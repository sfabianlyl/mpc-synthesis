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
    selectedConversationId: number | null;
    consensusSubmissions: Record<number, ConsensusSubmission[]>;
}

export default function Dashboard({ 
    conversations, 
    groups, 
    selectedConversationId, 
    consensusSubmissions 
}: PageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [bookletUrl, setBookletUrl] = useState<string | null>(null);
    const [bookletWindow, setBookletWindow] = useState<Window | null>(null);

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

    return (
        <>
            <Head title="Synthesis Dashboard" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header Row with Action Buttons */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    
                    <div className="flex flex-col md:flex-row items-end md:items-center gap-4">
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
                    <div className="flex flex-col md:flex-row items-end md:items-center gap-4">
                        {/* Action Buttons moved to top, aligned right */}

                        {selectedConversationId && (
                            <div className="flex items-center gap-2">
                                <Link href={`/synthesis/review/${selectedConversationId}`} className="w-full">
                                    <Button className="w-full sm:w-auto flex items-center justify-center gap-2" variant='outline'>
                                        <FileCheck className="size-4" /> Begin Synthesis
                                    </Button>
                                </Link>
                                <Link href={`/synthesis/collaborate/${selectedConversationId}`} className="w-full">
                                    <Button variant="outline" className="w-full sm:w-auto flex items-center justify-center gap-2">
                                        <Users className="size-4" /> Collaborative Review
                                    </Button>
                                </Link>
                                {/* Export Booklet with Download PDF */}
                                {bookletUrl && bookletWindow ? (
                                    <Button
                                        variant="outline"
                                        className="w-full sm:w-auto flex items-center justify-center gap-2"
                                        onClick={() => bookletWindow?.print()}
                                    >
                                        <FileDown className="size-4" /> Download PDF
                                    </Button>
                                ) : null}
                                <Button
                                    variant="outline"
                                    className="w-full sm:w-auto flex items-center justify-center gap-2"
                                    onClick={() => {
                                        const url = `/synthesis/export-booklet/${selectedConversationId}`;
                                        const win = window.open(url, '_blank');
                                        setBookletUrl(url);
                                        setBookletWindow(win);
                                    }}
                                >
                                    <FileDown className="size-4" /> Export Booklet
                                </Button>
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
               

                {/* ─── Consensus Submission Status Cards ───────────── */}
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
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {Object.entries(consensusSubmissions).map(([groupId, submissions]) => (
                                        submissions.map((submission) => {
                                            const displayName = getDisplayName(submission);

                                            return (
                                                <Card key={submission.id} className={`border-sidebar-border/50 hover:shadow-md transition-shadow ${submission.is_submitted ? 'bg-emerald-500/5' : 'bg-muted/20'}`}>
                                                    <CardHeader className="pb-3">
                                                        <CardTitle className="text-base flex items-center gap-2">
                                                            {submission.is_submitted && <CheckCircle2 className="size-5 text-emerald-500" />}
                                                            {displayName || `Group ${groupId}`}
                                                        </CardTitle>
                                                    </CardHeader>
                                                    <CardContent className="pt-0">
                                                        <p className="text-sm text-muted-foreground leading-relaxed whitespace-pre-wrap">
                                                            {submission.consensus_text || 'No consensus text provided.'}
                                                        </p>
                                                    </CardContent>
                                                </Card>
                                            );
                                        })
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout breadcrumbs={[{ title: 'Synthesis Dashboard', href: '/synthesis' }]}>{page}</AppLayout>;
