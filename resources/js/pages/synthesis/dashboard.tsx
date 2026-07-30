import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, FileCheck, FileDown, Users, XCircle, Clock, Layers } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { Conversation, ConsensusSubmission, Group, SynthesisSubmission } from '@/types/models';

interface PageProps {
    conversations: Conversation[];
    groups: Group[];
    submissions: SynthesisSubmission[];
    masterSynthesis: any | null;
    selectedConversationId: number | null;
    consensusSubmissions: Record<number, ConsensusSubmission[]>;
}

export default function Dashboard({ 
    conversations, 
    groups, 
    submissions = [], 
    masterSynthesis,
    selectedConversationId, 
    consensusSubmissions 
}: PageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    // Calculate responses stats
    const totalGroups = groups.length;
    const submittedResponsesCount = groups.filter(g => g.responses && g.responses.length > 0).length;

    // Count submission statuses
    const submittedCount = submissions.filter(s => s.status === 'submitted').length;
    const draftCount = submissions.filter(s => s.status === 'draft').length;

    // Get highest submission number for this conversation
    const highestSubmissionNumber = submissions.reduce((max, s) => Math.max(max, s.submission_number || 0), 0);

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

    // Helper to get display name with fallback
    const getDisplayName = (submission: ConsensusSubmission): string => {
        const anySub = submission as any;
        return anySub.group_name || anySub.facilitator_name || '';
    };

    // Group submissions by user for history display
    const submissionsByUser = submissions.reduce<Record<number, SynthesisSubmission[]>>((acc, s) => {
        if (!acc[s.user.id]) {
            acc[s.user.id] = [];
        }
        acc[s.user.id].push(s);
        return acc;
    }, {});

    return (
        <>
            <Head title="Synthesis Dashboard" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header Row with Action Buttons */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Synthesis Dashboard</h1>
                        <p className="text-muted-foreground mt-1">
                            Synthesize consensus responses from your assigned conversations into consolidated session reports.
                        </p>
                    </div>

                    <div className="flex flex-col md:flex-row items-end md:items-center gap-4">
                        {/* Action Buttons moved to top, aligned right */}
                        {selectedConversationId && (
                            <div className="flex items-center gap-2">
                                <Link href={`/synthesis/review/${selectedConversationId}`} className="w-full">
                                    <Button className="w-full sm:w-auto flex items-center justify-center gap-2" variant={submissions.some(s => s.status === 'submitted') ? 'outline' : 'default'}>
                                        <FileCheck className="size-4" /> View Synthesis
                                    </Button>
                                </Link>
                                <Link href={`/synthesis/collaborate/${selectedConversationId}`} className="w-full">
                                    <Button variant="outline" className="w-full sm:w-auto flex items-center justify-center gap-2">
                                        <Users className="size-4" /> Collaborative Review
                                    </Button>
                                </Link>
                                <Button
                                    variant="outline"
                                    className="w-full sm:w-auto flex items-center justify-center gap-2"
                                    onClick={() => window.open(`/synthesis/export-booklet/${selectedConversationId}`, '_blank')}
                                >
                                    <FileDown className="size-4" /> Export Booklet
                                </Button>
                            </div>
                        )}

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

                {/* ─── Synthesis Submission Stats Cards ───────────── */}
                {selectedConversationId && (
                    <div className="grid gap-4 md:grid-cols-3">
                        <Card>
                            <CardContent className="pt-6 flex items-center gap-3">
                                <div className="p-2 bg-green-50 dark:bg-green-950/20 rounded-lg">
                                    <CheckCircle2 className="h-5 w-5 text-green-600" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{submittedCount}</p>
                                    <p className="text-xs text-muted-foreground">Submitted</p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6 flex items-center gap-3">
                                <div className="p-2 bg-amber-50 dark:bg-amber-950/20 rounded-lg">
                                    <Clock className="h-5 w-5 text-amber-600" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{draftCount}</p>
                                    <p className="text-xs text-muted-foreground">Draft</p>
                                </div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardContent className="pt-6 flex items-center gap-3">
                                <div className="p-2 bg-indigo-50 dark:bg-indigo-950/20 rounded-lg">
                                    <FileCheck className="h-5 w-5 text-indigo-600" />
                                </div>
                                <div>
                                    <p className="text-2xl font-bold">{masterSynthesis?.id ? '1' : '0'}</p>
                                    <p className="text-xs text-muted-foreground">Master Synthesis</p>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                )}

                {/* ─── Synthesis Submission History ───────────────── */}
                {selectedConversationId && submissions.length > 0 && (
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 flex flex-row items-center justify-between pb-4">
                            <div>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Layers className="size-5" />
                                    Synthesis Submission History
                                </CardTitle>
                                <CardDescription>
                                    Track all synthesis submissions (individual and master) for this session.
                                    {highestSubmissionNumber > 1 && ` Highest submission number: ${highestSubmissionNumber}`}
                                </CardDescription>
                            </div>
                            <Badge variant="outline">
                                {submissions.length} submission{submissions.length !== 1 ? 's' : ''}
                            </Badge>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Submission #</TableHead>
                                        <TableHead>Synthesis Member</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Submitted At</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {submissions.map((submission) => (
                                        <TableRow key={submission.id}>
                                            <TableCell className="font-medium">
                                                <Badge variant="outline" className="text-xs">
                                                    #{submission.submission_number || 1}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="size-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium text-primary">
                                                        {submission.user.name.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()}
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium">{submission.user.name}</p>
                                                        <p className="text-xs text-muted-foreground">{submission.user.email}</p>
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge 
                                                    variant={submission.status === 'submitted' ? 'default' : submission.status === 'reviewed' ? 'outline' : 'secondary'} 
                                                    className={
                                                        submission.status === 'submitted' 
                                                            ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/10' 
                                                            : submission.status === 'reviewed'
                                                                ? 'bg-blue-500/10 text-blue-600 hover:bg-blue-500/10'
                                                                : 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/10'
                                                    }
                                                >
                                                    {submission.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {submission.submitted_at ? new Date(submission.submitted_at).toLocaleString() : '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <Link href={`/synthesis/review/${submission.id}`}>
                                                    <Button variant="link" size="sm" className="text-xs">View Details</Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Show Master Synthesis Info ─────────────────── */}
                {selectedConversationId && masterSynthesis && (
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 flex flex-row items-center justify-between pb-4">
                            <div>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <FileCheck className="size-5 text-indigo-600" />
                                    Master Synthesis Status
                                </CardTitle>
                                <CardDescription>
                                    Master synthesis aggregates content from all individual submissions.
                                </CardDescription>
                            </div>
                            <Badge variant="default" className="bg-indigo-500 hover:bg-indigo-600">
                                {masterSynthesis.is_master_synthesis ? 'Master Synthesis' : 'Individual'}
                            </Badge>
                        </CardHeader>
                        <CardContent className="pt-6 space-y-4">
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Convergence</p>
                                    {masterSynthesis.convergence_text ? (
                                        <Badge variant="outline" className="bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600">Completed</Badge>
                                    ) : (
                                        <Badge variant="secondary">Not Started</Badge>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Matters for Consideration</p>
                                    {masterSynthesis.consideration_text ? (
                                        <Badge variant="outline" className="bg-amber-50 dark:bg-amber-950/20 text-amber-600">Completed</Badge>
                                    ) : (
                                        <Badge variant="secondary">Not Started</Badge>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Proposals</p>
                                    {masterSynthesis.proposals_text ? (
                                        <Badge variant="outline" className="bg-blue-50 dark:bg-blue-950/20 text-blue-600">Completed</Badge>
                                    ) : (
                                        <Badge variant="secondary">Not Started</Badge>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <p className="text-xs font-medium text-muted-foreground">Others</p>
                                    {masterSynthesis.other_text ? (
                                        <Badge variant="outline" className="bg-violet-50 dark:bg-violet-950/20 text-violet-600">Completed</Badge>
                                    ) : (
                                        <Badge variant="secondary">Not Started</Badge>
                                    )}
                                </div>
                            </div>

                            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Clock className="h-4 w-4" />
                                <span>Last updated: {new Date(masterSynthesis.updated_at).toLocaleString()}</span>
                            </div>

                            <div className="flex gap-2 pt-2">
                                <Link href={`/synthesis/collaborate/${selectedConversationId}`}>
                                    <Button variant="outline" size="sm">
                                        Edit Master Synthesis
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Consensus Submission Status Card ───────────── */}
                {selectedConversationId && (
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 flex flex-row items-center justify-between pb-4">
                            <div>
                                <CardTitle className="text-lg flex items-center gap-2">
                                    <Users className="size-5" />
                                    Consensus Submission Status
                                </CardTitle>
                                <CardDescription>Track which groups have submitted their group's consensus response.</CardDescription>
                            </div>
                            <Badge variant="outline" className="h-6">
                                {Object.keys(consensusSubmissions).filter(groupId => Object.values(consensusSubmissions[Number(groupId)]).some(s => s.is_submitted)).length} / {groups.length} Submitted
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
                                            <TableHead>Group Name</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Submitted At</TableHead>
                                            <TableHead>Consensus Text Preview</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {Object.entries(consensusSubmissions).map(([groupId, submissions]) => (
                                            submissions.map((submission) => {
                                                const anySub = submission as any;
                                                const displayName = getDisplayName(submission);
                                                const initials = displayName
                                                    ? displayName.split(' ').map((n: string) => n[0]).join('').slice(0, 2)
                                                    : '';

                                                return (
                                                    <TableRow key={submission.id}>
                                                        <TableCell>
                                                            {displayName ? (
                                                                <span className="flex items-center gap-2">
                                                                    <div className="size-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium text-primary">
                                                                        {initials}
                                                                    </div>
                                                                    G{groupId}
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
                                                );
                                            })
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* ─── Main Content Split Grid ────────────────────── */}
                <div className="grid gap-6 lg:grid-cols-3">

                    {/* Synthesis Report Status Card (Takes 1 Col) */}
                    <Card className="border-sidebar-border/50 lg:col-span-1 flex flex-col">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30">
                            <CardTitle className="text-lg">Synthesis Report</CardTitle>
                            <CardDescription>Generate or edit your synthesis report for this session.</CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6 flex-1 flex flex-col justify-between gap-6">
                            <div>
                                {submissions.length > 0 ? (
                                    <div className="space-y-4">
                                        {(() => {
                                            const userSubmission = submissions.find(s => s.user.id === (usePage().props as any).currentUser?.id);
                                            if (!userSubmission) return null;
                                            
                                            return (
                                                <>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm text-muted-foreground">Report Status:</span>
                                                        <Badge variant={userSubmission.status === 'submitted' ? 'default' : 'secondary'}>
                                                            {userSubmission.status === 'submitted' ? 'Submitted' : 'Draft'}
                                                        </Badge>
                                                    </div>
                                                    <div className="flex items-center justify-between">
                                                        <span className="text-sm text-muted-foreground">Submission #:</span>
                                                        <Badge variant="outline">#{userSubmission.submission_number || 1}</Badge>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground mt-2">
                                                        A report has been created. You can review the convergence, consideration and proposals.
                                                    </p>
                                                </>
                                            );
                                        })()}
                                    </div>
                                ) : (
                                    <div className="text-center py-6">
                                        <FileCheck className="size-10 mx-auto text-primary/30 mb-2" />
                                        <p className="text-sm text-muted-foreground">
                                            No synthesis report has been generated yet for this session.
                                        </p>
                                        <p className="text-xs text-muted-foreground/60 mt-1">
                                            {submittedResponsesCount} / {totalGroups} groups submitted.
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="mt-auto pt-4 border-t border-sidebar-border/30 flex flex-col gap-2">
                                {!submissions.length && submittedResponsesCount === 0 && (
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
