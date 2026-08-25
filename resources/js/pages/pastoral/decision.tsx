import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    XCircle,
    Loader2,
    FileText,
    Shield,
    Sparkles,
    Users,
    ChevronRight,
    ArrowRight,
    Save,
} from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface ConversationData {
    id: number;
    title: string;
    description: string | null;
    status: string;
    event_id: number | null;
    event?: {
        id: number;
        title: string;
        start_date: string;
        end_date: string | null;
    } | null;
}

interface IndividualDraftItem {
    id: number | null;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
}

interface MemberProgress {
    id: number;
    name: string;
    diocese: string | null;
    is_submitted: boolean;
}

interface DecisionPageProps {
    conversation: ConversationData;
    eventConversations: Array<{ id: number; title: string; event_id: number | null }>;
    dioceseDraft?: IndividualDraftItem | null;
    nationalDraft?: IndividualDraftItem | null;
    draft?: {
        national_text?: string | null;
        diocesan_text?: string | null;
        is_submitted?: boolean;
        submitted_at?: string | null;
    } | null;
    submissionProgress: {
        total: number;
        submitted_count: number;
        members: MemberProgress[];
    };
}

export default function Decision({
    conversation,
    eventConversations = [],
    dioceseDraft,
    nationalDraft,
    draft,
    submissionProgress,
}: DecisionPageProps) {
    const { flash } = usePage().props as any;
    const initialNationalText = nationalDraft?.draft_text ?? draft?.national_text ?? '';
    const initialDiocesanText = dioceseDraft?.draft_text ?? draft?.diocesan_text ?? '';

    const [nationalText, setNationalText] = useState(initialNationalText);
    const [diocesanText, setDiocesanText] = useState(initialDiocesanText);
    const [isSaving, setIsSaving] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    // Sync state when draft props change
    useEffect(() => {
        setNationalText(nationalDraft?.draft_text ?? draft?.national_text ?? '');
        setDiocesanText(dioceseDraft?.draft_text ?? draft?.diocesan_text ?? '');
    }, [nationalDraft, dioceseDraft, draft]);

    // Handle flash messages
    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }
        if (flash?.errors && Object.keys(flash.errors).length > 0) {
            const firstError = Object.values(flash.errors)[0] as string;
            setStatusMessage({ type: 'error', text: firstError || 'An error occurred.' });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    const isSubmitted = Boolean(dioceseDraft?.is_submitted || nationalDraft?.is_submitted || draft?.is_submitted);
    const submittedAt = nationalDraft?.submitted_at || dioceseDraft?.submitted_at || draft?.submitted_at;

    const handleSaveDraft = (submitFinal: boolean = false) => {
        if (submitFinal) {
            setIsSubmitting(true);
        } else {
            setIsSaving(true);
        }
        setStatusMessage(null);

        router.post(
            `/pastoral/decision/${conversation.id}`,
            {
                diocese_draft_text: diocesanText || null,
                national_draft_text: nationalText || null,
                is_submitted: submitFinal,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSaving(false);
                    setIsSubmitting(false);
                },
                onError: (errors) => {
                    setIsSaving(false);
                    setIsSubmitting(false);
                    const errorMsg = Object.values(errors)[0] as string || 'Failed to save draft.';
                    setStatusMessage({ type: 'error', text: errorMsg });
                },
            }
        );
    };

    const nationalCharCount = nationalText.length;
    const diocesanCharCount = diocesanText.length;

    return (
        <>
            <Head title={`Decision Draft - ${conversation.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-5xl mx-auto">
                {/* ─── Top Session Stepper / Switcher ───────────── */}
                {eventConversations.length > 1 && (
                    <div className="flex items-center gap-2 overflow-x-auto pb-2 border-b">
                        <span className="text-xs font-semibold uppercase text-muted-foreground mr-2 shrink-0">
                            Sessions:
                        </span>
                        {eventConversations.map((conv) => {
                            const isCurrent = conv.id === conversation.id;
                            return (
                                <Link key={conv.id} href={`/pastoral/decision/${conv.id}`}>
                                    <Button
                                        variant={isCurrent ? 'default' : 'outline'}
                                        size="sm"
                                        className={`gap-1.5 text-xs ${
                                            isCurrent ? 'bg-indigo-600 hover:bg-indigo-700' : ''
                                        }`}
                                    >
                                        <FileText className="size-3.5" />
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
                            <h1 className="text-3xl font-bold tracking-tight">Pastoral Decision Draft</h1>
                            <Badge variant="outline" className="text-xs">
                                Step 1 of 3: Individual Draft
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1 text-base">
                            Session: <span className="font-semibold text-foreground">{conversation.title}</span>
                            {conversation.event?.title && ` · Event: ${conversation.event.title}`}
                        </p>
                        {conversation.description && (
                            <p className="text-sm text-muted-foreground mt-0.5 max-w-2xl">
                                {conversation.description}
                            </p>
                        )}
                    </div>

                    {/* Progress Badge */}
                    <div className="flex flex-col sm:items-end gap-1.5 shrink-0">
                        {isSubmitted ? (
                            <Badge variant="default" className="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1">
                                <CheckCircle2 className="h-4 w-4" />
                                Your Draft is Submitted
                            </Badge>
                        ) : (
                            <Badge variant="outline" className="text-amber-600 border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/20">
                                Draft in Progress
                            </Badge>
                        )}

                        <div className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <Users className="size-3.5" />
                            <span>
                                Team Progress: {submissionProgress?.submitted_count ?? 0} / {submissionProgress?.total ?? 0} submitted
                            </span>
                        </div>
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

                {/* ─── National Level Decision Draft ──────────────── */}
                <Card className={isSubmitted ? 'border-emerald-500/30' : ''}>
                    <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Shield className="size-5 text-indigo-600" />
                            National Level Reflection & Proposals
                        </CardTitle>
                        <CardDescription>
                            Provide your strategic discernment and proposals for the national church for this session ({conversation.title}).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6 space-y-3">
                        <textarea
                            value={nationalText}
                            onChange={(e) => setNationalText(e.target.value)}
                            placeholder="Enter your national-level pastoral proposals and key directions..."
                            rows={8}
                            disabled={isSubmitted}
                            className="w-full min-h-[140px] rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-75"
                        />
                        <div className="flex justify-end">
                            <span className="text-xs text-muted-foreground">
                                {nationalCharCount} character{nationalCharCount !== 1 ? 's' : ''}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* ─── Diocese Level Decision Draft ───────────────── */}
                <Card className={isSubmitted ? 'border-emerald-500/30' : ''}>
                    <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Shield className="size-5 text-violet-600" />
                            Diocesan Level Implementation & Context
                        </CardTitle>
                        <CardDescription>
                            Provide specific pastoral needs, challenges, and implementation perspectives for your diocese.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6 space-y-3">
                        <textarea
                            value={diocesanText}
                            onChange={(e) => setDiocesanText(e.target.value)}
                            placeholder="Enter your diocesan-level proposals and pastoral considerations..."
                            rows={8}
                            disabled={isSubmitted}
                            className="w-full min-h-[140px] rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-75"
                        />
                        <div className="flex justify-end">
                            <span className="text-xs text-muted-foreground">
                                {diocesanCharCount} character{diocesanCharCount !== 1 ? 's' : ''}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* ─── Submission Timestamp ───────────────────────── */}
                {submittedAt && (
                    <div className="text-xs text-muted-foreground flex items-center gap-1.5">
                        <CheckCircle2 className="size-3.5 text-emerald-600" />
                        Submitted on {new Date(submittedAt).toLocaleString()}
                    </div>
                )}

                {/* ─── Action Bar ─────────────────────────────────── */}
                <div className="flex flex-col sm:flex-row gap-3 justify-between pt-4 border-t items-center">
                    <div className="flex items-center gap-2">
                        <Link href="/pastoral/dashboard">
                            <Button variant="outline" size="sm" className="gap-2">
                                <FileText className="size-4" /> Back to Dashboard
                            </Button>
                        </Link>
                    </div>

                    <div className="flex items-center gap-2">
                        {!isSubmitted ? (
                            <>
                                <Button
                                    variant="outline"
                                    onClick={() => handleSaveDraft(false)}
                                    disabled={isSaving || isSubmitting}
                                    className="gap-2"
                                >
                                    {isSaving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                    Save Draft
                                </Button>
                                <Button
                                    onClick={() => handleSaveDraft(true)}
                                    disabled={isSaving || isSubmitting || (!nationalText.trim() && !diocesanText.trim())}
                                    className="gap-2 bg-indigo-600 hover:bg-indigo-700"
                                >
                                    {isSubmitting ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                                    Submit Draft
                                </Button>
                            </>
                        ) : (
                            <Link href={`/pastoral/collaborate/${conversation.id}`}>
                                <Button className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                                    Proceed to Collaboration
                                    <ArrowRight className="size-4" />
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

Decision.layout = (page: React.ReactNode) => {
    const props = page as any;
    const conversation = props?.props?.conversation;

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
                { title: `Decision Draft: ${conversation?.title || 'Session'}`, href: `/pastoral/decision/${conversation?.id || ''}` },
            ]}
        >
            {page}
        </AppLayout>
    );
};
