import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Sparkles,
    Loader2,
    CheckCircle2,
    XCircle,
    FileText,
    Users,
    Lock,
    ArrowRight,
    Save,
    Shield,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface MemberDraft {
    id: number;
    user_id: number;
    national_text: string | null;
    diocesan_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    user?: { id: number; name: string; email: string } | null;
    diocese?: { id: number; name: string; code: string } | null;
}

interface DecisionPartData {
    id: number | null;
    national_text: string | null;
    diocesan_text: string | null;
    consolidated_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    submitted_by?: { id: number; name: string } | null;
    generated_by: string | null;
    generated_at: string | null;
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
    isUnlocked: boolean;
    progress: {
        submitted_count: number;
        total_required: number;
    };
    memberDrafts: MemberDraft[];
    decisionPart: DecisionPartData | null;
}

export default function Collaborate({
    conversation,
    eventConversations = [],
    isUnlocked,
    progress,
    memberDrafts = [],
    decisionPart,
}: CollaboratePageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [consolidatedText, setConsolidatedText] = useState(decisionPart?.consolidated_text ?? '');
    const [selectedDioceseFilter, setSelectedDioceseFilter] = useState<string>('all');

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

    // Sync text with decisionPart prop
    useEffect(() => {
        if (decisionPart?.consolidated_text) {
            setConsolidatedText(decisionPart.consolidated_text);
        }
    }, [decisionPart]);

    const isFinalized = Boolean(decisionPart?.is_submitted);

    // Filter member drafts
    const uniqueDioceses = Array.from(
        new Set(memberDrafts.map((d) => d.diocese?.name).filter(Boolean))
    ) as string[];

    const filteredDrafts = selectedDioceseFilter === 'all'
        ? memberDrafts
        : memberDrafts.filter((d) => d.diocese?.name === selectedDioceseFilter);

    // Trigger AI summary generation for this DecisionPart
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
                    if (page.props.flash?.success) {
                        setStatusMessage({ type: 'success', text: page.props.flash.success });
                    }
                },
                onError: (errors: any) => {
                    setIsGenerating(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to generate summary.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Save DecisionPart as draft
    const handleSaveDraft = () => {
        setIsSaving(true);
        setStatusMessage(null);

        router.patch(
            `/pastoral/collaborate/${conversation.id}/save-part`,
            { consolidated_text: consolidatedText },
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

    // Finalize DecisionPart
    const handleFinalize = () => {
        if (!confirm('Are you sure you want to finalize this Decision Part? This will mark this conversation as complete.')) {
            return;
        }

        setIsFinalizing(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/collaborate/${conversation.id}/finalize-part`,
            { consolidated_text: consolidatedText },
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
                            Sessions:
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
                            <h1 className="text-3xl font-bold tracking-tight">Collaborative Decision Part</h1>
                            <Badge variant="outline" className="text-xs">
                                Step 2 of 3: Shared Conversation Decision
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-base">
                            Session: <span className="font-semibold text-foreground">{conversation.title}</span>
                            {conversation.event?.title && ` · Event: ${conversation.event.title}`}
                        </p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Collaborate with other pastoral members to synthesize individual drafts into ONE shared Decision Part.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {isFinalized ? (
                            <Badge variant="default" className="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1">
                                <CheckCircle2 className="h-4 w-4" />
                                Decision Part Finalized
                            </Badge>
                        ) : isUnlocked ? (
                            <Badge variant="outline" className="text-indigo-600 border-indigo-300 dark:border-indigo-800 bg-indigo-50 dark:bg-indigo-950/20">
                                Collaboration Open
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="text-amber-600 border-amber-300 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/20 flex items-center gap-1">
                                <Lock className="size-3" />
                                Locked (Drafts Pending)
                            </Badge>
                        )}
                    </div>
                </div>

                {/* ─── Status Message ─────────────────────────────── */}
                {statusMessage && (
                    <div
                        className={`flex items-center gap-2 p-4 rounded-lg border ${
                            statusMessage.type === 'success'
                                ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600'
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

                {/* ─── Gated Locked State ─────────────────────────── */}
                {!isUnlocked ? (
                    <Card className="border-dashed bg-muted/20">
                        <CardContent className="py-16 flex flex-col items-center justify-center text-center gap-4 max-w-xl mx-auto">
                            <div className="p-3 bg-amber-100 dark:bg-amber-950/40 rounded-full text-amber-600">
                                <Lock className="size-8" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-lg font-semibold">Collaboration Locked</h3>
                                <p className="text-sm text-muted-foreground">
                                    All pastoral members must submit their individual decision drafts before the collaborative Decision Part can be produced.
                                </p>
                            </div>
                            <div className="w-full bg-muted rounded-full h-3 overflow-hidden border">
                                <div
                                    className="bg-indigo-600 h-full transition-all duration-500 rounded-full"
                                    style={{
                                        width: `${progress.total_required > 0 ? (progress.submitted_count / progress.total_required) * 100 : 0}%`,
                                    }}
                                />
                            </div>
                            <p className="text-xs text-muted-foreground font-medium">
                                {progress.submitted_count} of {progress.total_required} pastoral members submitted
                            </p>
                            <div className="flex gap-2 pt-2">
                                <Link href={`/pastoral/decision/${conversation.id}`}>
                                    <Button variant="default" size="sm" className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                                        <FileText className="size-4" /> Go to Your Draft
                                    </Button>
                                </Link>
                                <Link href="/pastoral/dashboard">
                                    <Button variant="outline" size="sm">
                                        Back to Dashboard
                                    </Button>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    /* ─── Unlocked Collaboration View ───────────── */
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        {/* ─── Left Column: Submitted Member Drafts ─── */}
                        <div className="lg:col-span-6 flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold flex items-center gap-2">
                                    <Users className="size-5 text-indigo-600" />
                                    Submitted Member Drafts ({memberDrafts.length})
                                </h2>

                                {/* Diocese Filter */}
                                {uniqueDioceses.length > 0 && (
                                    <select
                                        value={selectedDioceseFilter}
                                        onChange={(e) => setSelectedDioceseFilter(e.target.value)}
                                        aria-label="Filter member drafts by diocese"
                                        className="h-8 rounded-md border border-input bg-background px-2 text-xs"
                                    >
                                        <option value="all">All Dioceses ({memberDrafts.length})</option>
                                        {uniqueDioceses.map((d) => (
                                            <option key={d} value={d}>
                                                {d}
                                            </option>
                                        ))}
                                    </select>
                                )}
                            </div>

                            <div className="space-y-4 max-h-[800px] overflow-y-auto pr-1">
                                {filteredDrafts.map((draft) => (
                                    <Card key={draft.id} className="border-sidebar-border/60 shadow-sm">
                                        <CardHeader className="pb-3 bg-muted/20">
                                            <div className="flex items-center justify-between">
                                                <CardTitle className="text-sm font-semibold flex items-center gap-2">
                                                    <Users className="size-4 text-muted-foreground" />
                                                    {draft.user?.name || 'Pastoral Member'}
                                                </CardTitle>
                                                {draft.diocese && (
                                                    <Badge variant="secondary" className="text-xs">
                                                        {draft.diocese.name}
                                                    </Badge>
                                                )}
                                            </div>
                                        </CardHeader>
                                        <CardContent className="pt-3 space-y-3 text-sm">
                                            {draft.national_text && (
                                                <div>
                                                    <p className="text-xs font-semibold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider mb-1">
                                                        National Reflection:
                                                    </p>
                                                    <p className="text-xs whitespace-pre-wrap text-muted-foreground bg-muted/10 p-2.5 rounded border">
                                                        {draft.national_text}
                                                    </p>
                                                </div>
                                            )}

                                            {draft.diocesan_text && (
                                                <div>
                                                    <p className="text-xs font-semibold text-violet-600 dark:text-violet-400 uppercase tracking-wider mb-1">
                                                        Diocesan Reflection:
                                                    </p>
                                                    <p className="text-xs whitespace-pre-wrap text-muted-foreground bg-muted/10 p-2.5 rounded border">
                                                        {draft.diocesan_text}
                                                    </p>
                                                </div>
                                            )}

                                            {draft.submitted_at && (
                                                <p className="text-[11px] text-muted-foreground">
                                                    Submitted: {new Date(draft.submitted_at).toLocaleString()}
                                                </p>
                                            )}
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        </div>

                        {/* ─── Right Column: Shared DecisionPart Editor ── */}
                        <div className="lg:col-span-6 flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold flex items-center gap-2">
                                    <Shield className="size-5 text-indigo-600" />
                                    Shared Decision Part
                                </h2>

                                <Button
                                    onClick={handleGenerateSummary}
                                    disabled={isGenerating || isFinalized}
                                    size="sm"
                                    className="gap-1.5 bg-purple-600 hover:bg-purple-700 text-xs"
                                >
                                    {isGenerating ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                                    {isGenerating ? 'Synthesizing...' : 'AI Synthesize Drafts'}
                                </Button>
                            </div>

                            <Card className={isFinalized ? 'border-emerald-500/40 bg-emerald-50/5' : ''}>
                                <CardHeader className="pb-3 bg-muted/15 border-b">
                                    <div className="flex items-center justify-between">
                                        <CardDescription className="text-xs">
                                            {decisionPart?.generated_by && (
                                                <span className="mr-2">
                                                    AI Synthesized by {decisionPart.generated_by}
                                                </span>
                                            )}
                                            {decisionPart?.submitted_by && (
                                                <span className="text-emerald-600 font-medium">
                                                    Finalized by {decisionPart.submitted_by.name}
                                                </span>
                                            )}
                                        </CardDescription>
                                        {isFinalized && (
                                            <Badge variant="default" className="bg-emerald-600 text-xs">
                                                Locked & Finalized
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4 space-y-4">
                                    <Textarea
                                        value={consolidatedText}
                                        onChange={(e) => setConsolidatedText(e.target.value)}
                                        placeholder="The shared decision part for this session will appear here. Edit collaboratively..."
                                        rows={18}
                                        disabled={isFinalized}
                                        className="resize-none font-mono text-sm leading-relaxed"
                                    />

                                    {!isFinalized && (
                                        <div className="flex justify-between items-center pt-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleSaveDraft}
                                                disabled={isSaving || isFinalizing || !consolidatedText.trim()}
                                                className="gap-1.5"
                                            >
                                                {isSaving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                                Save Draft
                                            </Button>

                                            <Button
                                                size="sm"
                                                onClick={handleFinalize}
                                                disabled={isSaving || isFinalizing || !consolidatedText.trim()}
                                                className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                            >
                                                {isFinalizing ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                                Finalize Decision Part
                                            </Button>
                                        </div>
                                    )}

                                    {isFinalized && conversation.event_id && (
                                        <div className="pt-4 border-t flex justify-end">
                                            <Link href={`/pastoral/master-decision/${conversation.event_id}`}>
                                                <Button className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                                                    Go to Event Master Decision
                                                    <ArrowRight className="size-4" />
                                                </Button>
                                            </Link>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                )}
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
