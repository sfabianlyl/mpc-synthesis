import { Head, Link, router, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle, Loader2, FileText, Users, Bot, Sparkles, ChevronDown, ChevronUp, Pencil, Eye, EyeOff, Clock, MessageSquare } from 'lucide-react';
import { useState, useEffect, useCallback } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import AppLayout from '@/layouts/app-layout';
import type { SynthesisSubmission, MasterSynthesisReport, EnrichedDocumentChunk } from '@/types/models';

// ─── Type Definitions ─────────────────────────────────────────────

interface SynthesisMember {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_online?: boolean;
    editing_category?: string | null;
}

/**
 * Grouped synthesis contributions for a single category from all members.
 * Each member can have both AI-generated and manually input content.
 */
interface CategoryContributions {
    ai_content: string;        // LLM-generated content (read-only reference)
    manual_content: string;    // Manually crafted content (editable)
    submissions: Array<{
        id: number;
        user: { id: number; name: string };
        ai_content: string | null;
        manual_content: string | null;
        is_ai_generated: boolean;
        submission_number: number;
        created_at: string;
        updated_at: string;
    }>;
}

interface MasterSynthesisEditor {
    convergence: CategoryContributions;
    matters_for_consideration: CategoryContributions;
    proposals: CategoryContributions;
    others: CategoryContributions;
}

interface PageProps {
    conversation: any;
    submissions: SynthesisSubmission[];
    masterSynthesis: MasterSynthesisReport | null;
    groups: any[];
    responses: any[];
    consensusSubmissions: Record<number, any[]>;
    currentUser: { id: number; name: string; email: string; role: string };
    onlineUsers?: SynthesisMember[];
    retrievedChunks?: EnrichedDocumentChunk[];
    success?: string;
    errors?: Record<string, string>;
}

// ─── Category Configuration ───────────────────────────────────────

const CATEGORIES = [
    { 
        key: 'convergence', 
        label: 'Convergence', 
        icon: CheckCircle2,
        description: 'Points of agreement across all groups',
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/20',
        borderColor: 'border-emerald-200 dark:border-emerald-800',
    },
    { 
        key: 'consideration', 
        label: 'Matters for Consideration', 
        icon: FileText,
        description: 'Critical issues requiring attention',
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-50 dark:bg-amber-950/20',
        borderColor: 'border-amber-200 dark:border-amber-800',
    },
    { 
        key: 'proposals', 
        label: 'Proposals', 
        icon: Sparkles,
        description: 'Actionable recommendations and suggestions',
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950/20',
        borderColor: 'border-blue-200 dark:border-blue-800',
    },
    { 
        key: 'others', 
        label: 'Others', 
        icon: MessageSquare,
        description: 'Additional insights and observations',
        color: 'text-violet-600 dark:text-violet-400',
        bgColor: 'bg-violet-50 dark:bg-violet-950/20',
        borderColor: 'border-violet-200 dark:border-violet-800',
    },
] as const;

// ─── Helper Functions ─────────────────────────────────────────────

