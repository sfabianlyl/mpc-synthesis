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
    Building2,
    Globe,
    Layers,
} from 'lucide-react';
import { useState, useEffect, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface DraftItem {
    id: number;
    user_id: number;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    user?: { id: number; name: string; email: string } | null;
    diocese?: { id: number; name: string; code: string } | null;
}

interface SharedDecisionPartData {
    id: number | null;
    diocesan_text?: string | null;
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
    isUnlocked: boolean;
    progress: {
        submitted_count: number;
        total_required: number;
    };
    nationalDrafts?: DraftItem[];
    dioceseDrafts?: DraftItem[];
    dioceseDecisionPart?: SharedDecisionPartData | null;
    nationalDecisionPart?: SharedDecisionPartData | null;
}

export default function Collaborate({
    conversation,
    eventConversations = [],
    isUnlocked,
    progress,
    nationalDrafts = [],
    dioceseDrafts = [],
    dioceseDecisionPart,
    nationalDecisionPart,
}: CollaboratePageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isFinalizing, setIsFinalizing] = useState(false);

    const [nationalText, setNationalText] = useState(nationalDecisionPart?.national_text ?? '');
    const [diocesanText, setDiocesanText] = useState(dioceseDecisionPart?.diocesan_text ?? '');
    const [activeDraftTab, setActiveDraftTab] = useState<'both' | 'national' | 'diocese'>('both');
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

    // Sync state when props change
    useEffect(() => {
        if (nationalDecisionPart?.national_text !== undefined) {
            setNationalText(nationalDecisionPart.national_text ?? '');
        }
    }, [nationalDecisionPart]);

    useEffect(() => {
        if (dioceseDecisionPart?.diocesan_text !== undefined) {
            setDiocesanText(dioceseDecisionPart.diocesan_text ?? '');
        }
    }, [dioceseDecisionPart]);

    const isFinalized = Boolean(dioceseDecisionPart?.is_submitted && nationalDecisionPart?.is_submitted);

    // Group Diocese Drafts by Diocese
    const groupedDioceseDrafts = useMemo(() => {
        const groups: Record<string, DraftItem[]> = {};
        dioceseDrafts.forEach((draft) => {
            const dioceseName = draft.diocese?.name || 'Unassigned Diocese';
            if (!groups[dioceseName]) {
                groups[dioceseName] = [];
            }
            groups[dioceseName].push(draft);
        });
        return groups;
    }, [dioceseDrafts]);

    const uniqueDioceses = useMemo(() => Object.keys(groupedDioceseDrafts), [groupedDioceseDrafts]);

    // Trigger AI summary generation for both Decision Parts
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
                    if (respProps.dioceseDecisionPart?.diocesan_text) {
                        setDiocesanText(respProps.dioceseDecisionPart.diocesan_text);
                    }
                    setStatusMessage({ type: 'success', text: 'AI synthesized drafts for National and Diocesan levels successfully.' });
                },
                onError: (errors: any) => {
                    setIsGenerating(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to generate summary.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Save Shared Decision Parts as draft
    const handleSaveDraft = () => {
        setIsSaving(true);
        setStatusMessage(null);

        router.patch(
            `/pastoral/collaborate/${conversation.id}/save-part`,
            {
                national_text: nationalText,
                diocesan_text: diocesanText,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                    setStatusMessage({ type: 'success', text: 'Decision Parts drafts saved.' });
                },
                onError: (errors: any) => {
                    setIsSaving(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to save Decision Parts.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Finalize Shared Decision Parts
    const handleFinalize = () => {
        if (!confirm('Are you sure you want to finalize both National and Diocese Decision Parts for this session?')) {
            return;
        }

        setIsFinalizing(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/collaborate/${conversation.id}/finalize-part`,
            {
                national_text: nationalText,
                diocesan_text: diocesanText,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsFinalizing(false);
                },
                onError: (errors: any) => {
                    setIsFinalizing(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to finalize Decision Parts.';
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
                            <h1 className="text-3xl font-bold tracking-tight">Collaborative Decision Parts</h1>
                            <Badge variant="outline" className="text-xs">
                                Step 2 of 3: Shared Conversation Decision
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-base">
                            Session: <span className="font-semibold text-foreground">{conversation.title}</span>
                            {conversation.event?.title && ` · Event: ${conversation.event.title}`}
                        </p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Collaborate with other pastoral members to synthesize individual drafts into ONE shared National Decision Part and ONE shared Diocese Decision Part for this session.
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {isFinalized ? (
                            <Badge variant="default" className="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1">
                                <CheckCircle2 className="h-4 w-4" />
                                Decision Parts Finalized
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

                {/* ─── Gated Locked State ─────────────────────────── */}
                {!isUnlocked ? (
                    <Card className="border-dashed bg-muted/20">
                        <CardContent className="py-16 flex flex-col items-center justify-center text-center gap-4 max-w-xl mx-auto">
                            <div className="p-3 bg-amber-100 dark:bg-amber-950/40 rounded-full text-amber-600">
                                <Lock className="size-8" />
                            </div>
                            <div className="space-y-1">
                                <h3 className="text-lg font-semibold">Collaboration Locked for this Session</h3>
                                <p className="text-sm text-muted-foreground">
                                    All pastoral members must submit their individual decision drafts for <strong>{conversation.title}</strong> before the group can produce the shared Decision Parts.
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
                                {progress.submitted_count} of {progress.total_required} pastoral members submitted for this session
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
                            <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                <h2 className="text-lg font-semibold flex items-center gap-2">
                                    <Users className="size-5 text-indigo-600" />
                                    Submitted Member Drafts
                                </h2>

                                <div className="flex items-center gap-1.5">
                                    <div className="inline-flex rounded-md border p-0.5 bg-muted/40 text-xs">
                                        <button
                                            type="button"
                                            onClick={() => setActiveDraftTab('both')}
                                            className={`px-2.5 py-1 rounded font-medium transition-colors ${
                                                activeDraftTab === 'both' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            All Drafts
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setActiveDraftTab('national')}
                                            className={`px-2.5 py-1 rounded font-medium transition-colors ${
                                                activeDraftTab === 'national' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            National ({nationalDrafts.length})
                                        </button>
                                        <button
                                            type="button"
                                            onClick={() => setActiveDraftTab('diocese')}
                                            className={`px-2.5 py-1 rounded font-medium transition-colors ${
                                                activeDraftTab === 'diocese' ? 'bg-background shadow-xs text-foreground' : 'text-muted-foreground hover:text-foreground'
                                            }`}
                                        >
                                            Diocese ({dioceseDrafts.length})
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4 max-h-[880px] overflow-y-auto pr-1">
                                {/* ── National Level Drafts ── */}
                                {(activeDraftTab === 'both' || activeDraftTab === 'national') && (
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2 pt-1">
                                            <Globe className="size-4 text-indigo-600" />
                                            <h3 className="text-sm font-semibold text-foreground uppercase tracking-wider">
                                                National Level Drafts ({nationalDrafts.length})
                                            </h3>
                                        </div>

                                        {nationalDrafts.length === 0 ? (
                                            <p className="text-xs text-muted-foreground italic">No national drafts submitted.</p>
                                        ) : (
                                            nationalDrafts.map((draft) => (
                                                <Card key={`nat-${draft.id}`} className="border-sidebar-border/60 shadow-xs">
                                                    <CardHeader className="py-2.5 px-3 bg-muted/20 flex flex-row items-center justify-between">
                                                        <div className="flex items-center gap-2">
                                                            <Users className="size-3.5 text-muted-foreground" />
                                                            <span className="text-xs font-semibold">{draft.user?.name || 'Member'}</span>
                                                        </div>
                                                        {draft.diocese && (
                                                            <Badge variant="outline" className="text-[11px] px-1.5 py-0">
                                                                {draft.diocese.name}
                                                            </Badge>
                                                        )}
                                                    </CardHeader>
                                                    <CardContent className="p-3 text-xs leading-relaxed whitespace-pre-wrap font-sans text-foreground/90">
                                                        {draft.draft_text || <span className="text-muted-foreground italic">No content provided</span>}
                                                    </CardContent>
                                                </Card>
                                            ))
                                        )}
                                    </div>
                                )}

                                {/* ── Diocese Level Drafts (Grouped by Diocese) ── */}
                                {(activeDraftTab === 'both' || activeDraftTab === 'diocese') && (
                                    <div className="space-y-3 pt-2">
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Building2 className="size-4 text-violet-600" />
                                                <h3 className="text-sm font-semibold text-foreground uppercase tracking-wider">
                                                    Diocese Level Drafts (Grouped by Diocese)
                                                </h3>
                                            </div>

                                            {uniqueDioceses.length > 1 && (
                                                <select
                                                    value={selectedDioceseFilter}
                                                    onChange={(e) => setSelectedDioceseFilter(e.target.value)}
                                                    className="h-7 rounded-md border border-input bg-background px-2 text-[11px]"
                                                    aria-label="Filter diocese drafts"
                                                >
                                                    <option value="all">All Dioceses ({uniqueDioceses.length})</option>
                                                    {uniqueDioceses.map((d) => (
                                                        <option key={d} value={d}>
                                                            {d} ({groupedDioceseDrafts[d]?.length || 0})
                                                        </option>
                                                    ))}
                                                </select>
                                            )}
                                        </div>

                                        {Object.keys(groupedDioceseDrafts).length === 0 ? (
                                            <p className="text-xs text-muted-foreground italic">No diocese drafts submitted.</p>
                                        ) : (
                                            Object.entries(groupedDioceseDrafts)
                                                .filter(([dioceseName]) => selectedDioceseFilter === 'all' || selectedDioceseFilter === dioceseName)
                                                .map(([dioceseName, drafts]) => (
                                                    <Card key={dioceseName} className="border-sidebar-border/60 shadow-xs border-l-4 border-l-violet-500">
                                                        <CardHeader className="py-2 px-3 bg-muted/30">
                                                            <div className="flex items-center justify-between">
                                                                <CardTitle className="text-xs font-bold flex items-center gap-1.5 text-violet-700 dark:text-violet-400">
                                                                    <Building2 className="size-3.5" />
                                                                    {dioceseName}
                                                                </CardTitle>
                                                                <Badge variant="secondary" className="text-[10px]">
                                                                    {drafts.length} submission{drafts.length !== 1 ? 's' : ''}
                                                                </Badge>
                                                            </div>
                                                        </CardHeader>
                                                        <CardContent className="p-3 space-y-3">
                                                            {drafts.map((d) => (
                                                                <div key={`dioc-${d.id}`} className="space-y-1 text-xs border-b last:border-0 pb-2.5 last:pb-0">
                                                                    <div className="flex items-center justify-between text-[11px] font-semibold text-muted-foreground">
                                                                        <span>{d.user?.name || 'Pastoral Member'}</span>
                                                                        {d.submitted_at && (
                                                                            <span className="text-[10px]">{new Date(d.submitted_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                                                        )}
                                                                    </div>
                                                                    <p className="text-xs leading-relaxed whitespace-pre-wrap text-foreground/90 bg-muted/10 p-2 rounded border">
                                                                        {d.draft_text || <span className="text-muted-foreground italic">No content</span>}
                                                                    </p>
                                                                </div>
                                                            ))}
                                                        </CardContent>
                                                    </Card>
                                                ))
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* ─── Right Column: Shared Decision Parts Editor ── */}
                        <div className="lg:col-span-6 flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold flex items-center gap-2">
                                        <Shield className="size-5 text-indigo-600" />
                                        Shared Decision Parts (Session Output)
                                    </h2>
                                    <p className="text-xs text-muted-foreground">
                                        Exactly ONE shared National and Diocese Decision Part for this conversation.
                                    </p>
                                </div>

                                <Button
                                    onClick={handleGenerateSummary}
                                    disabled={isGenerating || isFinalized}
                                    size="sm"
                                    className="gap-1.5 bg-purple-600 hover:bg-purple-700 text-xs shrink-0"
                                >
                                    {isGenerating ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                                    {isGenerating ? 'Synthesizing...' : 'AI Synthesize Both'}
                                </Button>
                            </div>

                            {/* 1. National Decision Part Card */}
                            <Card className={isFinalized ? 'border-emerald-500/40 bg-emerald-50/5' : 'border-indigo-200 dark:border-indigo-900/50'}>
                                <CardHeader className="py-2.5 px-4 bg-muted/20 border-b flex flex-row items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Globe className="size-4 text-indigo-600" />
                                        <CardTitle className="text-sm font-semibold">Shared National Decision Part</CardTitle>
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

                            {/* 2. Diocese Decision Part Card */}
                            <Card className={isFinalized ? 'border-emerald-500/40 bg-emerald-50/5' : 'border-violet-200 dark:border-violet-900/50'}>
                                <CardHeader className="py-2.5 px-4 bg-muted/20 border-b flex flex-row items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <Building2 className="size-4 text-violet-600" />
                                        <CardTitle className="text-sm font-semibold">Shared Diocese Decision Part</CardTitle>
                                    </div>
                                    {dioceseDecisionPart?.is_submitted && (
                                        <Badge variant="default" className="bg-emerald-600 text-[10px] px-1.5 py-0">
                                            Finalized
                                        </Badge>
                                    )}
                                </CardHeader>
                                <CardContent className="p-3">
                                    <Textarea
                                        value={diocesanText}
                                        onChange={(e) => setDiocesanText(e.target.value)}
                                        placeholder="Enter the consolidated Diocese Decision Part for this session..."
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
                                        Save Drafts
                                    </Button>

                                    <Button
                                        size="sm"
                                        onClick={handleFinalize}
                                        disabled={isSaving || isFinalizing || (!nationalText.trim() && !diocesanText.trim())}
                                        className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                    >
                                        {isFinalizing ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                        Finalize Decision Parts
                                    </Button>
                                </div>
                            ) : (
                                conversation.event_id && (
                                    <div className="p-3 rounded-lg border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/20 flex items-center justify-between">
                                        <div className="flex items-center gap-2 text-xs text-emerald-700 dark:text-emerald-300">
                                            <CheckCircle2 className="size-4" />
                                            <span>Both Decision Parts for this session have been finalized.</span>
                                        </div>
                                        <Link href={`/pastoral/master-decision/${conversation.event_id}`}>
                                            <Button size="sm" className="gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-xs">
                                                Go to Event Master Decision
                                                <ArrowRight className="size-3.5" />
                                            </Button>
                                        </Link>
                                    </div>
                                )
                            )}
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
