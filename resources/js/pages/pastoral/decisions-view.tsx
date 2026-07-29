import { Head, usePage } from '@inertiajs/react';
import { FileText, Users, Shield, CheckCircle2, Clock, BookOpen } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { DecisionSummary, DecisionDraft, MasterDecision, Diocese } from '@/types/models';

interface PageProps {
    masterDecisions: MasterDecision[];
    decisionSummaries: DecisionSummary[];
    decisionDrafts: DecisionDraft[];
    dioceses: Diocese[];
    userRole: string;
}

export default function DecisionsView({
    masterDecisions = [],
    decisionSummaries = [],
    decisionDrafts = [],
    dioceses = [],
    userRole,
}: PageProps) {
    const { auth } = usePage().props as any;
    const user = auth?.user;
    const isDiscernment = userRole === 'discernment';

    // Group decision summaries by diocese
    const summariesByDiocese = dioceses.reduce<Record<number, DecisionSummary[]>>((acc, diocese) => {
        acc[diocese.id] = decisionSummaries.filter(s => s.diocese_id === diocese.id);
        return acc;
    }, {});

    // Group decision drafts by diocese
    const draftsByDiocese = dioceses.reduce<Record<number, DecisionDraft[]>>((acc, diocese) => {
        acc[diocese.id] = decisionDrafts.filter(d => d.diocese_id === diocese.id);
        return acc;
    }, {});

    return (
        <>
            <Head title="Decisions Overview" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Decisions Overview</h1>
                        <p className="text-muted-foreground mt-1">
                            View all decision summaries, drafts, and master decisions.
                            {isDiscernment && (
                                <span className="block text-sm text-indigo-600 dark:text-indigo-400 mt-1">
                                    Read-only mode - you have full access to view all decisions.
                                </span>
                            )}
                        </p>
                    </div>

                    {/* Current role badge */}
                    <Badge
                        variant="outline"
                        className={`px-3 py-1 text-sm ${
                            isDiscernment
                                ? 'bg-violet-50 dark:bg-violet-950/20 text-violet-600 border-violet-200 dark:border-violet-800'
                                : 'bg-blue-50 dark:bg-blue-950/20 text-blue-600 border-blue-200 dark:border-blue-800'
                        }`}
                    >
                        {isDiscernment ? <Shield className="size-3.5 mr-1.5" /> : <BookOpen className="size-3.5 mr-1.5" />}
                        {isDiscernment ? 'Discernment (Read-Only)' : 'Pastoral View'}
                    </Badge>
                </div>

                {/* Section 1: Master Decisions */}
                <Card className="border-primary/20">
                    <CardHeader>
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Shield className="size-5 text-indigo-600" />
                            National Master Decisions
                        </CardTitle>
                        <CardDescription>
                            The consolidated national-level decisions from all dioceses.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {masterDecisions.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground text-sm">
                                <Shield className="size-10 mx-auto text-muted-foreground/30 mb-2" />
                                <p>No master decisions have been created yet.</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {masterDecisions.map((master) => (
                                    <Card key={master.id} className="border-dashed bg-muted/20">
                                        <CardContent className="pt-6 pb-4">
                                            <div className="flex items-start justify-between gap-4 mb-3">
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium text-foreground">National Decision</p>
                                                    <p className="text-sm text-muted-foreground mt-1 whitespace-pre-wrap">
                                                        {master.national_decision || 'No national decision text available.'}
                                                    </p>
                                                </div>
                                                <Badge variant={master.is_finalized ? 'default' : 'secondary'} className="shrink-0">
                                                    {master.is_finalized ? (
                                                        <><CheckCircle2 className="size-3.5 mr-1" /> Finalized</>
                                                    ) : (
                                                        <><Clock className="size-3.5 mr-1" /> Draft</>
                                                    )}
                                                </Badge>
                                            </div>

                                            {master.diocesan_decisions && master.diocesan_decisions.length > 0 && (
                                                <>
                                                    <Separator className="my-4" />
                                                    <div>
                                                        <p className="text-sm font-medium text-muted-foreground mb-2">Diocesan Decisions</p>
                                                        <div className="space-y-2">
                                                            {master.diocesan_decisions.map((d, idx) => (
                                                                <div key={idx} className="flex items-start gap-3 p-3 rounded-lg bg-background/50 border">
                                                                    <div className="size-6 rounded-full bg-indigo-100 dark:bg-indigo-950/30 flex items-center justify-center text-xs font-medium text-indigo-600 shrink-0">
                                                                        {d.diocese_name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase()}
                                                                    </div>
                                                                    <div className="flex-1 min-w-0">
                                                                        <p className="text-sm font-medium">{d.diocese_name}</p>
                                                                        <p className="text-xs text-muted-foreground mt-0.5 whitespace-pre-wrap">
                                                                            {d.decision || 'No decision text.'}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    </div>
                                                </>
                                            )}

                                            <p className="text-xs text-muted-foreground mt-3">
                                                Created: {new Date(master.created_at).toLocaleDateString()} at {new Date(master.created_at).toLocaleTimeString()}
                                            </p>
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Separator />

                {/* Section 2: Decision Summaries by Diocese */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg flex items-center gap-2">
                            <FileText className="size-5 text-emerald-600" />
                            Decision Summaries by Diocese
                        </CardTitle>
                        <CardDescription>
                            Individual decision summaries submitted by each diocese.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {dioceses.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground text-sm">
                                <FileText className="size-10 mx-auto text-muted-foreground/30 mb-2" />
                                <p>No dioceses found.</p>
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {dioceses.map((diocese) => {
                                    const summaries = summariesByDiocese[diocese.id] || [];
                                    return (
                                        <Card key={diocese.id} className="border-dashed bg-muted/10">
                                            <CardHeader className="pb-3">
                                                <CardTitle className="text-base flex items-center gap-2">
                                                    <div className="size-8 rounded-full bg-emerald-100 dark:bg-emerald-950/30 flex items-center justify-center text-sm font-medium text-emerald-600">
                                                        {diocese.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase()}
                                                    </div>
                                                    {diocese.name}
                                                    <Badge variant="outline" className="text-xs">
                                                        {diocese.code}
                                                    </Badge>
                                                </CardTitle>
                                            </CardHeader>
                                            <CardContent>
                                                {summaries.length === 0 ? (
                                                    <p className="text-sm text-muted-foreground italic py-2">No decision summary submitted.</p>
                                                ) : (
                                                    <div className="space-y-3">
                                                        {summaries.map((summary) => (
                                                            <div key={summary.id} className="p-3 rounded-lg bg-background/50 border space-y-2">
                                                                <div className="flex items-start justify-between gap-4">
                                                                    <div className="flex-1 min-w-0">
                                                                        {summary.national_summary && (
                                                                            <div>
                                                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">National Summary</p>
                                                                                <p className="text-sm mt-0.5 whitespace-pre-wrap">{summary.national_summary}</p>
                                                                            </div>
                                                                        )}
                                                                        {summary.diocesan_summary && (
                                                                            <div className="mt-2">
                                                                                <p className="text-xs font-medium text-muted-foreground uppercase tracking-wider">Diocesan Summary</p>
                                                                                <p className="text-sm mt-0.5 whitespace-pre-wrap">{summary.diocesan_summary}</p>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                    <Badge variant={summary.is_finalized ? 'default' : 'secondary'} className="shrink-0">
                                                                        {summary.is_finalized ? (
                                                                            <><CheckCircle2 className="size-3.5 mr-1" /> Finalized</>
                                                                        ) : (
                                                                            <><Clock className="size-3.5 mr-1" /> Draft</>
                                                                        )}
                                                                    </Badge>
                                                                </div>
                                                                {summary.provider && (
                                                                    <p className="text-xs text-muted-foreground pt-1">
                                                                        Provider: <span className="font-medium">{summary.provider}</span>
                                                                    </p>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </div>
                                                )}
                                            </CardContent>
                                        </Card>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>

                <Separator />

                {/* Section 3: Decision Drafts */}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Users className="size-5 text-amber-600" />
                            Decision Drafts
                        </CardTitle>
                        <CardDescription>
                            Individual decision drafts submitted by members.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {decisionDrafts.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground text-sm">
                                <Users className="size-10 mx-auto text-muted-foreground/30 mb-2" />
                                <p>No decision drafts have been submitted yet.</p>
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Author</TableHead>
                                        <TableHead>Diocese</TableHead>
                                        <TableHead className="max-w-[300px]">National Summary Preview</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Date</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {decisionDrafts.map((draft) => (
                                        <TableRow key={draft.id}>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="size-6 rounded-full bg-amber-100 dark:bg-amber-950/30 flex items-center justify-center text-xs font-medium text-amber-600">
                                                        {draft.user?.name.split(' ').map((n: string) => n[0]).join('').slice(0, 2).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium">{draft.user?.name || 'Unknown'}</p>
                                                        <p className="text-xs text-muted-foreground">{draft.user?.email || ''}</p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                {draft.diocese ? (
                                                    <Badge variant="outline" className="text-xs">
                                                        {draft.diocese.name} ({draft.diocese.code})
                                                    </Badge>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">-</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="max-w-[300px] truncate text-muted-foreground text-sm">
                                                {draft.national_summary || '-'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={draft.is_finalized ? 'default' : 'secondary'} className="text-xs">
                                                    {draft.is_finalized ? (
                                                        <><CheckCircle2 className="size-3.5 mr-1" /> Finalized</>
                                                    ) : (
                                                        <><Clock className="size-3.5 mr-1" /> Draft</>
                                                    )}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right text-muted-foreground text-sm">
                                                {new Date(draft.created_at).toLocaleDateString()}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

DecisionsView.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Decisions', href: '/pastoral/decisions' }]}>{page}</AppLayout>
);
