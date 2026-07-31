import { Head, router, usePage } from '@inertiajs/react';
import { Sparkles, Loader2, CheckCircle2, XCircle, FileText, Users } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

interface CollaboratePageProps {
    event: {
        id: number;
        title: string;
        start_date: string;
        end_date: string | null;
    };
    nationalDrafts: Array<{
        id: number;
        user_id: number;
        draft_text: string | null;
        is_submitted: boolean;
        submitted_at: string | null;
        created_at: string;
        user?: { id: number; name: string; email: string };
        diocese?: { id: number; name: string; code: string };
    }>;
    masterDecisionSummary: {
        id: number | null;
        master_text: string | null;
        is_editable: boolean;
        generated_by: string | null;
        generated_at: string | null;
    } | null;
    dioceseDrafts?: Array<{
        id: number;
        user_id: number;
        draft_text: string | null;
        is_submitted: boolean;
        submitted_at: string | null;
        created_at: string;
        user?: { id: number; name: string; email: string };
        diocese?: { id: number; name: string; code: string };
    }>;
}

export default function Collaborate({
    event,
    nationalDrafts,
    masterDecisionSummary,
    dioceseDrafts = [],
}: CollaboratePageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isGeneratingSummary, setIsGeneratingSummary] = useState(false);
    const [isSavingMaster, setIsSavingMaster] = useState(false);
    const [masterText, setMasterText] = useState(masterDecisionSummary?.master_text ?? '');

    // Handle flash messages via useEffect
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

    // Sync masterText when masterDecisionSummary changes
    useEffect(() => {
        if (masterDecisionSummary?.master_text) {
            setMasterText(masterDecisionSummary.master_text);
        }
    }, [masterDecisionSummary]);

    // Group diocese drafts by diocese
    const groupDioceseDrafts = (drafts: typeof dioceseDrafts) => {
        const groups: Record<string, typeof dioceseDrafts> = {};
        drafts.forEach((draft) => {
            const dioceseKey = draft.diocese?.name || 'No Diocese';
            if (!groups[dioceseKey]) {
                groups[dioceseKey] = [];
            }
            groups[dioceseKey].push(draft);
        });
        return groups;
    };

    const dioceseGroups = groupDioceseDrafts(dioceseDrafts);

    // Generate decision summary via LLM
    const handleGenerateSummary = () => {
        setIsGeneratingSummary(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/collaborate/${event.id}/generate-summary`,
            {},
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    setIsGeneratingSummary(false);
                    if (page.props.flash?.success) {
                        setStatusMessage({ type: 'success', text: page.props.flash.success });
                    }
                    // Reload the page to get updated masterDecisionSummary
                    window.location.reload();
                },
                onError: (errors: any) => {
                    setIsGeneratingSummary(false);
                    const errorObj = errors as Record<string, string>;
                    const errorMsg = errors.error || Object.values(errorObj).find((v) => v) || 'Failed to generate summary.';
                    setStatusMessage({ type: 'error', text: errorMsg });
                },
            }
        );
    };

    // Save master decision text
    const handleSaveMasterText = () => {
        setIsSavingMaster(true);
        setStatusMessage(null);

        router.patch(
            `/pastoral/collaborate/${event.id}/save-master-text`,
            { master_text: masterText },
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    setIsSavingMaster(false);
                    if (page.props.flash?.success) {
                        setStatusMessage({ type: 'success', text: page.props.flash.success });
                    }
                },
                onError: (errors: any) => {
                    setIsSavingMaster(false);
                    const errorObj = errors as Record<string, string>;
                    const errorMsg = errors.error || Object.values(errorObj).find((v) => v) || 'Failed to save master text.';
                    setStatusMessage({ type: 'error', text: errorMsg });
                },
            }
        );
    };

    return (
        <>
            <Head title={`Collaborative Decision - ${event?.title || 'Event'}`} />
            <div className="p-8 flex flex-col gap-6 max-w-[1920px] mx-auto">
                {/* ─── Header Section ─────────────────────────────── */}
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Collaborative Decision</h1>
                    <p className="text-muted-foreground mt-1">
                        Event: {event?.title}
                    </p>
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

                {/* ─── Section 1: National Level Decision Drafts ─── */}
                <div>
                    <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        National Level Decision Drafts
                    </h2>
                    {nationalDrafts.length > 0 ? (
                        <div className="grid gap-4 md:grid-cols-2">
                            {nationalDrafts.map((draft) => (
                                <Card key={draft.id} className="border-sidebar-border/50">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="text-base flex items-center gap-2">
                                                <Users className="h-4 w-4" />
                                                {draft.user?.name || 'Unknown'}
                                            </CardTitle>
                                            <Badge variant="default" className="text-xs">
                                                Submitted
                                            </Badge>
                                        </div>
                                        <CardDescription className="flex items-center gap-2">
                                            {draft.diocese ? (
                                                <>
                                                    Diocese: {draft.diocese.name}
                                                    {draft.diocese.code && (
                                                        <Badge variant="secondary" className="text-xs ml-1">
                                                            {draft.diocese.code}
                                                        </Badge>
                                                    )}
                                                </>
                                            ) : (
                                                'No diocese assigned'
                                            )}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <Separator className="mb-3" />
                                        <div className="whitespace-pre-wrap text-sm text-muted-foreground">
                                            {draft.draft_text || 'No content provided.'}
                                        </div>
                                        {draft.submitted_at && (
                                            <p className="text-xs text-muted-foreground mt-3">
                                                Submitted: {new Date(draft.submitted_at).toLocaleString()}
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Card className="border-dashed bg-muted/20">
                            <CardContent className="pt-6 text-center text-muted-foreground text-sm">
                                <FileText className="h-8 w-8 mx-auto text-muted-foreground/30 mb-2" />
                                <p>No national decision drafts have been submitted yet.</p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* ─── Section 2: Generate Decision Summary ─────── */}
                <div className="flex justify-center">
                    <Button
                        onClick={handleGenerateSummary}
                        disabled={isGeneratingSummary || nationalDrafts.length === 0}
                        className="flex items-center gap-2 bg-purple-600 hover:bg-purple-700"
                        size="lg"
                    >
                        {isGeneratingSummary ? (
                            <Loader2 className="size-5 animate-spin" />
                        ) : (
                            <Sparkles className="size-5" />
                        )}
                        {isGeneratingSummary ? 'Generating...' : 'Generate Decision Summary'}
                    </Button>
                </div>

                {/* ─── Section 3: Master Decision (Editable) ────── */}
                <div>
                    <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
                        <FileText className="h-5 w-5" />
                        Master Decision
                        {masterDecisionSummary?.generated_by && (
                            <Badge variant="outline" className="text-xs ml-2">
                                Generated by {masterDecisionSummary.generated_by}
                            </Badge>
                        )}
                    </h2>
                    {(masterDecisionSummary?.master_text || masterText) ? (
                        <Card className="border-sidebar-border/50">
                            <CardHeader className="pb-3">
                                <CardDescription className="text-xs">
                                    {masterDecisionSummary?.generated_at && (
                                        <>
                                            Generated: {new Date(masterDecisionSummary.generated_at).toLocaleString()}
                                            {' · '}
                                        </>
                                    )}
                                    {masterDecisionSummary?.is_editable ? (
                                        <span className="text-emerald-600 dark:text-emerald-400">Editable</span>
                                    ) : (
                                        <span className="text-muted-foreground">Read-only</span>
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Textarea
                                    value={masterText}
                                    onChange={(e) => setMasterText(e.target.value)}
                                    disabled={!masterDecisionSummary?.is_editable}
                                    rows={12}
                                    className="resize-none"
                                    placeholder="Master decision text will appear here..."
                                />
                                {masterDecisionSummary?.is_editable && (
                                    <div className="flex justify-end mt-4">
                                        <Button
                                            onClick={handleSaveMasterText}
                                            disabled={isSavingMaster || !masterText.trim()}
                                            className="flex items-center gap-2"
                                        >
                                            {isSavingMaster ? (
                                                <Loader2 className="size-4 animate-spin" />
                                            ) : (
                                                <CheckCircle2 className="size-4" />
                                            )}
                                            Save Master Decision
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    ) : (
                        <Card className="border-dashed bg-muted/20">
                            <CardContent className="pt-6 text-center text-muted-foreground text-sm">
                                <Sparkles className="h-8 w-8 mx-auto text-muted-foreground/30 mb-2" />
                                <p>No master decision summary has been generated yet.</p>
                                <p className="text-xs mt-1">Click "Generate Decision Summary" above to create one.</p>
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* ─── Section 4: Diocese Level Decision Drafts ── */}
                <div>
                    <h2 className="text-xl font-semibold mb-4 flex items-center gap-2">
                        <Users className="h-5 w-5" />
                        Diocese Level Decision Drafts
                    </h2>
                    {Object.keys(dioceseGroups).length > 0 ? (
                        <div className="space-y-6">
                            {Object.entries(dioceseGroups).map(([dioceseName, drafts]) => (
                                <div key={dioceseName}>
                                    <h3 className="text-lg font-medium mb-3 flex items-center gap-2">
                                        <Badge variant="secondary">{dioceseName}</Badge>
                                        <span className="text-sm text-muted-foreground">({drafts.length} draft{drafts.length > 1 ? 's' : ''})</span>
                                    </h3>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        {drafts.map((draft) => (
                                            <Card key={draft.id} className="border-sidebar-border/50 opacity-75">
                                                <CardHeader className="pb-3">
                                                    <div className="flex items-center justify-between">
                                                        <CardTitle className="text-base flex items-center gap-2">
                                                            <Users className="h-4 w-4" />
                                                            {draft.user?.name || 'Unknown'}
                                                        </CardTitle>
                                                        <Badge variant="secondary" className="text-xs">
                                                            Read-only
                                                        </Badge>
                                                    </div>
                                                </CardHeader>
                                                <CardContent>
                                                    <Separator className="mb-3" />
                                                    <div className="whitespace-pre-wrap text-sm text-muted-foreground">
                                                        {draft.draft_text || 'No content provided.'}
                                                    </div>
                                                    {draft.submitted_at && (
                                                        <p className="text-xs text-muted-foreground mt-3">
                                                            Submitted: {new Date(draft.submitted_at).toLocaleString()}
                                                        </p>
                                                    )}
                                                </CardContent>
                                            </Card>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <Card className="border-dashed bg-muted/20">
                            <CardContent className="pt-6 text-center text-muted-foreground text-sm">
                                <Users className="h-8 w-8 mx-auto text-muted-foreground/30 mb-2" />
                                <p>No diocese decision drafts have been submitted yet.</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

Collaborate.layout = (page: React.ReactNode) => {
    const props = page as any;

    if (!props?.props?.event) {
        return <>{page}</>;
    }

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
                { title: 'Collaborative Decision', href: `/pastoral/collaborate/${props.props.event.id}` },
            ]}
        >
            {page}
        </AppLayout>
    );
};
