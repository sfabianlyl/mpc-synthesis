import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Sparkles,
    Loader2,
    CheckCircle2,
    XCircle,
    Save,
    Printer,
    Layers,
    Globe,
    Shield,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface DecisionPartItem {
    id: number;
    national_text?: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
}

interface ConversationPartItem {
    id: number;
    title: string;
    description: string | null;
    status: string;
    national_decision_part: DecisionPartItem | null;
    decision_part?: {
        id: number;
        consolidated_text: string | null;
        is_submitted: boolean;
        submitted_at: string | null;
        submitted_by: string | null;
    } | null;
}

interface MasterDecisionData {
    id: number | null;
    master_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    submitted_by: string | null;
    generated_by: string | null;
    generated_at: string | null;
}

interface MasterDecisionPageProps {
    event: {
        id: number;
        title: string;
        start_date?: string;
        end_date?: string | null;
    };
    conversationsWithParts: ConversationPartItem[];
    masterDecision: MasterDecisionData | null;
}

export default function MasterDecisionPage({
    event,
    conversationsWithParts = [],
    masterDecision,
}: MasterDecisionPageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isGenerating, setIsGenerating] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [isFinalizing, setIsFinalizing] = useState(false);
    const [masterText, setMasterText] = useState(masterDecision?.master_text ?? '');

    useEffect(() => {
        if (masterDecision?.master_text) {
            setMasterText(masterDecision.master_text);
        }
    }, [masterDecision]);

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

    const isFinalized = Boolean(masterDecision?.is_submitted);

    // AI summary generation for master decision
    const handleGenerateMaster = () => {
        setIsGenerating(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/master-decision/${event.id}/generate-summary`,
            {},
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    setIsGenerating(false);
                    const respProps = page.props as any;
                    if (respProps.masterDecision?.master_text) {
                        setMasterText(respProps.masterDecision.master_text);
                    }
                    if (page.props.flash?.success) {
                        setStatusMessage({ type: 'success', text: page.props.flash.success });
                    }
                },
                onError: (errors: any) => {
                    setIsGenerating(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to generate Master Decision.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Save master decision as draft
    const handleSaveDraft = () => {
        setIsSaving(true);
        setStatusMessage(null);

        router.patch(
            `/pastoral/master-decision/${event.id}/save`,
            { master_text: masterText },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                    setStatusMessage({ type: 'success', text: 'Master Decision draft saved.' });
                },
                onError: (errors: any) => {
                    setIsSaving(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to save Master Decision.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };

    // Finalize master decision
    const handleFinalize = () => {
        if (!confirm('Are you sure you want to finalize the Master Pastoral Decision for this event?')) {
            return;
        }

        setIsFinalizing(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/master-decision/${event.id}/finalize`,
            { master_text: masterText },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsFinalizing(false);
                    window.location.href = `/pastoral/bishop-overview/${event.id}`;
                },
                onError: (errors: any) => {
                    setIsFinalizing(false);
                    const errorMsg = errors.error || Object.values(errors)[0] || 'Failed to finalize Master Decision.';
                    setStatusMessage({ type: 'error', text: errorMsg as string });
                },
            }
        );
    };


    return (
        <>
            <Head title={`Master Decision - ${event.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-[1600px] mx-auto">
                {/* ─── Header Section ─────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-start justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-3xl font-bold tracking-tight">Overall Pastoral Pathways</h1>
                            <Badge variant="outline" className="text-xs">
                                Step 3 of 3: Overall
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-base">
                            Event: <span className="font-semibold text-foreground">{event.title}</span>
                        </p>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Consolidate and finalize the Overall Pastoral Pathways
                        </p>
                    </div>

                    <div className="flex items-center gap-2 shrink-0">
                        {isFinalized ? (
                            <Badge variant="default" className="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1">
                                <CheckCircle2 className="h-4 w-4" />
                                Overall Pathway Finalized
                            </Badge>
                        ) : (
                            <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <Layers className="size-3" />
                                Conversations: {conversationsWithParts.filter(c => c.national_decision_part?.is_submitted).length}/{conversationsWithParts.length} pathways finalized
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

                {/* ─── Full Editor View (Always Accessible) ─────────── */}
                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                        {/* ─── Left Column: All Conversation Pathways ─ */}
                        <div className="lg:col-span-5 flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold flex items-center gap-2">
                                    <Layers className="size-5 text-indigo-600" />
                                    Conversation Pathways ({conversationsWithParts.length})
                                </h2>
                            </div>

                            <div className="space-y-4 max-h-[700px] overflow-y-auto pr-1">
                                {conversationsWithParts.length === 0 ? (
                                    <p className="text-sm text-muted-foreground italic">No conversations found for this event.</p>
                                ) : (
                                    conversationsWithParts.map((conv) => (
                                        <Card key={conv.id} className="border-sidebar-border/60 shadow-xs">
                                            <CardHeader className="py-2.5 px-3 bg-muted/20 border-b flex flex-row items-center justify-between">
                                                <CardTitle className="text-xs font-bold flex items-center gap-1.5 text-indigo-700 dark:text-indigo-400">
                                                    <Globe className="size-3.5" />
                                                    {conv.title}
                                                </CardTitle>
                                                {conv.national_decision_part?.is_submitted && (
                                                    <Badge variant="default" className="bg-emerald-600 text-[10px] px-1.5 py-0">
                                                        Finalized
                                                    </Badge>
                                                )}
                                            </CardHeader>
                                            <CardContent className="p-3 text-xs leading-relaxed max-h-[250px] overflow-y-auto font-mono whitespace-pre-wrap">
                                                {conv.national_decision_part?.national_text || (
                                                    <span className="text-muted-foreground italic">No pathway content yet.</span>
                                                )}
                                            </CardContent>
                                        </Card>
                                    ))
                                )}
                            </div>
                        </div>

                        {/* ─── Right Column: Master Decision Editor ───── */}
                        <div className="lg:col-span-7 flex flex-col gap-4">
                            <div className="flex items-center justify-between">
                                <h2 className="text-lg font-semibold flex items-center gap-2">
                                    <Shield className="size-5 text-indigo-600" />
                                    Overall Pastoral Pathways
                                </h2>

                                <Button
                                    onClick={handleGenerateMaster}
                                    disabled={isGenerating || isFinalized}
                                    size="sm"
                                    className="gap-1.5 bg-purple-600 hover:bg-purple-700 text-xs"
                                >
                                    {isGenerating ? <Loader2 className="size-3.5 animate-spin" /> : <Sparkles className="size-3.5" />}
                                    {isGenerating ? 'Synthesizing...' : 'AI Synthesize All Sessions'}
                                </Button>
                            </div>

                            <Card className={isFinalized ? 'border-emerald-500/40 bg-emerald-50/5' : ''}>
                                <CardHeader className="pb-3 bg-muted/15 border-b">
                                    <div className="flex items-center justify-between">
                                        <CardDescription className="text-xs">
                                            {masterDecision?.generated_by && (
                                                <span className="mr-2">
                                                    Synthesized via {masterDecision.generated_by}
                                                </span>
                                            )}
                                            {masterDecision?.submitted_by && (
                                                <span className="text-emerald-600 font-medium">
                                                    Finalized by {masterDecision.submitted_by}
                                                </span>
                                            )}
                                        </CardDescription>
                                        {isFinalized && (
                                            <Badge variant="default" className="bg-emerald-600 text-xs">
                                                Finalized Statement
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4 space-y-4">
                                    <Textarea
                                        value={masterText}
                                        onChange={(e) => setMasterText(e.target.value)}
                                        placeholder="The comprehensive event-level Master Pastoral Decision will appear here..."
                                        rows={20}
                                        disabled={isFinalized}
                                        className="resize-none font-mono text-sm leading-relaxed"
                                    />

                                    {!isFinalized && (
                                        <div className="flex justify-between items-center pt-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={handleSaveDraft}
                                                disabled={isSaving || isFinalizing || !masterText.trim()}
                                                className="gap-1.5"
                                            >
                                                {isSaving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                                Save Draft
                                            </Button>

                                            <Button
                                                size="sm"
                                                onClick={handleFinalize}
                                                disabled={isSaving || isFinalizing || !masterText.trim()}
                                                className="gap-1.5 bg-emerald-600 hover:bg-emerald-700"
                                            >
                                                {isFinalizing ? <Loader2 className="size-3.5 animate-spin" /> : <CheckCircle2 className="size-3.5" />}
                                                Finalize Overall Pathways
                                            </Button>
                                        </div>
                                    )}

                                    {isFinalized && (
                                        <div className="pt-4 border-t flex justify-between items-center">
                                            <p className="text-xs text-muted-foreground">
                                                Overall finalized and ready for the Bishop's presentation.
                                            </p>
                                            <Link href={`/pastoral/bishop-overview/${event.id}`}>
                                                <Button className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                                                    <Printer className="size-4" />
                                                    View Bishop's Overview
                                                </Button>
                                            </Link>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
            </div>
        </>
    );
}

MasterDecisionPage.layout = (page: React.ReactNode) => {
    const props = page as any;
    const event = props?.props?.event;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
                { title: `Master Decision: ${event?.title || 'Event'}`, href: `/pastoral/master-decision/${event?.id || ''}` },
            ]}
        >
            {page}
        </AppLayout>
    );
};
