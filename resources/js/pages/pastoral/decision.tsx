import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle, Loader2, FileText, Shield, Sparkles } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface EventData {
    id: number;
    title: string;
    start_date: string;
    end_date: string | null;
}

interface DraftData {
    id: number | null;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
}

interface DecisionPageProps {
    event: EventData;
    nationalDraft: DraftData | null;
    dioceseDraft: DraftData | null;
    userSubmitted: boolean;
}

export default function Decision({ event, nationalDraft, dioceseDraft, userSubmitted }: DecisionPageProps) {
    const { flash } = usePage().props as any;
    const [nationalText, setNationalText] = useState(nationalDraft?.draft_text ?? '');
    const [dioceseText, setDioceseText] = useState(dioceseDraft?.draft_text ?? '');
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);

    // Load flash messages
    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }

        if (flash?.errors?.national || flash?.errors?.diocese) {
            const key = flash.errors.national ? 'national' : 'diocese';
            setStatusMessage({ type: 'error', text: flash.errors[key] });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Pre-fill from existing drafts on mount
    useEffect(() => {
        setNationalText(nationalDraft?.draft_text ?? '');
        setDioceseText(dioceseDraft?.draft_text ?? '');
    }, [nationalDraft, dioceseDraft]);

    const handleSubmit = () => {
        setIsSubmitting(true);
        setStatusMessage(null);

        router.post(
            `/pastoral/decision/${event.id}`,
            {
                national_draft_text: nationalText || null,
                diocese_draft_text: dioceseText || null,
                submit: true,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubmitting(false);
                    setStatusMessage({ type: 'success', text: 'Decision drafts submitted successfully.' });
                },
                onError: (errors) => {
                    setIsSubmitting(false);
                    if (errors.national_draft_text || errors.diocese_draft_text) {
                        setStatusMessage({ type: 'error', text: 'Validation failed. Please check your inputs.' });
                    } else {
                        setStatusMessage({ type: 'error', text: 'Failed to submit drafts. Please try again.' });
                    }
                },
            }
        );
    };

    const nationalCharCount = nationalText.length;
    const dioceseCharCount = dioceseText.length;

    return (
        <>
            <Head title={`Begin Decision Making - ${event.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-5xl mx-auto">
                {/* ─── Header Section ─────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Begin Decision Making</h1>
                        <p className="text-muted-foreground mt-1">
                            Event: {event.title}
                        </p>
                        <p className="text-sm text-muted-foreground mt-0.5">
                            {new Date(event.start_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}
                            {' - '}
                            {event.end_date
                                ? new Date(event.end_date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })
                                : 'Ongoing'}
                        </p>
                    </div>

                    {/* Submitted Badge */}
                    {userSubmitted && (
                        <Badge variant="default" className="bg-emerald-600 hover:bg-emerald-700 flex items-center gap-1 self-start">
                            <CheckCircle2 className="h-4 w-4" />
                            Submitted
                        </Badge>
                    )}
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
                <Card className={userSubmitted ? 'opacity-60' : ''}>
                    <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Shield className="size-5 text-primary" />
                            National Level Decision Draft
                        </CardTitle>
                        <CardDescription>
                            Provide your decision draft at the national level. This will be consolidated with other members' inputs.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6 space-y-3">
                        {nationalDraft?.is_submitted && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                Submitted at {new Date(nationalDraft.submitted_at!).toLocaleString()}
                            </div>
                        )}
                        <textarea
                            value={nationalText}
                            onChange={(e) => setNationalText(e.target.value)}
                            placeholder="Enter your national level decision draft..."
                            rows={10}
                            disabled={userSubmitted}
                            className="w-full min-h-[60px] rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <div className="flex justify-end">
                            <span className="text-xs text-muted-foreground">
                                {nationalCharCount} character{nationalCharCount !== 1 ? 's' : ''}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* ─── Diocese Level Decision Draft ───────────────── */}
                <Card className={userSubmitted ? 'opacity-60' : ''}>
                    <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Shield className="size-5 text-secondary" />
                            Diocese Level Decision Draft
                        </CardTitle>
                        <CardDescription>
                            Provide your decision draft at the diocese level. This will be consolidated with other members' inputs.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6 space-y-3">
                        {dioceseDraft?.is_submitted && (
                            <div className="flex items-center gap-2 text-sm text-muted-foreground mb-2">
                                <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                                Submitted at {new Date(dioceseDraft.submitted_at!).toLocaleString()}
                            </div>
                        )}
                        <textarea
                            value={dioceseText}
                            onChange={(e) => setDioceseText(e.target.value)}
                            placeholder="Enter your diocese level decision draft..."
                            rows={10}
                            disabled={userSubmitted}
                            className="w-full min-h-[60px] rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                        />
                        <div className="flex justify-end">
                            <span className="text-xs text-muted-foreground">
                                {dioceseCharCount} character{dioceseCharCount !== 1 ? 's' : ''}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                {/* ─── Action Bar ───────────────────────────── */}
                <div className="flex flex-col sm:flex-row gap-3 justify-between pt-4 border-t">
                    <div className="flex items-center gap-3">
                        <a href="/pastoral/dashboard">
                            <Button variant="outline" className="flex items-center gap-2">
                                <FileText className="size-4" /> Back to Dashboard
                            </Button>
                        </a>
                    </div>
                    {!userSubmitted && (
                        <Button
                            onClick={handleSubmit}
                            disabled={isSubmitting || (!nationalText.trim() && !dioceseText.trim())}
                            className="flex items-center gap-2"
                        >
                            {isSubmitting && <Loader2 className="size-4 animate-spin" />}
                            {!isSubmitting && <Sparkles className="size-4" />}
                            {isSubmitting ? 'Submitting...' : 'Submit Both Drafts'}
                        </Button>
                    )}
                    {userSubmitted && (
                        <Badge variant="outline" className="flex items-center gap-1 text-emerald-600 border-emerald-200 dark:border-emerald-800">
                            <CheckCircle2 className="h-4 w-4" />
                            Your drafts have been submitted
                        </Badge>
                    )}
                </div>
            </div>
        </>
    );
}

Decision.layout = (page: React.ReactNode) => {
    const props = page as any;

    if (!props?.props?.event) {
        return <>{page}</>;
    }

    return (
        <AppLayout breadcrumbs={[
            { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
            { title: 'Begin Decision Making', href: `/pastoral/decision/${props.props.event.id}` },
        ]}>
            {page}
        </AppLayout>
    );
};
