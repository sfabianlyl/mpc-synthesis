import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle, Loader2, FileText, Users, Bot, MessageSquare, Sparkles } from 'lucide-react';
import { useState, useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

// ─── Type Definitions ─────────────────────────────────────────────

interface SynthesisSubmission {
    id: number;
    submission_number: number;
    user: { id: number; name: string; email: string };
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    other_text: string | null;
    ai_convergence_text: string | null;
    ai_consideration_text: string | null;
    ai_proposals_text: string | null;
    ai_other_text: string | null;
    is_llm_assisted: boolean;
    status: 'draft' | 'submitted';
    submitted_at: string | null;
    custom_queries: Array<{
        id: number;
        prompt: string;
        response: string[];
        provider: string | null;
        created_at: string;
    }>;
}

interface MasterSynthesisReport {
    id: number | null;
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    other_text: string | null;
    llm_generated: boolean;
    status: string;
}

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
}

interface PageProps {
    conversation: any;
    submissions: SynthesisSubmission[];
    masterSynthesis: MasterSynthesisReport | null;
    groups: any[];
    responses: any[];
    consensusSubmissions: Record<number, any[]>;
    currentUser: User;
    success?: string;
    errors?: Record<string, string>;
}

// ─── Category Configuration ───────────────────────────────────────

const CATEGORIES = [
    { key: 'convergence', label: 'Convergence', icon: CheckCircle2 },
    { key: 'consideration', label: 'Matters for Consideration', icon: FileText },
    { key: 'proposals', label: 'Proposals', icon: Sparkles },
    { key: 'other', label: 'Others', icon: MessageSquare },
];

// ─── Helper Functions ─────────────────────────────────────────────

const renderAiText = (text: string | null): React.ReactNode => {
    if (!text || !text.trim()) {
return null;
}

    const bullets = text.split('\n').filter((line) => line.trim());

    return (
        <ul className="space-y-1">
            {bullets.map((bullet, i) => (
                <li key={i} className="flex items-start gap-2">
                    <span className="text-primary mt-0.5 flex-shrink-0">•</span>
                    <span className="text-sm">{bullet.replace(/^-+\s*/, '')}</span>
                </li>
            ))}
        </ul>
    );
};

const getInitials = (name: string): string => {
    return name
        .split(' ')
        .map((n) => n[0])
        .join('')
        .slice(0, 2)
        .toUpperCase();
};

// ─── Component ────────────────────────────────────────────────────