const renderContentText = (text: string | null, isAiGenerated: boolean = false): React.ReactNode => {
    if (!text || !text.trim()) {
        return <p className="text-sm text-muted-foreground italic">No content</p>;
    }

    const lines = text.split('\n').filter((line) => line.trim());

    if (lines.length > 1) {
        return (
            <ul className="space-y-1">
                {lines.map((line, i) => (
                    <li key={i} className="flex items-start gap-2">
                        <span className={`${isAiGenerated ? 'text-blue-500' : 'text-primary'} mt-0.5 flex-shrink-0`}>•</span>
                        <span className="text-sm">{line.replace(/^-+\s*/, '')}</span>
                    </li>
                ))}
            </ul>
        );
    }

    return <p className="text-sm whitespace-pre-wrap">{text}</p>;
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

export default function Collaborate({
    conversation,
    submissions,
    masterSynthesis,
    groups,
    responses,
    consensusSubmissions = {},
    currentUser,
    onlineUsers = [],
    success: initialSuccess,
    errors: initialErrors,
}: PageProps) {
    const { flash } = usePage().props as any;
    const [statusMessage, setStatusMessage] = useState<{ type: 'success' | 'error'; text: string } | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [isAiGenerating, setIsAiGenerating] = useState(false);
    const [isMasterAiGenerating, setIsMasterAiGenerating] = useState(false);
    const [masterSynthesisLocal, setMasterSynthesisLocal] = useState<MasterSynthesisReport | null>(masterSynthesis);
    const [submissionsLocal, setSubmissionsLocal] = useState<SynthesisSubmission[]>(submissions);
    
    // Per-category input state for the user's own synthesis submission
    const [convergenceInput, setConvergenceInput] = useState('');
    const [mattersInput, setMattersInput] = useState('');
    const [proposalsInput, setProposalsInput] = useState('');
    const [othersInput, setOthersInput] = useState('');
    
    // Per-category master synthesis editor state (both AI and manual content)
    const [masterEditors, setMasterEditors] = useState<Record<string, { ai_content: string; manual_content: string }>>({
        convergence: { ai_content: '', manual_content: '' },
        matters_for_consideration: { ai_content: '', manual_content: '' },
        proposals: { ai_content: '', manual_content: '' },
        others: { ai_content: '', manual_content: '' },
    });
    
    // Expand all master synthesis state
    const [showAllMasterSynthesis, setShowAllMasterSynthesis] = useState(false);
    
    // Filter state for master synthesis content display
    const [showAiContent, setShowAiContent] = useState<Record<string, boolean>>({
        convergence: false,
        matters_for_consideration: false,
        proposals: false,
        others: false,
    });
    
    // Active editing category indicator (for real-time collaboration)
    const [activeEditingCategory, setActiveEditingCategory] = useState<string | null>(null);

    // Category field mapping for master synthesis
    const MASTER_CATEGORY_FIELD_MAP: Record<string, string> = {
        convergence: 'convergence',
        matters_for_consideration: 'consideration',
        proposals: 'proposals',
        others: 'other',
    };

    useEffect(() => {
        if (flash?.success) {
            setStatusMessage({ type: 'success', text: flash.success });
            const timer = setTimeout(() => setStatusMessage(null), 5000);
            return () => clearTimeout(timer);
        }

        if (flash?.errors?.submission || flash?.errors?.ai_synthesis || flash?.errors?.master_synthesis) {
            const key = flash.errors.submission ? 'submission' : flash.errors.ai_synthesis ? 'ai_synthesis' : 'master_synthesis';
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
            const fieldMap: Record<string, string> = {
                convergence: 'convergence_text',
                matters_for_consideration: 'consideration_text',
                proposals: 'proposals_text',
                others: 'other_text',
            };

            // Check for AI-assisted fields
            const hasAiFields = 
                masterSynthesisLocal.ai_convergence_text ||
                masterSynthesisLocal.ai_consideration_text ||
                masterSynthesisLocal.ai_proposals_text ||
                masterSynthesisLocal.ai_other_text;

            if (hasAiFields) {
                // If AI-assisted, pre-fill ai_content with AI data
                setMasterEditors({
                    convergence: { 
                        ai_content: masterSynthesisLocal.ai_convergence_text ?? '', 
                        manual_content: masterSynthesisLocal.convergence_text ?? '' 
                    },
                    matters_for_consideration: { 
                        ai_content: masterSynthesisLocal.ai_consideration_text ?? '', 
                        manual_content: masterSynthesisLocal.consideration_text ?? '' 
                    },
                    proposals: { 
                        ai_content: masterSynthesisLocal.ai_proposals_text ?? '', 
                        manual_content: masterSynthesisLocal.proposals_text ?? '' 
                    },
                    others: { 
                        ai_content: masterSynthesisLocal.ai_other_text ?? '', 
                        manual_content: masterSynthesisLocal.other_text ?? '' 
                    },
                });
            } else {
                // Otherwise, use regular fields as manual_content
                setMasterEditors({
                    convergence: { 
                        ai_content: '', 
                        manual_content: masterSynthesisLocal.convergence_text ?? '' 
                    },
                    matters_for_consideration: { 
                        ai_content: '', 
                        manual_content: masterSynthesisLocal.consideration_text ?? '' 
                    },
                    proposals: { 
                        ai_content: '', 
                        manual_content: masterSynthesisLocal.proposals_text ?? '' 
                    },
                    others: { 
                        ai_content: '', 
                        manual_content: masterSynthesisLocal.other_text ?? '' 
                    },
                });
            }
        } else {
            // Reset to empty when no master synthesis exists
            setMasterEditors({
                convergence: { ai_content: '', manual_content: '' },
                matters_for_consideration: { ai_content: '', manual_content: '' },
                proposals: { ai_content: '', manual_content: '' },
                others: { ai_content: '', manual_content: '' },
            });
        }
    }, [masterSynthesisLocal]);

    // Check if current user has a submitted submission
    const hasSubmitted = submissionsLocal.some(
        (s) => s.user.id === currentUser.id && s.status === 'submitted'
    );

    // Check if current user has a draft submission
    const userDraft = submissionsLocal.find(
        (s) => s.user.id === currentUser.id && s.status === 'draft'
    );

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
    const getSubmissionsByCategory = useCallback((category: string) => {
        return submissionsLocal.map((s) => ({
            ...s,
            text: (s as unknown as Record<string, unknown>)[`${category}_text`] as string | null,
            aiText: (s as unknown as Record<string, unknown>)[`ai_${category}_text`] as string | null,
        }));
    }, [submissionsLocal]);

    // Build category contributions from all submissions
    const buildCategoryContributions = useCallback((category: string): CategoryContributions => {
        const catSubmissions = getSubmissionsByCategory(category);
        let aiContent = '';
        let manualContent = '';

        catSubmissions.forEach(s => {
            if (s.aiText && s.aiText.trim()) {
                aiContent += (aiContent ? '\n\n--- AI Source ---\n\n' : '') + s.aiText;
            }
            if (s.text && s.text.trim()) {
                manualContent += (manualContent ? '\n\n--- Manual Input ---\n\n' : '') + s.text;
            }
        });

        return {
            ai_content: aiContent,
            manual_content: manualContent,
            submissions: catSubmissions.map(s => ({
                id: s.id,
                user: s.user,
                ai_content: s.aiText,
                manual_content: s.text,
                is_ai_generated: !!s.aiText && !s.text?.trim(),
                submission_number: s.submission_number || 1,
                created_at: '',
                updated_at: '',
            })),
        };
    }, [getSubmissionsByCategory]);

    // Submit synthesis handler — sends the user's own draft input values
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

    // Generate AI-assisted synthesis handler for individual submission
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

    // Generate AI summary for master synthesis category
    const handleGenerateMasterAiSummary = (categoryKey: string) => {
        setIsMasterAiGenerating(true);
        router.post(
            `/synthesis/generate-master-ai-summary/${conversation.id}`,
            {
                category: categoryKey,
            },
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    if (page.props.masterSynthesis) {
                        setMasterSynthesisLocal(page.props.masterSynthesis);
                    }
                    setIsMasterAiGenerating(false);
                },
                onError: () => {
                    setIsMasterAiGenerating(false);
                },
            }
        );
    };

    // Save master synthesis for a specific category
    const handleSaveMasterCategory = (categoryKey: string) => {
        if (!masterSynthesisLocal?.id && currentUser.role !== 'admin' && currentUser.role !== 'drafter') {
            return;
        }

        // Track active editing category
        setActiveEditingCategory(categoryKey);
        setTimeout(() => setActiveEditingCategory(null), 3000);

        const fieldMap: Record<string, string> = {
            convergence: 'convergence_text',
            matters_for_consideration: 'consideration_text',
            proposals: 'proposals_text',
            others: 'other_text',
        };

        const editor = masterEditors[categoryKey];
        const updateData: Record<string, string | null> = {
            [fieldMap.convergence]: masterEditors.convergence.manual_content || null,
            [fieldMap.matters_for_consideration]: masterEditors.matters_for_consideration.manual_content || null,
            [fieldMap.proposals]: masterEditors.proposals.manual_content || null,
            [fieldMap.others]: masterEditors.others.manual_content || null,
        };

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

    // Save all master synthesis categories at once
    const handleSaveAllMasterCategories = () => {
        if (!masterSynthesisLocal?.id && currentUser.role !== 'admin' && currentUser.role !== 'drafter') {
            return;
        }

        const updateData: Record<string, string | null> = {
            convergence_text: masterEditors.convergence.manual_content || null,
            consideration_text: masterEditors.matters_for_consideration.manual_content || null,
            proposals_text: masterEditors.proposals.manual_content || null,
            other_text: masterEditors.others.manual_content || null,
        };

        router.post(
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

    // Update master editor content for a category
    const updateMasterEditor = (categoryKey: string, field: 'ai_content' | 'manual_content', value: string) => {
        setMasterEditors(prev => ({
            ...prev,
            [categoryKey]: {
                ...prev[categoryKey],
                [field]: value,
            },
        }));
    };

    // Toggle AI content visibility for a category
    const toggleAiContent = (categoryKey: string) => {
        setShowAiContent(prev => ({
            ...prev,
            [categoryKey]: !prev[categoryKey],
        }));
    };

    // Get master synthesis text for a category (from editable input state)
    const getMasterText = (category: string): string => {
        switch (category) {
            case 'convergence': return masterEditors.convergence.manual_content;
            case 'consideration': return masterEditors.matters_for_consideration.manual_content;
            case 'proposals': return masterEditors.proposals.manual_content;
            case 'others': return masterEditors.others.manual_content;
            default: return '';
        }
    };

    // Check if current user can edit (admin or drafter role)
    const canEditMaster = currentUser.role === 'admin' || currentUser.role === 'drafter';

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
                <div className="grid gap-4 md:grid-cols-4">
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
                                    const contributions = buildCategoryContributions(category.key);

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
                                
                                // Separate AI-only and manual submissions
                                const aiOnlySubmissions = categorySubmissions.filter(
                                    (s) => s.aiText && s.aiText.trim() && !s.text?.trim()
                                );
                                const manualSubmissions = categorySubmissions.filter(
                                    (s) => s.text && s.text.trim()
                                );
                                const mixedSubmissions = categorySubmissions.filter(
                                    (s) => s.aiText && s.aiText.trim() && s.text && s.text.trim()
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

                                        {/* ─── AI-Generated Contributions Subsection ─ */}
                                        {aiOnlySubmissions.length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-blue-700 dark:text-blue-300 mb-3 flex items-center gap-2">
                                                    <Bot className="h-4 w-4" />
                                                    AI-Assisted Contributions ({aiOnlySubmissions.length})
                                                </h4>
                                                <div className="space-y-3">
                                                    {aiOnlySubmissions.map((submission) => (
                                                        <div
                                                            key={submission.id}
                                                            className={`border ${category.borderColor} rounded-lg p-4 ${category.bgColor}`}
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
                                                                        <Bot className="h-3 w-3 mr-1" /> AI-Generated
                                                                    </Badge>
                                                                    {submission.submission_number && submission.submission_number > 1 && (
                                                                        <Badge variant="outline" className="text-xs">
                                                                            Submission #{submission.submission_number}
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                                {submission.submitted_at && (
                                                                    <span className="text-xs text-muted-foreground">
                                                                        {new Date(submission.submitted_at).toLocaleString()}
                                                                    </span>
                                                                )}
                                                            </div>
                                                            <div className="ml-8 mt-2">
                                                                {renderContentText(submission.aiText, true)}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* ─── Mixed AI + Manual Contributions Subsection */}
                                        {mixedSubmissions.length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-purple-700 dark:text-purple-300 mb-3 flex items-center gap-2">
                                                    <Users className="h-4 w-4" />
                                                    AI-Assisted with Manual Edits ({mixedSubmissions.length})
                                                </h4>
                                                <div className="space-y-3">
                                                    {mixedSubmissions.map((submission) => (
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
                                                                    <Badge variant="secondary" className="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                                                                        <Bot className="h-3 w-3 mr-1" /> AI-Assisted
                                                                    </Badge>
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
                                                            
                                                            {/* AI Content (collapsible) */}
                                                            {submission.aiText && submission.aiText.trim() && (
                                                                <div className="ml-8 mt-2">
                                                                    <button
                                                                        onClick={() => toggleAiContent(`mixed-${submission.id}`)}
                                                                        className="text-xs text-muted-foreground hover:text-primary flex items-center gap-1 mb-1"
                                                                    >
                                                                        {showAiContent[`mixed-${submission.id}`] ? <EyeOff className="h-3 w-3" /> : <Eye className="h-3 w-3" />}
                                                                        Toggle AI Source Content
                                                                    </button>
                                                                    {showAiContent[`mixed-${submission.id}`] && (
                                                                        <div className="bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-md p-3 mb-2">
                                                                            <p className="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1">AI-Generated Source:</p>
                                                                            {renderContentText(submission.aiText, true)}
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            )}
                                                            
                                                            {/* Manual Edits */}
                                                            <div className="ml-8 mt-2">
                                                                <p className="text-xs font-medium text-green-700 dark:text-green-300 mb-1">Manual Edits:</p>
                                                                {renderContentText(submission.text, false)}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* ─── Manual Contributions Subsection ───── */}
                                        {manualSubmissions.length > 0 && (
                                            <div>
                                                <h4 className="text-sm font-medium text-muted-foreground mb-3 flex items-center gap-2">
                                                    <Users className="h-4 w-4" />
                                                    Manual Contributions ({manualSubmissions.length})
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
                                                                    {submission.submission_number && submission.submission_number > 1 && (
                                                                        <Badge variant="outline" className="text-xs">
                                                                            Submission #{submission.submission_number}
                                                                        </Badge>
                                                                    )}
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

                                        {/* ─── Custom Queries (AI-Generated Others) ─── */}
                                        {category.key === 'others' && (() => {
                                            const submissionsWithCustomQueries = submissionsLocal.filter(
                                                (s) => s.custom_queries && s.custom_queries.length > 0
                                            );
                                            
                                            if (submissionsWithCustomQueries.length === 0) {
                                                return null;
                                            }
                                            
                                            return (
                                                <div>
                                                    <h4 className="text-sm font-medium text-violet-700 dark:text-violet-300 mb-3 flex items-center gap-2">
                                                        <Bot className="h-4 w-4" />
                                                        AI-Generated Others (Custom Queries)
                                                    </h4>
                                                    <div className="space-y-4">
                                                        {submissionsWithCustomQueries.map((submission) =>
                                                            submission.custom_queries.map((query) => (
                                                                <div
                                                                    key={query.id}
                                                                    className="border border-violet-200 dark:border-violet-800 rounded-lg p-4 bg-violet-50/30 dark:bg-violet-950/10"
                                                                >
                                                                    <div className="flex items-center justify-between mb-2">
                                                                        <div className="flex items-center gap-2">
                                                                            <div className="size-6 rounded-full bg-violet-500 flex items-center justify-center text-xs font-medium text-white">
                                                                                {getInitials(submission.user.name)}
                                                                            </div>
                                                                            <span className="text-sm font-medium">
                                                                                {submission.user.name}
                                                                            </span>
                                                                            <Badge variant="secondary" className="text-xs bg-violet-100 dark:bg-violet-900/30 text-violet-700 dark:text-violet-300">
                                                                                <Bot className="h-3 w-3 mr-1" /> AI Query
                                                                            </Badge>
                                                                        </div>
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {new Date(query.created_at).toLocaleString()}
                                                                        </span>
                                                                    </div>
                                                                    <p className="text-sm font-medium text-muted-foreground mt-2 mb-1">
                                                                        Prompt: {query.prompt}
                                                                    </p>
                                                                    <ul className="space-y-1 ml-8">
                                                                        {query.response.map((point: string, i: number) => (
                                                                            <li key={i} className="flex items-start gap-2 text-sm">
                                                                                <span className="text-violet-600 dark:text-violet-400 mt-0.5 flex-shrink-0">•</span>
                                                                                <span>{point}</span>
                                                                            </li>
                                                                        ))}
                                                                    </ul>
                                                                </div>
                                                            ))
                                                        )}
                                                    </div>
                                                </div>
                                            );
                                        })()}

                                        {/* ─── Master Synthesis Editor ────────── */}
                                        <div className={`pt-4 border-t ${isEditingThisCategory ? 'border-primary/30 bg-primary/5' : ''}`}>
                                            <h4 className="text-sm font-medium mb-3 flex items-center justify-between">
                                                <span className="flex items-center gap-2">
                                                    <FileText className="h-4 w-4" />
                                                    Master Synthesis ({category.label})
                                                </span>
                                                <Button
                                                    size="sm"
                                                    variant="default"
                                                    onClick={() => handleSaveMasterCategory(category.key)}
                                                >
                                                    <CheckCircle2 className="h-3 w-3 mr-1" /> Save
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
                                                        value={masterEditors[category.key]?.manual_content ?? ''}
                                                        onChange={(e) => updateMasterEditor(category.key, 'manual_content', e.target.value)}
                                                        rows={10}
                                                        className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                    />
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        Manually edit the master synthesis for this category.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        {/* ─── AI Generate button (for draft users) ─ */}
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

                {/* ─── Save All Master Synthesis (for admin/drafter) ───── */}
                {canEditMaster && masterSynthesisLocal?.id && (
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
                                <Button onClick={handleSaveAllMasterCategories} className="flex items-center gap-2">
                                    <CheckCircle2 className="size-4" />
                                    Save All Categories
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
                        <CardContent className="pt-6 space-y-4">
                            {CATEGORIES.map((category) => (
                                <div key={category.key}>
                                    <h4 className={`text-sm font-medium mb-2 flex items-center gap-2 ${category.color}`}>
                                        {category.icon && <category.icon className="h-4 w-4" />}
                                        {category.label}
                                    </h4>
                                    <div className="space-y-2">
                                        {masterEditors[category.key]?.ai_content && masterEditors[category.key].ai_content.trim() && (
                                            <div>
                                                <p className="text-xs font-medium text-blue-700 dark:text-blue-300 mb-1">AI Reference:</p>
                                                <textarea
                                                    value={masterEditors[category.key].ai_content}
                                                    rows={4}
                                                    className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                                />
                                            </div>
                                        )}
                                        <div>
                                            <p className="text-xs font-medium text-green-700 dark:text-green-300 mb-1">Master Content:</p>
                                            <textarea
                                                value={masterEditors[category.key]?.manual_content ?? ''}
                                                rows={6}
                                                className="w-full min-h-[60px] rounded-md border border-input bg-card px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                            />
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    )}
                    {showAllMasterSynthesis && (!masterSynthesisLocal || !masterSynthesisLocal.id) && (
                        <CardContent className="pt-6 text-center text-muted-foreground text-sm bg-muted/30 rounded-lg border border-dashed">
                            <FileText className="h-8 w-8 mx-auto text-muted-foreground/30 mb-2" />
                            <p>No master synthesis has been compiled yet.</p>
                        </CardContent>
                    )}
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
