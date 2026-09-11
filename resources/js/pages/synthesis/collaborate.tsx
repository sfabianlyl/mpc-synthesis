import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle, Loader2, FileText, Users, Sparkles, ChevronDown, ChevronUp, Pencil, Clock, MessageSquare, Brain } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { SynthesisSubmission, MasterSynthesisReport } from '@/types/models';
import type { SynthesisMember, CategoryContributions, CollaboratePageProps, SynthesisSummaryData } from './types';
import { CATEGORIES, type CategoryKey } from './constants';
import { renderContentText, getInitials, categoryToFieldName } from './helpers';

// ─── Component ────────────────────────────────────────────────────

export default function Collaborate({
    conversation,
    submissions,
    masterSynthesis,
    synthesisSummary,
    groups,
    responses,
    consensusSubmissions = {},
    currentUser,
    onlineUsers = [],
    success: initialSuccess,
    errors: initialErrors,
}: CollaboratePageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [masterSynthesisLocal, setMasterSynthesisLocal] = useState<MasterSynthesisReport | null>(masterSynthesis);
    const [submissionsLocal, setSubmissionsLocal] = useState<SynthesisSubmission[]>(submissions);
    
    // Per-category input state for the user's own synthesis submission
    const [convergenceInput, setConvergenceInput] = useState('');
    const [mattersInput, setMattersInput] = useState('');
    const [proposalsInput, setProposalsInput] = useState('');
    const [othersInput, setOthersInput] = useState('');
    
    // Per-category master synthesis editor state
    const [masterEditors, setMasterEditors] = useState<Record<string, string>>({
        convergence: '',
        consideration: '',
        proposals: '',
        others: '',
    });
    
    // Expand all master synthesis state
    const [showAllMasterSynthesis, setShowAllMasterSynthesis] = useState(false);
    
    // Save button feedback state per category
    const [saveFeedback, setSaveFeedback] = useState<{
        category: string | null;
        type: 'success' | 'error' | null;
    }>({ category: null, type: null });

    // Save all button feedback state
    const [saveAllFeedback, setSaveAllFeedback] = useState<'success' | 'error' | null>(null);

    // Key points extraction state
    const [keyPointsCount, setKeyPointsCount] = useState(5);
    const [keyPointsText, setKeyPointsText] = useState('');
    const [isExtractingKeyPoints, setIsExtractingKeyPoints] = useState(false);
    const [hasLoadedKeyPoints, setHasLoadedKeyPoints] = useState(false);

    // Key points save feedback state
    const [keyPointsSaveFeedback, setKeyPointsSaveFeedback] = useState<'success' | 'error' | null>(null);
    
    // Active editing category indicator (for real-time collaboration)

    // SynthesisSummary state
    const [isGeneratingSummary, setIsGeneratingSummary] = useState(false);
    const [summaryLocal, setSummaryLocal] = useState<SynthesisSummaryData | null>(synthesisSummary);
    const [activeEditingCategory, setActiveEditingCategory] = useState<string | null>(null);

    console.log('[DEBUG] Initial synthesisSummary prop:', JSON.stringify(synthesisSummary, null, 2));
    console.log('[DEBUG] Initial summaryLocal state:', JSON.stringify(summaryLocal, null, 2));

    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }

        if (flash?.errors?.submission || flash?.errors?.master_synthesis) {
            const key = flash.errors.submission ? 'submission' : 'master_synthesis';
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

    // Initialize master synthesis editors on mount or when masterSynthesis changes
    useEffect(() => {
        if (masterSynthesisLocal) {
            setMasterEditors({
                convergence: masterSynthesisLocal.convergence_text ?? '',
                consideration: masterSynthesisLocal.consideration_text ?? '',
                proposals: masterSynthesisLocal.proposals_text ?? '',
                others: masterSynthesisLocal.other_text ?? '',
            });
        } else {
            // Reset to empty when no master synthesis exists
            setMasterEditors({
                convergence: '',
                consideration: '',
                proposals: '',
                others: '',
            });
        }
    }, [masterSynthesisLocal]);
    // Load existing key points on mount or when masterSynthesis changes
    useEffect(() => {
        if (!masterSynthesisLocal?.id || hasLoadedKeyPoints) {
            return;
        }
        fetch(`/synthesis/key-points/${conversation.id}?master_synthesis_id=${masterSynthesisLocal.id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        })
            .then((res) => res.json())
            .then((data) => {
                if (data.key_point?.key_points_text) {
                    setKeyPointsText(data.key_point.key_points_text);
                    setHasLoadedKeyPoints(true);
                }
            })
            .catch(() => {});
    }, [masterSynthesisLocal?.id, conversation?.id]);

    // Check if current user has a submitted submission
    const hasSubmitted = submissionsLocal.some(
        (s) => s.user.id === currentUser.id && s.status === 'submitted'
    );

    // Track submitted drafts count and whether summary regeneration button should be shown
    const submittedDraftsCount = submissionsLocal.filter(s => s.status === 'submitted').length;
    const hasGeneratedSummary = Boolean(summaryLocal && summaryLocal.generated_at && summaryLocal.is_successful);
    const summarySubmittedDraftsCount = summaryLocal?.submitted_drafts_count ?? 0;
    const shouldShowSummaryButton = !hasGeneratedSummary || (submittedDraftsCount !== summarySubmittedDraftsCount);

    // Get online synthesis members
    const onlineMembers = onlineUsers.filter(u => u.is_online !== false);
    
    // Who is editing which category
    const editingIndicators = onlineMembers
        .filter(u => u.editing_category)
        .reduce<Record<string, number>>((acc, u) => {
            const cat = u.editing_category!;
            acc[cat] = (acc[cat] || 0) + 1;
            return acc;
        }, {});

    // Group submissions by category for display
    const getSubmissionsByCategory = useCallback((categoryKey: string) => {
        const fieldName = categoryToFieldName(categoryKey);

        return submissionsLocal.map((s) => ({
            ...s,
            text: (s as unknown as Record<string, unknown>)[fieldName] as string | null,
        }));
    }, [submissionsLocal]);

    // Build category contributions from all submissions
    const buildCategoryContributions = useCallback((category: string): CategoryContributions => {
        const catSubmissions = getSubmissionsByCategory(category);
        let unifiedContent = '';

        catSubmissions.forEach(s => {
            if (s.text && s.text.trim()) {
                unifiedContent += (unifiedContent ? '\n\n---\n\n' : '') + s.text;
            }
        });

        return {
            content: unifiedContent,
            submissions: catSubmissions.map(s => ({
                id: s.id,
                user: s.user,
                content: s.text,
                created_at: '',
                updated_at: '',
            })),
        };
    }, [getSubmissionsByCategory]);

    // Submit synthesis handler — sends the user's own draft input values
    const handleSubmitSynthesis = () => {
        setIsSubmitting(true);
        router.post(
            `/synthesis/submit-draft/${conversation.id}`,
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

    // Save master synthesis for a specific category — any synthesis member can edit and save
    const handleSaveMasterCategory = (categoryKey: string) => {
        // Track active editing category
        setActiveEditingCategory(categoryKey);
        setTimeout(() => setActiveEditingCategory(null), 3000);

        const fieldName = categoryKey === 'convergence' ? 'convergence_text' : categoryKey === 'consideration' ? 'consideration_text' : categoryKey === 'proposals' ? 'proposals_text' : 'other_text';

        router.post(
            `/synthesis/master-synthesis-authorized/${conversation.id}`,
            { [fieldName]: masterEditors[categoryKey] ?? null },
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    if (page.props.masterSynthesis) {
                        setMasterSynthesisLocal(page.props.masterSynthesis);
                    }
                    // Show "Saved" feedback for 2 seconds
                    setSaveFeedback({ category: categoryKey, type: 'success' });
                    setTimeout(() => setSaveFeedback(prev => prev.category === categoryKey ? { category: null, type: null } : prev), 2000);
                },
                onError: (errors) => {
                    console.error('[ERROR] Save master synthesis failed:', errors);
                    setStatusMessage({ type: 'error', text: 'Failed to save master synthesis. Check console for details.' });
                    // Show "Error Saving" feedback for 2 seconds
                    setSaveFeedback({ category: categoryKey, type: 'error' });
                    setTimeout(() => setSaveFeedback(prev => prev.category === categoryKey ? { category: null, type: null } : prev), 2000);
                },
            }
        );
    };

    // Save all master synthesis categories at once — any synthesis member can edit and save
    const handleSaveAllMasterCategories = () => {
        router.post(
            `/synthesis/master-synthesis-authorized/${conversation.id}`,
            {
                convergence_text: masterEditors.convergence ?? null,
                consideration_text: masterEditors.consideration ?? null,
                proposals_text: masterEditors.proposals ?? null,
                other_text: masterEditors.others ?? null,
            },
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    if (page.props.masterSynthesis) {
                        setMasterSynthesisLocal(page.props.masterSynthesis);
                    }
                    // Show "Saved" feedback for 2 seconds
                    setSaveAllFeedback('success');
                    setTimeout(() => setSaveAllFeedback(null), 2000);
                },
                onError: (errors) => {
                    console.error('[ERROR] Save all master synthesis failed:', errors);
                    setStatusMessage({ type: 'error', text: 'Failed to save master synthesis. Check console for details.' });
                    // Show "Error Saving" feedback for 2 seconds
                    setSaveAllFeedback('error');
                    setTimeout(() => setSaveAllFeedback(null), 2000);
                },
            }
        );
    };

    // Update master editor content for a category
    const updateMasterEditor = (categoryKey: string, value: string) => {
        setMasterEditors(prev => ({
            ...prev,
            [categoryKey]: value,
        }));
    };

    // Check if current user can edit (admin or drafter role)
    const canEditMaster = currentUser.role === 'admin' || currentUser.role === 'drafter';

    // Extract key points handler

    // Generate SynthesisSummary handler — generates summaries for ALL categories synchronously
    const handleGenerateSynthesisSummary = async () => {
        setIsGeneratingSummary(true);
        setStatusMessage(null);
        try {
            const res = await fetch(`/synthesis/summary-sync/${conversation.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();

            if (data.success && data.summary) {
                setSummaryLocal(data.summary);
                setStatusMessage({ type: 'success', text: 'Synthesis summaries generated successfully for all categories.' });
            } else {
                setStatusMessage({ type: 'error', text: data.message || 'Failed to generate summary.' });
            }
        } catch {
            setStatusMessage({ type: 'error', text: 'An error occurred while generating the summary.' });
        } finally {
            setIsGeneratingSummary(false);
        }
    };
    const handleExtractKeyPoints = () => {
        if (!masterSynthesisLocal?.id) {
            return;
        }

        setIsExtractingKeyPoints(true);
        fetch(`/synthesis/extract-key-points/${conversation.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                max_key_points: keyPointsCount,
                master_synthesis_id: masterSynthesisLocal.id,
            }),
        })
            .then((res) => res.json())
            .then((data) => {
                setIsExtractingKeyPoints(false);
                if (data.success && data.key_points?.key_points_text) {
                    setKeyPointsText(data.key_points.key_points_text);
                    setHasLoadedKeyPoints(true);
                }
            })
            .catch(() => {
                setIsExtractingKeyPoints(false);
            });
    };

    // Save key points handler — persists edited key points to the backend
    const handleSaveKeyPoints = () => {
        if (!keyPointsText.trim()) {
            return;
        }

        setKeyPointsSaveFeedback('success');
        setTimeout(() => setKeyPointsSaveFeedback(null), 2000);

        fetch(`/synthesis/key-points/${conversation.id}`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                key_points_text: keyPointsText,
                master_synthesis_id: masterSynthesisLocal?.id,
            }),
        })
            .then((res) => res.json())
            .then((data) => {
                if (!data.success) {
                    setKeyPointsSaveFeedback('error');
                    setTimeout(() => setKeyPointsSaveFeedback(null), 2000);
                }
            })
            .catch(() => {
                setKeyPointsSaveFeedback('error');
                setTimeout(() => setKeyPointsSaveFeedback(null), 2000);
            });
    };

    return (
        <>
            <Head title={`Collaborative Review - ${conversation?.title || 'Synthesis Review'}`} />
            <div className="p-8 flex flex-col gap-6 max-w-[1920px] mx-auto">
                {/* ─── Header Section ─────────────────────────────── */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Collaborative Synthesis Review</h1>
                        {conversation?.title && (
                            <p className="text-muted-foreground mt-1 text-lg font-medium">{conversation.title}</p>
                        )}
                    </div>
                   

                    <div className="flex items-center gap-4">
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

                {conversation?.description && (
                    <div className="rounded-xl border border-blue-100 bg-blue-50/60 p-4 dark:border-blue-900/40 dark:bg-blue-950/30">
                        <p className="mb-1 text-sm font-semibold uppercase tracking-wider text-blue-900 dark:text-blue-300">
                            Conversation Details
                        </p>
                        <div className="whitespace-pre-wrap text-base leading-relaxed text-slate-700 dark:text-slate-300">
                            {conversation.description}
                        </div>
                    </div>
                )}

                 <section className="space-y-4">
                        {/* Instructions for synthesis members */}
                        <h2 className="font-semibold">Group Action</h2>
    
                        <h4>The task is to discern the factors of:</h4>
                        <ol type="A">
                            <li>A. Convergence: Independent points coming together to point toward one clear, unified conclusion or emerging conclusion. </li>
                            <li>B. Matters for consideration: A dissenting statement or totally new and unheard of ideas or improved ways of doing existing ideas or objections.</li>
                            <li>C. Recommendation: actionable or something clear enough to be put into practical use. Recommendation may also be explained as other required action before being able to address the pertaining matter.</li>
                        </ol>
                        
                        <h4 className="font-semibold">Method</h4>
                        <ol>
                            <li>1. Read the submitted paragraphs</li>
                            <li>2. Then you may use the LLM trigger to assist in the formulation of  FINAL Convergence + Matters for consideration + Proposal</li>
                            <li>3. Complete in full and proper sentences under Master Content your Convergence + Matters for consideration + Proposal for further discussion and discernment under collaborative review</li>
                        </ol>
                </section>

                {/* ─── Online Users & Collaboration Indicators ────── */}
                {onlineMembers.length > 0 && (
                    <Card className="border-sidebar-border/50">
                        <CardContent className="pt-6 pb-4">
                            <div className="flex items-center gap-4 flex-wrap">
                                <div className="flex items-center gap-2">
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm font-medium text-muted-foreground">Online:</span>
                                </div>
                                <div className="flex -space-x-2">
                                    {onlineMembers.map((user) => (
                                        <div
                                            key={user.id}
                                            className={`size-8 rounded-full border-2 border-background flex items-center justify-center text-xs font-medium ${
                                                user.editing_category 
                                                    ? 'bg-primary text-primary-foreground animate-pulse' 
                                                    : 'bg-muted text-muted-foreground'
                                            }`}
                                            title={`${user.name}${user.editing_category ? ` (editing: ${CATEGORIES.find(c => c.key === user.editing_category)?.label || user.editing_category})` : ''}`}
                                        >
                                            {getInitials(user.name)}
                                        </div>
                                    ))}
                                </div>
                                <div className="flex items-center gap-2 text-xs text-muted-foreground ml-auto">
                                    <Clock className="size-3" />
                                    <span>
                                        {Object.entries(editingIndicators).map(([cat, count]) => (
                                            <span key={cat} className="ml-2">
                                                {count} editing {CATEGORIES.find(c => c.key === cat)?.label || cat}
                                            </span>
                                        ))}
                                    </span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                )}

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

                {/* ─── Summary Cards ──────────────────────────────── */}
                <div className="grid gap-4 md:grid-cols-3">
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
                            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/20 rounded-lg">
                                <FileText className="h-5 w-5 text-indigo-600" />
                            </div>
                            <div>
                                <p className="text-2xl font-bold">
                                    {masterSynthesisLocal?.id ? '1' : '0'}
                                </p>
                                <p className="text-xs text-muted-foreground">Master Syntheses</p>
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
                            Review all member submissions and compile the master synthesis for each category
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
                                const contributions = buildCategoryContributions(category.key);
                                
                                const manualSubmissions = categorySubmissions.filter(
                                    (s) => s.text && s.text.trim()
                                );

                                const isEditingThisCategory = activeEditingCategory === category.key;

                                return (
                                    <TabsContent key={category.key} value={category.key} className="space-y-6">
                                        {/* ─── Category Description ────────────── */}
                                        <div className={`p-4 rounded-lg border ${category.bgColor} ${category.borderColor}`}>
                                            <p className={`text-sm font-medium ${category.color}`}>
                                                {category.description}
                                            </p>
                                        </div>

                                        {/* ─── Manual Contributions ────────────── */}
                                        {manualSubmissions.length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-muted-foreground mb-3 flex items-center gap-2">
                                                    <Users className="h-4 w-4" />
                                                    Contributions ({manualSubmissions.length})
                                                </h4>
                                                <div className="space-y-3">
                                                    {manualSubmissions.map((submission) => (
                                                        <div
                                                            key={submission.id}
                                                            className={`border ${category.borderColor} rounded-lg p-4 bg-card`}
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


                                        {/* ─── SynthesisSummary Display (Non-editable) ────────── */}
                                        {summaryLocal && summaryLocal.id && (() => {
                                            const summaryFieldName = category.key + '_summary';
                                            const categoryContent = (summaryLocal as any)[summaryFieldName];
                                            // Only show the summary box if this category has content
                                            if (!categoryContent || !categoryContent.trim()) return null;
                                            return (
                                                <div className="mb-4 p-4 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-950/20 dark:border-blue-800">
                                                    <h4 className="text-sm font-medium mb-2 flex items-center justify-between">
                                                        <span className="flex items-center gap-2">
                                                            <Sparkles className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                            AI-Generated Synthesis Summary ({category.label})
                                                        </span>
                                                        {summaryLocal.generated_at && (
                                                            <span className="text-xs text-muted-foreground">
                                                                Generated: {new Date(summaryLocal.generated_at).toLocaleString()}
                                                            </span>
                                                        )}
                                                    </h4>
                                                    <div className="whitespace-pre-wrap text-sm text-muted-foreground">
                                                        {categoryContent}
                                                    </div>
                                                </div>
                                            );
                                        })()}
                                        {/* Show trigger button when no summary exists or when submitted drafts count has changed */}
                                        {shouldShowSummaryButton && (
                                            <div className="mb-4">
                                                <Button
                                                    onClick={handleGenerateSynthesisSummary}
                                                    disabled={isGeneratingSummary || submittedDraftsCount === 0}
                                                    variant={hasGeneratedSummary ? 'outline' : 'default'}
                                                    className={`flex items-center gap-2 ${hasGeneratedSummary ? 'bg-blue-600 text-white hover:bg-blue-700' : 'bg-blue-600 hover:bg-blue-700'}`}
                                                >
                                                    {isGeneratingSummary && <Loader2 className="size-4 animate-spin" />}
                                                    <Sparkles className="size-4" />
                                                    {hasGeneratedSummary ? 'Regenerate' : 'Generate'} Synthesis Summary for {category.label}
                                                </Button>
                                            </div>
                                        )}
                                        {/* ─── Master Synthesis Editor ────────── */}
                                        <div className={`pt-4 border-t ${isEditingThisCategory ? 'border-primary/30 bg-primary/5' : ''}`}>
                                            <h4 className="text-sm font-medium mb-3 flex items-center justify-between">
                                                <span className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4" />
                                                    Master Synthesis ({category.label})
                                                </span>
                                                <Button
                                                    size="sm"
                                                    variant={saveFeedback.category === category.key && saveFeedback.type === 'error' ? 'destructive' : 'default'}
                                                    onClick={() => handleSaveMasterCategory(category.key)}
                                                    disabled={saveFeedback.category === category.key}
                                                    className={saveFeedback.category === category.key && saveFeedback.type === 'success' ? 'bg-emerald-600 hover:bg-emerald-700' : ''}
                                                >
                                                    {saveFeedback.category === category.key && saveFeedback.type === 'success' ? (
                                                        <><CheckCircle2 className="h-3 w-3 mr-1" /> Saved</>
                                                    ) : saveFeedback.category === category.key && saveFeedback.type === 'error' ? (
                                                        <><XCircle className="h-3 w-3 mr-1" /> Error Saving</>
                                                    ) : (
                                                        <><CheckCircle2 className="h-3 w-3 mr-1" /> Save</>
                                                    )}
                                                </Button>
                                            </h4>

                                            <div className="space-y-4">
                                                {/* Manual Editor - Simple plain textarea */}
                                                <div>
                                                    <label className="text-sm font-medium mb-1 block flex items-center gap-2">
                                                        <Pencil className="h-3 w-3" />
                                                        Master Content (Editable)
                                                    </label>
                                                    <textarea
                                                        value={masterEditors[category.key] ?? ''}
                                                        onChange={(e) => updateMasterEditor(category.key, e.target.value)}
                                                        rows={10}
                                                        className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    />
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        Manually edit the master synthesis for this category.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </TabsContent>
                                );
                            })}
                        </Tabs>
                    </CardContent>
                </Card>

                {/* ─── Save All Master Synthesis (for admin/drafter) ───── */}
                {canEditMaster && (
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                            <CardTitle className="text-lg flex items-center justify-between">
                                <span className="flex items-center gap-2">
                                    <FileText className="size-5" />
                                    Save All Master Synthesis Categories
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="pt-6">
                            <div className="flex justify-end">
                                <Button
                                    onClick={handleSaveAllMasterCategories}
                                    disabled={saveAllFeedback !== null}
                                    variant={saveAllFeedback === 'error' ? 'destructive' : 'default'}
                                    className={`flex items-center gap-2 ${saveAllFeedback === 'success' ? 'bg-emerald-600 hover:bg-emerald-700' : ''}`}
                                >
                                    {saveAllFeedback === 'success' ? (
                                        <><CheckCircle2 className="size-4" /> Saved</>
                                    ) : saveAllFeedback === 'error' ? (
                                        <><XCircle className="size-4" /> Error Saving</>
                                    ) : (
                                        <><CheckCircle2 className="size-4" /> Save All Categories</>
                                    )}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Show All Master Synthesis (Expandable) ───── */}
                <Card className="border-sidebar-border/50">
                    <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                        <CardTitle className="text-lg flex items-center justify-between">
                            <span className="flex items-center gap-2">
                                <FileText className="size-5" />
                                All Master Synthesis
                            </span>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => setShowAllMasterSynthesis(!showAllMasterSynthesis)}
                                className="flex items-center gap-2"
                            >
                                {showAllMasterSynthesis ? (
                                    <ChevronUp className="size-4" />
                                ) : (
                                    <ChevronDown className="size-4" />
                                )}
                                {showAllMasterSynthesis ? 'Collapse' : 'Expand'}
                            </Button>
                        </CardTitle>
                    </CardHeader>
                    {showAllMasterSynthesis && masterSynthesisLocal && (
                        <CardContent className="pt-6 space-y-6">
                            {CATEGORIES.map((category) => (
                                <div key={category.key}>
                                    <h4 className={`text-sm font-medium mb-2 flex items-center gap-2 ${category.color}`}>
                                        {category.icon && <category.icon className="h-4 w-4" />}
                                        {category.label}
                                    </h4>
                                    <div className="space-y-2">
                                        <p className="text-xs font-medium text-green-700 dark:text-green-300 mb-1">Master Content:</p>
                                        <div className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground whitespace-pre-wrap">
                                            {masterEditors[category.key] ?? 'No content available.'}
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {/* Key Points Extraction Output */}
                            {keyPointsText && (
                                <div>
                                    <h4 className="text-sm font-medium mb-2 flex items-center gap-2 text-purple-700 dark:text-purple-300">
                                        <Brain className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        Extracted Key Points:
                                    </h4>
                                    <div className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground whitespace-pre-wrap">
                                        {keyPointsText.split('\n').filter((line) => line.trim()).map((line, index) => {
                                            // Strip existing dash/bullet prefix if present for consistent numbering
                                            const cleanLine = line.replace(/^[0-9]+[\.\)]*\s*|-+\s*/, '').trim();
                                            return (
                                                <div key={index} className="mb-1">
                                                    <span className="font-medium">{index + 1}.</span> {cleanLine}
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    )}
                    {showAllMasterSynthesis && (!masterSynthesisLocal || !masterSynthesisLocal.id) && (
                        <CardContent className="pt-6 text-center text-muted-foreground text-sm bg-muted/30 rounded-lg border border-dashed">
                            <FileText className="h-8 w-8 mx-auto text-muted-foreground/30 mb-2" />
                            <p>No master synthesis has been compiled yet.</p>
                        </CardContent>
                    )}
                </Card>

                {/* ─── Extract Key Points ─────────────────────────── */}
                {masterSynthesisLocal?.id && (
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30 py-4">
                            <CardTitle className="text-lg flex items-center gap-2">
                                <Brain className="size-5 text-purple-600 dark:text-purple-400" />
                                Extract Key Points from Master Synthesis
                            </CardTitle>
                            <CardDescription>
                                Use AI to extract the most important key points from the master synthesis content
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6 space-y-4">
                            {/* Number input and button row */}
                            <div className="flex items-center gap-4 flex-wrap">
                                <div className="flex items-center gap-2">
                                    <label htmlFor="key-points-count" className="text-sm font-medium whitespace-nowrap">
                                        Number of Key Points:
                                    </label>
                                    <Input
                                        id="key-points-count"
                                        type="number"
                                        min={1}
                                        max={10}
                                        value={keyPointsCount}
                                        onChange={(e) => setKeyPointsCount(parseInt(e.target.value) || 1)}
                                        className="w-20"
                                    />
                                </div>
                                <Button
                                    onClick={handleExtractKeyPoints}
                                    disabled={isExtractingKeyPoints || !masterSynthesisLocal.id}
                                    className="flex items-center gap-2 bg-purple-600 hover:bg-purple-700"
                                >
                                    {isExtractingKeyPoints && <Loader2 className="size-4 animate-spin" />}
                                    <Sparkles className="size-4" />
                                    Extract Key Points
                                </Button>
                            </div>

                            {/* Key Points Textarea */}
                            {(keyPointsText || isExtractingKeyPoints || hasLoadedKeyPoints) && (
                                <div className="space-y-2">
                                    <label className="text-sm font-medium mb-1 block flex items-center gap-2">
                                        <Brain className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                                        Extracted Key Points (Editable)
                                    </label>
                                    <textarea
                                        value={keyPointsText}
                                        onChange={(e) => setKeyPointsText(e.target.value)}
                                        rows={8}
                                        placeholder={isExtractingKeyPoints ? 'Extracting key points...' : 'Key points will appear here...'}
                                        className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    />
                                    <div className="flex items-center justify-between">
                                        <p className="text-xs text-muted-foreground">
                                            Edit the extracted key points as needed. Each point should be on its own line starting with a number (e.g., "1. ").
                                        </p>
                                        <Button
                                            onClick={handleSaveKeyPoints}
                                            disabled={keyPointsSaveFeedback !== null || !keyPointsText.trim()}
                                            variant={keyPointsSaveFeedback === 'error' ? 'destructive' : 'default'}
                                            className={`flex items-center gap-2 text-sm ${keyPointsSaveFeedback === 'success' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-purple-600 hover:bg-purple-700'}`}
                                        >
                                            {keyPointsSaveFeedback === 'success' ? (
                                                <><CheckCircle2 className="size-4" /> Saved</>
                                            ) : keyPointsSaveFeedback === 'error' ? (
                                                <><XCircle className="size-4" /> Error Saving</>
                                            ) : (
                                                <><CheckCircle2 className="size-4" /> Save Key Points</>
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                {/* ─── Action Bar ───────────────────────────── */}
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

Collaborate.layout = (page: React.ReactNode) => {
    const props = page as any;

    if (!props?.props?.conversation) {
        return <>{page}</>;
    }

    return (
        <AppLayout breadcrumbs={[{ title: 'Synthesis Dashboard', href: '/synthesis' }, { title: 'Collaborative Review', href: `/synthesis/collaborate/${props.props.conversation.id}` }]}>
            {page}
        </AppLayout>
    );
};