export default function ReviewPage({
    conversation,
    submissions,
    masterSynthesis,
    groups,
    responses,
    consensusSubmissions = {},
    currentUser,
    success: initialSuccess,
    errors: initialErrors,
}: PageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isAiGenerating, setIsAiGenerating] = useState(false);
    const [onlineUsers, setOnlineUsers] = useState<any[]>([]);
    const [masterSynthesisLocal, setMasterSynthesisLocal] = useState<MasterSynthesisReport | null>(masterSynthesis);
    const [submissionsLocal, setSubmissionsLocal] = useState<SynthesisSubmission[]>(submissions);
    const [toastMessage, setToastMessage] = useState<string | null>(null);
    // Per-category input state for the user's own synthesis submission
    const [convergenceInput, setConvergenceInput] = useState('');
    const [mattersInput, setMattersInput] = useState('');
    const [proposalsInput, setProposalsInput] = useState('');
    const [othersInput, setOthersInput] = useState('');

    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);

            return () => clearTimeout(timer);
        }

        if (flash?.errors?.submission || flash?.errors?.ai_synthesis) {
            const key = flash.errors.submission ? 'submission' : 'ai_synthesis';
            setStatusMessage({ type: 'error', text: flash.errors[key] });
            const timer = setTimeout(() => setStatusMessage(null), 5000);

            return () => clearTimeout(timer);
        }
    }, [flash]);

    // Initialize user's draft inputs on mount
    useEffect(() => {
        const userOwnDraft = submissionsLocal.find(
            (s) => s.user.id === currentUser.id && s.status === 'draft'
        );

        if (userOwnDraft) {
            setConvergenceInput(userOwnDraft.convergence_text ?? '');
            setMattersInput(userOwnDraft.consideration_text ?? '');
            setProposalsInput(userOwnDraft.proposals_text ?? '');
            setOthersInput(userOwnDraft.other_text ?? '');
        }
    }, [submissionsLocal, currentUser.id]);

    // Real-time WebSocket integration with Pusher/Echo
    useEffect(() => {
         
        const Echo = (window as any).Echo;

        if (!Echo) {
return;
}

        const channelName = `conversation.${conversation.id}`;

        const presenceChannel = Echo.join(channelName)
            .here((users: any[]) => {
                setOnlineUsers(users);
            })
            .joining((user: any) => {
                setOnlineUsers((prev) => [...prev, user]);
                setToastMessage(`${user.name} joined the review`);
                setTimeout(() => setToastMessage(null), 3000);
            })
            .leaving((user: any) => {
                setOnlineUsers((prev) => prev.filter((u: any) => u.id !== user.id));
            })
            .listen('SynthesisSubmitted', (e: any) => {
                if (e.submission) {
                    setSubmissionsLocal((prev) => [...prev, e.submission]);
                    setToastMessage(`${e.submission.user.name} submitted their synthesis`);
                    setTimeout(() => setToastMessage(null), 5000);
                }
            })
            .listen('MasterSynthesisUpdated', (e: any) => {
                if (e.masterSynthesis) {
                    setMasterSynthesisLocal(e.masterSynthesis);
                    setToastMessage('Master synthesis was updated');
                    setTimeout(() => setToastMessage(null), 5000);
                }
            });

        return () => {
             
            (window as any).Echo.leave(channelName);
        };
    }, [conversation.id]);

    // Check if current user has a submitted submission
    const hasSubmitted = submissionsLocal.some(
        (s) => s.user.id === currentUser.id && s.status === 'submitted'
    );

    // Check if current user has a draft submission
    const userDraft = submissionsLocal.find(
        (s) => s.user.id === currentUser.id && s.status === 'draft'
    );

    // Group submissions by category
    const getSubmissionsByCategory = (category: string) => {
        return submissionsLocal.map((s) => ({
            ...s,
            text: (s as Record<string, unknown>)[`${category}_text`] as string | null,
            aiText: (s as Record<string, unknown>)[`ai_${category}_text`] as string | null,
        }));
    };

    // Submit synthesis handler — sends the user's own draft input values (not master synthesis)
    const handleSubmitSynthesis = () => {
        setIsSubmitting(true);
        router.post(
            `/synthesis/submit-synthesis/${conversation.id}`,
            {
                convergence_text: convergenceInput,
                consideration_text: mattersInput,
                proposals_text: proposalsInput,
                other_text: othersInput,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsSubmitting(false);
                },
                onError: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    // Generate AI-assisted synthesis handler
    const handleGenerateAiSynthesis = () => {
        setIsAiGenerating(true);
        router.post(
            `/synthesis/ai-synthesis/${conversation.id}`,
            {
                selected_group_ids: groups.map((g) => g.id),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAiGenerating(false);
                },
                onError: () => {
                    setIsAiGenerating(false);
                },
            }
        );
    };

    // Update master synthesis handler — sends all category fields for complete update
    const handleUpdateMaster = (category: string, text: string) => {
        if (!masterSynthesisLocal?.id && currentUser.role !== 'admin' && currentUser.role !== 'drafter') {
return;
}

        // Build complete update data with all master synthesis category values
        const updateData: Record<string, string | null> = {
            convergence_text: masterSynthesisLocal?.convergence_text ?? '',
            consideration_text: masterSynthesisLocal?.consideration_text ?? '',
            proposals_text: masterSynthesisLocal?.proposals_text ?? '',
            other_text: masterSynthesisLocal?.other_text ?? '',
        };

        // Update the specific category with the new text value
        updateData[`${category}_text`] = text;

        router.patch(
            `/synthesis/master-synthesis/${conversation.id}`,
            updateData,
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    if (page.props.masterSynthesis) {
                        setMasterSynthesisLocal(page.props.masterSynthesis);
                    }
                },
            }
        );
    };

    // Get master synthesis text for a category
    const getMasterText = (category: string): string => {
        if (!masterSynthesisLocal) {
return '';
}

        return (masterSynthesisLocal as Record<string, unknown>)[`${category}_text`] as string ?? '';
    };

    return (
        <>
            <Head title={`Collaborative Review - ${conversation?.title || 'Synthesis Review'}`} />
            <div className="p-8 flex flex-col gap-6 max-w-[1920px] mx-auto">
                {/* ─── Header Section ─────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Collaborative Synthesis Review</h1>
                        <p className="text-muted-foreground mt-1">
                            {conversation?.title} — View and compile synthesis from all team members
                        </p>
                    </div>

                    <div className="flex items-center gap-4">
                        {/* Online Users Indicator */}
                        {onlineUsers.length > 0 && (
                            <div className="flex items-center gap-2 px-3 py-1.5 bg-green-50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-lg">
                                <div className="size-2 bg-green-500 rounded-full animate-pulse" />
                                <span className="text-sm text-green-700 dark:text-green-300">
                                    {onlineUsers.length} online
                                </span>
                            </div>
                        )}

                        {/* Back to Review Button */}
                        <Link href={`/synthesis/review/${conversation.id}`}>
                            <Button variant="outline" className="flex items-center gap-2">
                                <FileText className="size-4" /> Edit My Synthesis
                            </Button>
                        </Link>

                        {/* Submit Synthesis Button */}
                        {!hasSubmitted && (
                            <Button onClick={handleSubmitSynthesis} disabled={isSubmitting} className="flex items-center gap-2">
                                {isSubmitting && <Loader2 className="size-4 animate-spin" />}
                                <CheckCircle2 className="size-4" /> Submit My Synthesis
                            </Button>
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

                {/* ─── Toast Notification ─────────────────────────── */}
                {toastMessage && (
                    <div className="fixed bottom-6 right-6 z-50 px-4 py-3 bg-card border border-border rounded-lg shadow-lg animate-in fade-in slide-in-from-bottom-4">
                        <div className="flex items-center gap-2">
                            <Users className="h-4 w-4 text-muted-foreground" />
                            <span className="text-sm">{toastMessage}</span>
                        </div>
                    </div>
                )}

                {/* ─── Summary Cards ──────────────────────────────── */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-blue-50 dark:bg-blue-950/20 rounded-lg">
                                <Users className="h-5 w-5 text-blue-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{submissionsLocal.length}</p>
                                <p className="text-xs text-muted-foreground">Total Submissions</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-green-50 dark:bg-green-950/20 rounded-lg">
                                <CheckCircle2 className="h-5 w-5 text-green-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {submissionsLocal.filter((s) => s.status === 'submitted').length}
                                </p>
                                <p className="text-xs text-muted-foreground">Submitted</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-amber-50 dark:bg-amber-950/20 rounded-lg">
                                <Loader2 className="h-5 w-5 text-amber-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {submissionsLocal.filter((s) => s.status === 'draft').length}
                                </p>
                                <p className="text-xs text-muted-foreground">In Progress</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-purple-50 dark:bg-purple-950/20 rounded-lg">
                                <Bot className="h-5 w-5 text-purple-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {submissionsLocal.filter((s) => s.is_llm_assisted).length}
                                </p>
                                <p className="text-xs text-muted-foreground">AI-Assisted</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* ─── Tabs: Synthesis by Category ────────────────── */}
                <Card className="border-sidebar-border/50">
                    <CardHeader className="bg-muted/15 border-b border-sidebar-border/30">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <FileText className="size-5" />
                            Synthesis by Category
                        </CardTitle>
                        <CardDescription>
                            Review all member submissions and compile the master synthesis
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <Tabs defaultValue="convergence" className="w-full">
                            <TabsList className="grid w-full grid-cols-4 mb-6">
                                {CATEGORIES.map((category) => {
                                    const Icon = category.icon;
                                    const categorySubmissions = getSubmissionsByCategory(category.key);

                                    return (
                                        <TabsTrigger key={category.key} value={category.key} className="flex items-center gap-2">
                                            <Icon className="h-4 w-4" />
                                            <span className="hidden sm:inline">{category.label}</span>
                                            <Badge variant="secondary" className="text-xs ml-1">
                                                {categorySubmissions.length}
                                            </Badge>
                                        </TabsTrigger>
                                    );
                                })}
                            </TabsList>

                            {CATEGORIES.map((category) => {
                                const Icon = category.icon;
                                const categorySubmissions = getSubmissionsByCategory(category.key);
                                const aiOnlySubmissions = categorySubmissions.filter(
                                    (s) => s.aiText && s.aiText.trim() && !s.text?.trim()
                                );
                                const memberSubmissions = categorySubmissions.filter(
                                    (s) => s.text && s.text.trim()
                                );

                                return (
                                    <TabsContent key={category.key} value={category.key} className="space-y-6">
                                        {/* ─── AI Syntheses Subsection ──────────── */}
                                        {aiOnlySubmissions.length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-blue-700 dark:text-blue-300 mb-3 flex items-center gap-2">
                                                    <Bot className="h-4 w-4" />
                                                    AI-Assisted Syntheses
                                                </h4>
                                                <div className="space-y-3">
                                                    {aiOnlySubmissions.map((submission) => (
                                                        <div
                                                            key={submission.id}
                                                            className="border border-blue-200 dark:border-blue-800 rounded-lg p-4 bg-blue-50/30 dark:bg-blue-950/10"
                                                        >
                                                            <div className="flex items-center justify-between mb-2">
                                                                <div className="flex items-center gap-2">
                                                                    <div className="size-6 rounded-full bg-blue-500 flex items-center justify-center text-xs font-medium text-white">
                                                                        {getInitials(submission.user.name)}
                                                                    </div>
                                                                    <span className="text-sm font-medium">
                                                                        {submission.user.name}
                                                                    </span>
                                                                    <Badge variant="secondary" className="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                                                                        <Bot className="h-3 w-3 mr-1" /> AI
                                                                    </Badge>
                                                                </div>
                                                                {submission.submitted_at && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        Submitted: {new Date(submission.submitted_at).toLocaleString()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <div className="ml-8 mt-2">
                                                                {renderAiText(submission.aiText)}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* ─── Member Submissions Subsection ──── */}
                                        {memberSubmissions.length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-muted-foreground mb-3 flex items-center gap-2">
                                                    <Users className="h-4 w-4" />
                                                    Member Submissions
                                                </h4>
                                                <div className="space-y-3">
                                                    {memberSubmissions.map((submission) => (
                                                        <div
                                                            key={submission.id}
                                                            className="border rounded-lg p-4 bg-card"
                                                        >
                                                            <div className="flex items-center justify-between mb-2">
                                                                <div className="flex items-center gap-2">
                                                                    <div className="size-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium text-primary">
                                                                        {getInitials(submission.user.name)}
                                                                    </div>
                                                                    <span className="text-sm font-medium">
                                                                        {submission.user.name}
                                                                    </span>
                                                                    <Badge variant={submission.status === 'submitted' ? 'default' : 'secondary'} className="text-xs">
                                                                        {submission.status === 'submitted' ? 'Submitted' : 'Draft'}
                                                                    </Badge>
                                                                </div>
                                                                {submission.submitted_at && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {new Date(submission.submitted_at).toLocaleString()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <div className="ml-8 mt-2 whitespace-pre-wrap text-sm">
                                                                {submission.text}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* ─── Master Synthesis Editor ────────── */}
                                        <div>
                                            <h4 className="text-sm font-medium mb-3 flex items-center justify-between">
                                                <span className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4" />
                                                    Master Synthesis ({category.label})
                                                </span>
                                                {(currentUser.role === 'admin' || currentUser.role === 'drafter') && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleUpdateMaster(category.key, getMasterText(category.key))}
                                                    >
                                                        <CheckCircle2 className="h-3 w-3 mr-1" /> Save
                                                    </Button>
                                                )}
                                            </h4>
                                            {masterSynthesisLocal?.id ? (
                                                <Textarea
                                                    value={getMasterText(category.key)}
                                                    onChange={(e) => {
                                                        if (currentUser.role === 'admin' || currentUser.role === 'drafter') {
                                                            setMasterSynthesisLocal((prev) =>
                                                                prev
                                                                    ? { ...prev, [`${category}_text`]: e.target.value }
                                                                    : null
                                                            );
                                                        }
                                                    }}
                                                    rows={8}
                                                    placeholder={`Enter the compiled ${category.label.toLowerCase()} from all submissions...`}
                                                    className="border-muted-foreground/20 focus-visible:ring-primary/30"
                                                />
                                            ) : (
                                                <div className="text-center py-8 text-muted-foreground text-sm bg-muted/30 rounded-lg border border-dashed">
                                                    <FileText className="h-8 w-8 mx-auto text-muted-foreground/30 mb-2" />
                                                    <p>No master synthesis has been compiled yet.</p>
                                                    {(currentUser.role === 'admin' || currentUser.role === 'drafter') && (
                                                        <p className="text-xs mt-1">Start by entering the compiled synthesis above.</p>
                                                    )}
                                                </div>
                                            )}
                                        </div>

                                        {/* ─── AI Generate Button (for draft users) ─ */}
                                        {!hasSubmitted && userDraft && (
                                            <div className="pt-4 border-t">
                                                <Button
                                                    onClick={handleGenerateAiSynthesis}
                                                    disabled={isAiGenerating}
                                                    variant="outline"
                                                    className="w-full flex items-center justify-center gap-2 border-dashed"
                                                >
                                                    {isAiGenerating && <Loader2 className="size-4 animate-spin" />}
                                                    <Bot className="size-4" />
                                                    Generate AI-Assisted Synthesis for My Draft
                                                </Button>
                                            </div>
                                        )}
                                    </TabsContent>
                                );
                            })}
                        </Tabs>
                    </CardContent>
                </Card>

                {/* ─── Action Bar ─────────────────────────────────── */}
                <div className="flex flex-col sm:flex-row gap-3 justify-between pt-4 border-t">
                    <div className="flex items-center gap-3">
                        <Link href={`/synthesis/review/${conversation.id}`}>
                            <Button variant="outline" className="flex items-center gap-2">
                                <FileText className="size-4" /> Back to My Synthesis
                            </Button>
                        </Link>
                    </div>
                    {!hasSubmitted && (
                        <Button onClick={handleSubmitSynthesis} disabled={isSubmitting} className="flex items-center gap-2">
                            {isSubmitting && <Loader2 className="size-4 animate-spin" />}
                            <CheckCircle2 className="size-4" /> Submit My Synthesis
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

ReviewPage.layout = (page: React.ReactNode) => {
     
    const props = page as any;

    if (!props?.props?.conversation) {
        return <>{page}</>;
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Synthesis Dashboard', href: '/synthesis' }, { title: 'Collaborative Review', href: `/synthesis/review-page/${props.props.conversation.id}` }]}>
            {page}
        </AppLayout>
    );
};
