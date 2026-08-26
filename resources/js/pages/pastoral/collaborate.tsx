import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Sparkles,
    Loader2,
    CheckCircle2,
    XCircle,
    FileText,
    Users,
    ArrowRight,
    Save,
    Shield,
    Globe,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface DraftItem {
    id: number;
    user_id: number;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    user?: { id: number; name: string; email: string } | null;
}

interface SharedDecisionPartData {
    id: number | null;
    national_text?: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
}

interface CollaboratePageProps {
    conversation: {
        id: number;
        title: string;
        description: string | null;
        status: string;
        event_id: number | null;
        event?: {
            id: number;
            title: string;
        } | null;
    };
    eventConversations: Array<{ id: number; title: string; event_id: number | null }>;
    progress: {
        submitted_count: number;
        total_required: number;
    };
    nationalDrafts?: DraftItem[];
    nationalDecisionPart?: SharedDecisionPartData | null;
}

export default function Collaborate({
    conversation,
    eventConversations = [],
    progress,
    nationalDrafts = [],
    nationalDecisionPart,
}: CollaboratePageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isFinalizing, setIsFinalizing] = useState(false);

    const [nationalText, setNationalText] = useState(nationalDecisionPart?.national_text ?? '');

    const isFinalized = Boolean(nationalDecisionPart?.is_submitted);

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }

        if (flash?.errors) {
            const errorObj = flash.errors as Record<string, string>;
            const errorMsg = Object.values(errorObj).find((v) => v);
            if (errorMsg) {
                setStatusMessage({ type: 'error', text: errorMsg });
                const timer = setTimeout(() => setStatusMessage(null), 5000);
                return () => clearTimeout(timer);
            }
        }
    }, [flash]);

    // Sync state when props change
    useEffect(() => {
        if (nationalDecisionPart?.national_text !== undefined) {
            setNationalText(nationalDecisionPart.national_text ?? '');
        }
    }, [nationalDecisionPart]);

    // Trigger AI summary generation
    const handleGenerateSummary = () => {
        setIsGenerating(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/collaborate/${conversation.id}/generate-summary`,
            {},
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    setIsGenerating(false);
                    const respProps = page.props as any;
                    if (respProps.nationalDecisionPart?.national_text) {
                        setNationalText(respProps.nationalDecisionPart.national_text);
                    }
                    setStatusMessage({ type: 'success', text: 'AI synthesized draft for this session successfully.' });
                },
                onError: (errors: any) => {
                    setIsGenerating(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to generate summary.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Save Shared Decision Part as draft
    const handleSaveDraft = () => {
        setIsSaving(true);
        setStatusMessage(null);

        router.patch(
            `/pastoral/collaborate/${conversation.id}/save-part`,
            { national_text: nationalText },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                    setStatusMessage({ type: 'success', text: 'Decision Part draft saved.' });
                },
                onError: (errors: any) => {
                    setIsSaving(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to save Decision Part.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Finalize Shared Decision Part
    const handleFinalize = () => {
        if (!confirm('Are you sure you want to finalize the National Decision Part for this session?')) {
            return;
        }

        setIsFinalizing(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/collaborate/${conversation.id}/finalize-part`,
            { national_text: nationalText },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsFinalizing(false);
                },
                onError: (errors: any) => {
                    setIsFinalizing(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to finalize Decision Part.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    return (
        <>
            <Head title={`Collaborate - ${conversation.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-[1600px] mx-auto">
                {/* ─── Top Session Stepper ─────────────────────────── */}
                {eventConversations.length > 1 && (
                    <div className="flex items-center gap-2 overflow-x-auto pb-2 border-b">
                        <span className="text-xs font-semibold uppercase text-muted-foreground mr-2 shrink-0">
                            Conversations:
                        </span>
                        {eventConversations.map((conv) => {
                            const isCurrent = conv.id === conversation.id;
                            return (
                                <Link key={conv.id} href={`/pastoral/collaborate/${conv.id}`}>
                                    <Button
                                        variant={isCurrent ? 'default' : 'outline'}
                                        size="sm"
                                        className={`gap-1.5 text-xs ${
                                            isCurrent ? 'bg-indigo-600 hover:bg-indigo-700' : ''
                                        }`}
                                    >
                                        <Users className="size-3.5" />
                                        {conv.title}
                                    </Button>
                                </Link>
                            );
                        })}
                    </div>
                )}

                {/* ─── Header Section ─────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-3xl font-bold tracking-tight">Collaborative Discernment Pathways</h1>
                            <Badge variant="outline" className="text-xs">
                                Step 2 of 3: Collaborate
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-base">
                            Conversation: <span className="font-semibold text-foreground">{conversation.title}</span>
                            {conversation.event?.title && ` · Event: ${conversation.event.title}`}
                        </p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Collaborate with other pastoral members to come up with the pastoral pathways for {conversation.title}.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {isFinalized ? (
                            <Badge variant="default" className="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1">
                                <CheckCircle2 className="h-4 w-4" />
                                Pathways Finalized
                            </Badge>
                        ) : (
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Globe className="size-3" />
                                Progress: {progress.submitted_count}/{progress.total_required} members submitted
                            </div>
                        )}
                    </div>
                </div>

                {/* ─── Status Message ─────────────────────────────── */}
                {statusMessage && (
                    <div
                        className={`flex items-center gap-2 p-4 rounded-lg border ${
                            statusMessage.type === 'success'
                                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                                : 'bg-destructive/10 border-destructive/20 text-destructive'
                        }`}
                    >
                        {statusMessage.type === 'success' ? (
                            <CheckCircle2 className="h-5 w-5 shrink-0" />
                        ) : (
                            <XCircle className="h-5 w-5 shrink-0" />
                        )}
                        <span className="text-sm font-medium">{statusMessage.text}</span>
                    </div>
                )}

                {/* ─── Full Editor View ─────────── */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    {/* ─── Left Column: Submitted Member Drafts ─── */}
                    <div className="lg:col-span-6 flex flex-col gap-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <h2 className="text-lg font-semibold flex items-center gap-2">
                                <Users className="size-5 text-indigo-600" />
                                Submitted Individual Pathways
                            </h2>
                        </div>

                        <div className="space-y-4 max-h-[880px] overflow-y-auto pr-1">
                            <div className="space-y-3">
                                <div className="flex items-center gap-2 pt-1">
                                    <Globe className="size-4 text-indigo-600" />
                                    <h3 className="text-sm font-semibold text-foreground uppercase tracking-wider">
                                        Individual Pastoral Pathways ({nationalDrafts.length})
                                    </h3>
                                </div>

                                {nationalDrafts.length === 0 ? (
                                    <p className="text-xs text-muted-foreground italic">No drafts submitted yet.</p>
                                ) : (
                                    nationalDrafts.map((draft) => (
                                        <Card key={`nat-${draft.id}`} className="border-sidebar-border/60 shadow-xs">
                                            <CardHeader className="py-2.5 px-3 bg-muted/20 flex flex-row items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Users className="size-3.5 text-muted-foreground" />
                                                    <span className="text-xs font-semibold">{draft.user?.name || 'Member'}</span>
                                                </div>
                                                <Badge variant="outline" className="text-[11px] px-1.5 py-0">
                                                    Submitted
                                                </Badge>
                                            </CardHeader>
                                            <CardContent className="p-3 text-xs leading-relaxed whitespace-pre-wrap font-sans text-foreground/90">
                                                {draft.draft_text || <span className="text-muted-foreground italic">No content provided</span>}
                                            </CardContent>
                                        </Card>
                                    ))
                                )}
                            </div>
                        </div>
                    </div>

                    {/* ─── Right Column: Shared Decision Part Editor ── */}
                    <div className="lg:col-span-6 flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold flex items-center gap-2">
                                    <Shield className="size-5 text-indigo-600" />
                                    {conversation.title} Pastoral Pathways
                                </h2>
                            </div>

                            <Button
                                onClick={handleGenerateSummary}
                                disabled={isGenerating || isFinalized}
                                size="sm"
                                className="gap-1.5 bg-purple-600 hover:bg-purple-700 text-xs shrink-0"
                            >
                                {isGenerating ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                                {isGenerating ? 'Synthesizing...' : 'AI Synthesize'}
                            </Button>
                        </div>

                        {/* National Decision Part Card */}
                        <Card className={isFinalized ? 'border-emerald-500/40 bg-emerald-50/5' : 'border-indigo-200 dark:border-indigo-900/50'}>
                            <CardHeader className="py-2.5 px-4 bg-muted/20 border-b flex flex-row items-center justify-between">
                                <div className="flex items-center gap-2">
                                    <Globe className="size-4 text-indigo-600" />
                                    <CardTitle className="text-sm font-semibold">Shared Pastoral Pathway</CardTitle>
                                </div>
                                {nationalDecisionPart?.is_submitted && (
                                    <Badge variant="default" className="bg-emerald-600 text-[10px] px-1.5 py-0">
                                        Finalized
                                    </Badge>
                                )}
                            </CardHeader>
                            <CardContent className="p-3">
                                <Textarea
                                    value={nationalText}
                                    onChange={(e) => setNationalText(e.target.value)}
                                    placeholder="Enter the consolidated National Decision Part for this session..."
                                    rows={9}
                                    disabled={isFinalized}
                                    className="resize-none font-mono text-xs leading-relaxed"
                                />
                            </CardContent>
                        </Card>

                        {/* Actions */}
                        {!isFinalized ? (
                            <div className="flex justify-between items-center pt-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={handleSaveDraft}
                                    disabled={isSaving || isFinalizing}
                                    className="gap-1.5"
                                >
                                    {isSaving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                    Save Draft
                                </Button>

                                <Button
                                    size="sm"
                                    onClick={handleFinalize}
                                    disabled={isSaving || isFinalizing || !nationalText.trim()}
                                    className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                >
                                    {isFinalizing ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                    Finalize
                                </Button>
                            </div>
                        ) : (
                            conversation.event_id && (
                                <div className="p-3 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/20 flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-xs text-emerald-700 dark:text-emerald-300">
                                        <CheckCircle2 className="size-4" />
                                        <span>The pathway for this conversation have been finalized.</span>
                                    </div>
                                    <Link href={`/pastoral/master-decision/${conversation.event_id}`}>
                                        <Button size="sm" className="gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-xs">
                                            Go to Overall Pastoral Pathways
                                            <ArrowRight className="size-3.5" />
                                        </Button>
                                    </Link>
                                </div>
                            )
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Collaborate.layout = (page: React.ReactNode) => {
    const props = page as any;
    const conversation = props?.props?.conversation;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
                { title: `Collaborate: ${conversation?.title || 'Session'}`, href: `/pastoral/collaborate/${conversation?.id || ''}` },
            ]}
        >
            {page}
        </AppLayout>
    );
};
