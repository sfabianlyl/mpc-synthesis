import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileCheck, Users, XCircle } from 'lucide-react';
import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Conversation, ConsensusSubmission, Group, SynthesisReport } from '@/types/models';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface PageProps {
    conversations: Conversation[];
    groups: Group[];
    report: SynthesisReport | null;
    selectedConversationId: number | null;
    consensusSubmissions: Record<number, ConsensusSubmission[]>;
}

export default function Dashboard({ conversations, groups, report, selectedConversationId, consensusSubmissions }: PageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    // Calculate responses stats
    const totalGroups = groups.length;
    const submittedResponsesCount = groups.filter(g => g.responses && g.responses.length > 0).length;

    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }
        if (flash?.errors?.analysis) {
            setStatusMessage({ type: 'error', text: flash.errors.analysis });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const handleConversationChange = (value: string) => {
        router.get('/synthesis', { conversation_id: value }, { preserveState: true });
    };

    return (
        <>
            <Head title="Synthesis Dashboard" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header Row */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Synthesis Dashboard</h1>
                        <p className="text-muted-foreground mt-1">
                            Synthesize consensus responses from your assigned conversations into consolidated session reports.
                        </p>
                    </div>

                    {/* Conversation Selector */}
                    {conversations.length > 0 && (
                        <div className="flex items-center gap-3">
                            <span className="text-sm font-medium text-muted-foreground shrink-0">Selected Session:</span>
                            <Select
                                value={String(selectedConversationId)}
                                onValueChange={handleConversationChange}
                            >
                                <SelectTrigger className="w-[280px]">
                                    <SelectValue placeholder="Select Session" />
                                </SelectTrigger>
                                <SelectContent>
                                    {conversations.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.title} {c.status === 'completed' && '(Completed)'}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                    )}
                </div>

                {/* Status alert */}
                {statusMessage && (
                    <div className={`flex items-center gap-2 p-4 rounded-lg border ${
                        statusMessage.type === 'success'
                            ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600'
                            : 'bg-destructive/10 border-destructive/20 text-destructive'
                    }`}>
                        {statusMessage.type === 'success' ? (
                            <CheckCircle2 className="h-5 w-5 shrink-0" />
                        ) : (
                            <XCircle className="h-5 w-5 shrink-0" />
                        )}
                        <span className="text-sm font-medium">{statusMessage.text}</span>
                    </div>
                )}

                {/* Consensus Submission Status Card */}
                {selectedConversationId && (
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 flex flex-row items-center justify-between pb-4">
                            <div>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Users className="size-5" />
                                    Consensus Submission Status
                                </CardTitle>
                                <CardDescription>Track which facilitators have submitted their group's consensus response.</CardDescription>
                            </div>
                            <Badge variant="outline" className="h-6">
                                {Object.values(consensusSubmissions).flat().filter(s => s.is_submitted).length} / {Object.values(consensusSubmissions).flat().length} Submitted
                            </Badge>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {Object.keys(consensusSubmissions).length === 0 ? (
                                <div className="text-center py-8 text-muted-foreground text-sm">
                                    <Users className="size-10 mx-auto text-muted-foreground/30 mb-2" />
                                    <p>No consensus submissions received for this session yet.</p>
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Group</TableHead>
                                            <TableHead>Facilitator</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Submitted At</TableHead>
                                            <TableHead>Consensus Text Preview</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {Object.entries(consensusSubmissions).map(([groupId, submissions]) => (
                                            submissions.map((submission) => (
                                                <TableRow key={submission.id}>
                                                    <TableCell className="font-semibold">
                                                        Group #{groupId}
                                                    </TableCell>
                                                    <TableCell>
                                                        {submission.facilitator_name ? (
                                                            <span className="flex items-center gap-2">
                                                                <div className="size-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium text-primary">
                                                                    {submission.facilitator_name.split(' ').map(n => n[0]).join('').slice(0, 2)}
                                                                </div>
                                                                {submission.facilitator_name}
                                                            </span>
                                                        ) : (
                                                            <span className="text-muted-foreground italic">Unassigned</span>
                                                        )}
                                                    </TableCell>
                                                    <TableCell>
                                                        <Badge variant={submission.is_submitted ? 'default' : 'secondary'} className={submission.is_submitted ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/10' : 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/10'}>
                                                            {submission.is_submitted ? (
                                                                <><CheckCircle2 className="size-3.5 mr-1" /> Submitted</>
                                                            ) : (
                                                                <><XCircle className="size-3.5 mr-1" /> Pending</>
                                                            )}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="text-muted-foreground text-sm">
                                                        {submission.submitted_at ? new Date(submission.submitted_at).toLocaleString() : '—'}
                                                    </TableCell>
                                                    <TableCell className="max-w-[300px] truncate text-muted-foreground text-xs font-normal">
                                                        {submission.consensus_text || '—'}
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* Main Content Split Grid */}
                <div className="grid gap-6 lg:grid-cols-3">

                    {/* Synthesis Report Status Card (Takes 1 Col) */}
                    <Card className="border-sidebar-border/50 lg:col-span-1 flex flex-col">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30">
                            <CardTitle className="text-lg">Synthesis Report</CardTitle>
                            <CardDescription>Generate or edit your synthesis report for this session.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6 flex-1 flex flex-col justify-between gap-6">
                            <div>
                                {report ? (
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Report Status:</span>
                                            <Badge variant={report.status === 'submitted' ? 'default' : 'secondary'}>
                                                {report.status === 'submitted' ? 'Submitted' : 'Draft'}
                                            </Badge>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Generated via AI:</span>
                                            <Badge variant={report.llm_generated ? 'outline' : 'secondary'}>
                                                {report.llm_generated ? 'Yes' : 'Manual'}
                                            </Badge>
                                        </div>
                                        <p className="text-sm text-muted-foreground mt-2">
                                            A report has been created. You can review the convergence, consideration and proposals.
                                        </p>
                                    </div>
                                ) : (
                                    <div className="text-center py-6">
                                        <FileCheck className="size-10 mx-auto text-primary/30 mb-2" />
                                        <p className="text-sm text-muted-foreground">
                                            No synthesis report has been generated yet for this session.
                                        </p>
                                        <p className="text-xs text-muted-foreground/60 mt-1">
                                            {submittedResponsesCount} / {totalGroups} responses received.
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="mt-auto pt-4 border-t border-sidebar-border/30 flex flex-col gap-2">
                                <Link href={`/synthesis/review/${selectedConversationId}`} className="w-full">
                                    <Button className="w-full flex items-center justify-center gap-2" variant={report && report.status === 'submitted' ? 'outline' : 'default'}>
                                        <FileCheck className="size-4" /> View Synthesis
                                    </Button>
                                </Link>
                                {!report && submittedResponsesCount === 0 && (
                                    <p className="text-[11px] text-center text-destructive">
                                        At least 1 consensus response is required before viewing synthesis.
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Groups and Responses Overview Table (Takes 2 Cols) */}
                    <Card className="border-sidebar-border/50 lg:col-span-2">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 flex flex-row items-center justify-between pb-4">
                            <div>
                                <CardTitle className="text-lg">All Groups for Session</CardTitle>
                                <CardDescription>Monitor which groups have submitted their responses for this session.</CardDescription>
                            </div>
                            <Badge variant="outline" className="h-6">
                                {submittedResponsesCount} / {totalGroups} Submitted
                            </Badge>
                        </CardHeader>
                        <CardContent className="pt-6">
                            {groups.length === 0 ? (
                                <div className="text-center py-8 text-muted-foreground text-sm">
                                    No groups with responses in your assigned conversations.
                                </div>
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Group Name</TableHead>
                                            <TableHead>Submission Status</TableHead>
                                            <TableHead className="max-w-[400px]">Paragraph Snippet</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {groups.map((group) => {
                                            const hasSubmitted = group.responses && group.responses.length > 0;
                                            const responseText = hasSubmitted ? group.responses![0].consensus_text : '';
                                            return (
                                                <TableRow key={group.id}>
                                                    <TableCell className="font-semibold">{group.name}</TableCell>
                                                    <TableCell>
                                                        <Badge variant={hasSubmitted ? 'default' : 'secondary'} className={hasSubmitted ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/10' : 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/10'}>
                                                            {hasSubmitted ? 'Received' : 'Pending'}
                                                        </Badge>
                                                    </TableCell>
                                                    <TableCell className="max-w-[400px] truncate text-muted-foreground text-xs font-normal">
                                                        {hasSubmitted ? responseText : '—'}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>

                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout breadcrumbs={[{ title: 'Synthesis Dashboard', href: '/synthesis' }]}>{page}</AppLayout>;
