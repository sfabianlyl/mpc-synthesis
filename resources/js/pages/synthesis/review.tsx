import { Head, Link, router } from '@inertiajs/react';
import { CheckCircle2, XCircle, Loader2, FileText, Users, Bot, Trash2, FileDown } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

interface SynthesisCustomQuery {
    id: number;
    prompt: string;
    response: string[];
    provider: string | null;
    created_at: string;
}

interface SynthesisReport {
    id: number;
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    ai_convergence_text: string | null;
    ai_consideration_text: string | null;
    ai_proposals_text: string | null;
    ai_source_group_ids: number[] | null;
    ai_generated_at: string | null;
    llm_generated: boolean;
    status: string;
    custom_queries: SynthesisCustomQuery[];
}

interface PageProps {
    conversation: any;
    groups: any[];
    responses: any[];
    report: SynthesisReport | null;
    success?: string;
    errors?: Record<string, string>;
    consensusSubmissions: Record<number, any[]>;
    provider?: string;
}

export default function Review({
    conversation,
    groups,
    responses,
    report,
    success: initialSuccess,
    errors: initialErrors,
    consensusSubmissions = {},
}: PageProps) {
    const [isAnalyzing, setIsAnalyzing] = useState(false);
    const [analysisStatus, setAnalysisStatus] = useState<'idle' | 'success' | 'error'>('idle');
    const [analysisMessage, setAnalysisMessage] = useState('');
    const [convergenceText, setConvergenceText] = useState(report?.convergence_text ?? '');
    const [considerationText, setConsiderationText] = useState(report?.consideration_text ?? '');
    const [proposalsText, setProposalsText] = useState(report?.proposals_text ?? '');
    const [selectedGroupIds, setSelectedGroupIds] = useState<Set<number>>(
        new Set(groups.map(g => g.id))
    );
    // New state for custom prompt
    const [customPrompt, setCustomPrompt] = useState('');
    const [isCustomQuerying, setIsCustomQuerying] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Refs for batch checkbox indeterminate state
    const batchCheckboxRefs = useRef<Map<number, HTMLInputElement>>(new Map());

    useEffect(() => {
        if (initialSuccess) {
            setAnalysisStatus('success');
            setAnalysisMessage(initialSuccess);
            setTimeout(() => {
                setAnalysisStatus('idle');
                setAnalysisMessage('');
            }, 5000);
        }

        if (initialErrors?.analysis) {
            setAnalysisStatus('error');
            setAnalysisMessage(initialErrors.analysis);
            setTimeout(() => {
                setAnalysisStatus('idle');
                setAnalysisMessage('');
            }, 5000);
        }
    }, [initialSuccess, initialErrors]);

    // Sync local state when report prop changes (e.g., after Inertia re-render)
    useEffect(() => {
        if (report) {
            setConvergenceText(report.convergence_text ?? '');
            setConsiderationText(report.consideration_text ?? '');
            setProposalsText(report.proposals_text ?? '');
        }
    }, [report]);

    // Update indeterminate state for batch checkboxes when selectedGroupIds changes
    useEffect(() => {
        const groupIds = getUniqueGroupIds();
        const batches = [];

        for (let i = 0; i < groupIds.length; i += 5) {
            batches.push(groupIds.slice(i, i + 5));
        }

        batches.forEach((batchGroupIds, batchIndex) => {
            const ref = batchCheckboxRefs.current.get(batchIndex);

            if (ref) {
                const allInBatchSelected = batchGroupIds.every(id => selectedGroupIds.has(id));
                const someInBatchSelected = batchGroupIds.some(id => selectedGroupIds.has(id));
                ref.indeterminate = someInBatchSelected && !allInBatchSelected;
            }
        });
    }, [selectedGroupIds]);

    // Reset all inputs to clear them after submission
    const resetInputs = () => {
        setConvergenceText('');
        setConsiderationText('');
        setProposalsText('');
        setCustomPrompt('');
    };

    // Custom LLM query handler
    const handleCustomQuery = () => {
        if (!customPrompt.trim() || !conversation.id) {
            return;
        }

        setIsCustomQuerying(true);
        router.post(
            `/synthesis/custom-query/${conversation.id}`,
            {
                prompt: customPrompt,
                selected_group_ids: Array.from(selectedGroupIds),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setCustomPrompt('');
                    setIsCustomQuerying(false);
                },
                onError: () => setIsCustomQuerying(false),
            }
        );
    };

    // Delete custom query handler
    const handleDeleteCustomQuery = (queryId: number) => {
        router.delete(`/synthesis/custom-query/${queryId}`, {
            preserveScroll: true,
        });
    };

    // Submit synthesis handler - packages all data and clears inputs after submission
    const handleSubmitSynthesis = () => {
        if (!conversation.id || !report?.id) {
            return;
        }

        setIsSubmitting(true);

        // Gather custom queries from the report (these are already linked to the report)
        const customQueries = report.custom_queries ?? [];

        router.post(
            `/synthesis/submit-synthesis/${conversation.id}`,
            {
                // Manual inputs by category
                convergence_text: convergenceText,
                consideration_text: considerationText,
                proposals_text: proposalsText,
                // Others category: compile all custom query prompts and their responses
                other_text: customQueries.length > 0
                    ? customQueries.map(q => `Prompt: ${q.prompt}\nResponse: ${q.response.join('\n')}`).join('\n\n---\n\n')
                    : null,
                // Include AI data that will be part of the final submission
                ai_convergence_text: report.ai_convergence_text ?? '',
                ai_consideration_text: report.ai_consideration_text ?? '',
                ai_proposals_text: report.ai_proposals_text ?? '',
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAnalysisStatus('success');
                    setAnalysisMessage('Synthesis submitted successfully.');

                    // Clear all inputs after successful submission
                    resetInputs();

                    setIsSubmitting(false);

                    setTimeout(() => {
                        setAnalysisStatus('idle');
                        setAnalysisMessage('');
                    }, 5000);
                },
                onError: () => {
                    setAnalysisStatus('error');
                    setAnalysisMessage('Failed to submit synthesis. Please try again.');
                    setIsSubmitting(false);
                },
            }
        );
    };

    const handleTriggerLLM = () => {
        setIsAnalyzing(true);
        setAnalysisStatus('idle');
        setAnalysisMessage('');
        router.post(
            `/synthesis/analyze/${conversation.id}`,
            {
                selected_group_ids: Array.from(selectedGroupIds),
                // Send current manual text values so they get saved alongside AI output
                convergence_text: convergenceText,
                consideration_text: considerationText,
                proposals_text: proposalsText,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: (page: any) => {
                    // Read success message and report from page props (passed via session flash from back()->with())
                    const successMessage = page.props.success;
                    const newReport = page.props.report;

                    if (newReport) {
                        setConvergenceText(newReport.convergence_text ?? '');
                        setConsiderationText(newReport.consideration_text ?? '');
                        setProposalsText(newReport.proposals_text ?? '');
                    }

                    setIsAnalyzing(false);
                    setAnalysisStatus('success');
                    setAnalysisMessage(
                        successMessage ||
                        'LLM analysis completed successfully using ' + (page.props?.provider || 'unknown') + ' provider.'
                    );
                },
                onError: (errors) => {
                    setIsAnalyzing(false);
                    setAnalysisStatus('error');
                    setAnalysisMessage(errors.analysis || 'Analysis failed. Please try again.');
                },
            }
        );
    };

    const handleSaveReport = () => {
        if (!report?.id) {
            return;
        }

        router.patch(
            `/synthesis/report/${report.id}`,
            {
                convergence_text: convergenceText,
                consideration_text: considerationText,
                proposals_text: proposalsText,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAnalysisStatus('success');
                    setAnalysisMessage('Report saved successfully.');
                    setTimeout(() => {
                        setAnalysisStatus('idle');
                        setAnalysisMessage('');
                    }, 3000);
                },
                onError: () => {
                    setAnalysisStatus('error');
                    setAnalysisMessage('Failed to save report. Please try again.');
                },
            }
        );
    };

    const handleStatusUpdate = (status: 'draft' | 'reviewed' | 'submitted') => {
        if (!report?.id) {
            return;
        }

        router.patch(
            `/synthesis/report/${report.id}`,
            { status },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setAnalysisStatus('success');
                    setAnalysisMessage(`Report marked as ${status}.`);
                    setTimeout(() => {
                        setAnalysisStatus('idle');
                        setAnalysisMessage('');
                    }, 3000);
                },
                onError: () => {
                    setAnalysisStatus('error');
                    setAnalysisMessage('Failed to update status.');
                },
            }
        );
    };

    // Toggle a single group selection
    const toggleGroup = (groupId: number) => {
        setSelectedGroupIds(prev => {
            const newSet = new Set(prev);

            if (newSet.has(groupId)) {
                newSet.delete(groupId);
            } else {
                newSet.add(groupId);
            }

            return newSet;
        });
    };

    // Toggle all groups in a batch
    const toggleBatch = (batchGroupIds: number[]) => {
        setSelectedGroupIds(prev => {
            const newSet = new Set(prev);
            const allSelected = batchGroupIds.every(id => newSet.has(id));

            if (allSelected) {
                batchGroupIds.forEach(id => newSet.delete(id));
            } else {
                batchGroupIds.forEach(id => newSet.add(id));
            }

            return newSet;
        });
    };

    // Check if all groups are selected
    const isAllSelected = () => {
        return groups.every(g => selectedGroupIds.has(g.id));
    };

    // Get unique group IDs sorted
    const getUniqueGroupIds = () => {
        return [...new Set(groups.map(g => g.id))].sort((a, b) => a - b);
    };

    // Group batches of 5
    const getGroupBatches = () => {
        const groupIds = getUniqueGroupIds();
        const batches = [];

        for (let i = 0; i < groupIds.length; i += 5) {
            batches.push(groupIds.slice(i, i + 5));
        }

        return batches;
    };

    // Collect all consensus submissions from the groups (filtered by selectedGroupIds)
    const allConsensusSubmissions: any[] = [];

    for (const group of groups) {
        if (!selectedGroupIds.has(group.id)) {
            continue;
        }

        const submissions = consensusSubmissions[group.id] || [];

        for (const submission of submissions) {
            allConsensusSubmissions.push({
                ...submission,
                groupName: group.name,
                groupId: group.id,
            });
        }
    }

    // Render AI text as bullet points
    const renderAiText = (text: string | null): React.ReactNode => {
        if (!text || !text.trim()) {
            return null;
        }

        const bullets = text.split('\n').filter(line => line.trim());

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

    return (
        <>
            <Head title={`Review - ${conversation?.title || 'Synthesis Review'}`} />
            <div className="p-8 max-w-[1920px] mx-auto">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold">{conversation?.title}</h1>
                        <p className="text-muted-foreground mt-1">Conversation ID: {conversation?.id}</p>
                        {report && (
                            <Badge
                                variant={report.status === 'submitted' ? 'default' : 'secondary'}
                                className="mt-2"
                            >
                                Status: {report.status}
                                {report.llm_generated && (
                                    <span className="ml-1 text-xs">(LLM Generated)</span>
                                )}
                            </Badge>
                        )}
                    </div>
                    <div className="flex gap-2 pb-2">
                        <Link href={`/synthesis/review-page/${conversation.id}`}>
                            <Button variant="outline" className="flex items-center gap-2">
                                <Users className="size-4" /> Collaborative Review
                            </Button>
                        </Link>
                        {report && (
                            <Link href={`/synthesis/export-booklet/${conversation.id}`}>
                                <Button variant="outline" className="flex items-center gap-2">
                                    <FileDown className="size-4" /> Export Booklet
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Analysis Status Indicator */}
                {analysisStatus !== 'idle' && (
                    <div
                        className={`mb-6 flex items-center gap-2 p-4 rounded-lg border ${
                            analysisStatus === 'success'
                                ? 'bg-green-50 border-green-200 text-green-800'
                                : 'bg-red-50 border-red-200 text-red-800'
                        }`}
                    >
                        {analysisStatus === 'success' ? (
                            <CheckCircle2 className="h-5 w-5" />
                        ) : (
                            <XCircle className="h-5 w-5" />
                        )}
                        <span>{analysisMessage}</span>
                    </div>
                )}

                {/* 2-Column Layout */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Left Column - Submitted Consensus */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    Submitted Consensus
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Group Selection Checkboxes */}
                                <div className="border-b pb-4">
                                    <label className="text-sm font-medium mb-3 block flex items-center gap-2">
                                        <Users className="h-4 w-4 text-muted-foreground" />
                                        Filter by Groups
                                    </label>
                                    <div className="space-y-2">
                                        {/* Select All checkbox */}
                                        <div className="flex items-center gap-2 pb-2 border-b">
                                            <input
                                                type="checkbox"
                                                checked={isAllSelected()}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setSelectedGroupIds(new Set(getUniqueGroupIds()));
                                                    } else {
                                                        setSelectedGroupIds(new Set());
                                                    }
                                                }}
                                                className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                            />
                                            <span className="text-sm font-medium">Select All Groups</span>
                                            <Badge variant="secondary" className="text-xs ml-auto">
                                                {selectedGroupIds.size} / {groups.length}
                                            </Badge>
                                        </div>
                                        {/* Batch checkboxes */}
                                        {getGroupBatches().map((batchGroupIds, batchIndex) => {
                                            const allInBatchSelected = batchGroupIds.every(id => selectedGroupIds.has(id));
                                            const firstGroupId = batchGroupIds[0];
                                            const lastGroupId = batchGroupIds[batchGroupIds.length - 1];
                                            const count = batchGroupIds.length;

                                            return (
                                                <div key={`batch-${batchIndex}`} className="flex items-center gap-2 py-1">
                                                    <input
                                                        ref={(el) => {
                                                            if (el) {
                                                                batchCheckboxRefs.current.set(batchIndex, el);
                                                            } else {
                                                                batchCheckboxRefs.current.delete(batchIndex);
                                                            }
                                                        }}
                                                        type="checkbox"
                                                        checked={allInBatchSelected}
                                                        onChange={(e) => {
                                                            e.stopPropagation();
                                                            toggleBatch(batchGroupIds);
                                                        }}
                                                        className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                                                    />
                                                    <span className="text-sm">
                                                        Group {firstGroupId}-{lastGroupId} ({count})
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>

                                {allConsensusSubmissions.length > 0 ? (
                                    allConsensusSubmissions.map((submission: any, index: number) => (
                                        <div
                                            key={`${submission.group_id || submission.id}-${index}`}
                                            className="border rounded-lg p-4 bg-card"
                                        >
                                            <div className="flex justify-between items-start mb-2">
                                                <div>
                                                    <p className="font-medium text-sm">
                                                        {submission.group_name || submission.facilitator_name || 'Anonymous'}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        Group: {submission.groupName}
                                                    </p>
                                                </div>
                                                <Badge
                                                    variant={submission.is_submitted ? 'default' : 'secondary'}
                                                    className="text-xs"
                                                >
                                                    {submission.is_submitted ? (
                                                        <span className="flex items-center gap-1">
                                                            <CheckCircle2 className="h-3 w-3" /> Submitted
                                                        </span>
                                                    ) : (
                                                        <span className="flex items-center gap-1">
                                                            <XCircle className="h-3 w-3" /> Pending
                                                        </span>
                                                    )}
                                                </Badge>
                                            </div>
                                            {submission.submitted_at && (
                                                <p className="text-xs text-muted-foreground mb-2">
                                                    Submitted: {new Date(submission.submitted_at).toLocaleString()}
                                                </p>
                                            )}
                                            <p className="text-sm whitespace-pre-wrap bg-muted/50 p-3 rounded-md">
                                                {submission.consensus_text}
                                            </p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-muted-foreground text-center py-8">
                                        No consensus submissions from selected groups.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Submission Status Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Submission Status</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {(() => {
                                    const totalGroups = groups.length;
                                    const submittedGroups = groups.filter((group: any) => {
                                        const submissions = consensusSubmissions[group.id] || [];
                                        return submissions.some((s: any) => s.is_submitted);
                                    }).length;

                                    return (
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Consensus Submissions</span>
                                            <Badge variant="outline" className="text-base font-semibold">
                                                {submittedGroups} / {totalGroups} Submitted
                                            </Badge>
                                        </div>
                                    );
                                })()}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column - Synthesis Panel */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Synthesis Panel</CardTitle>
                                    <Button
                                        onClick={handleTriggerLLM}
                                        disabled={isAnalyzing || selectedGroupIds.size === 0}
                                        variant="outline"
                                        size="sm"
                                    >
                                        {isAnalyzing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                        {isAnalyzing ? 'Processing...' : 'Trigger LLM Synthesis'}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Convergence Section */}
                                <div className="space-y-2">
                                    {/* AI-generated field (read-only, cannot be edited) */}
                                    {report?.ai_convergence_text && report.ai_convergence_text.trim() && (
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <label className="text-sm font-medium text-blue-700 dark:text-blue-300">
                                                    Convergence (AI)
                                                </label>
                                                <Badge variant="secondary" className="text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300">
                                                    <Bot className="h-3 w-3 mr-1" /> AI Generated
                                                </Badge>
                                            </div>
                                            <div className="bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800 rounded-md p-3 min-h-[60px]">
                                                {renderAiText(report.ai_convergence_text)}
                                            </div>
                                        </div>
                                    )}
                                    {/* User field (editable input) */}
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">
                                            Convergence (Your Input)
                                        </label>
                                        <Textarea
                                            value={convergenceText}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                                setConvergenceText(e.target.value)
                                            }
                                            rows={6}
                                            placeholder="Points of agreement..."
                                        />
                                    </div>
                                </div>

                                {/* Matters for Consideration Section */}
                                <div className="space-y-2">
                                    {/* AI-generated field (read-only, cannot be edited) */}
                                    {report?.ai_consideration_text && report.ai_consideration_text.trim() && (
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <label className="text-sm font-medium text-purple-700 dark:text-purple-300">
                                                    Matters for Consideration (AI)
                                                </label>
                                                <Badge variant="secondary" className="text-xs bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                                                    <Bot className="h-3 w-3 mr-1" /> AI Generated
                                                </Badge>
                                            </div>
                                            <div className="bg-purple-50/50 dark:bg-purple-950/20 border border-purple-200 dark:border-purple-800 rounded-md p-3 min-h-[60px]">
                                                {renderAiText(report.ai_consideration_text)}
                                            </div>
                                        </div>
                                    )}
                                    {/* User field (editable input) */}
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">
                                            Matters for Consideration (Your Input)
                                        </label>
                                        <Textarea
                                            value={considerationText}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                                setConsiderationText(e.target.value)
                                            }
                                            rows={6}
                                            placeholder="Areas of concern or unresolved questions..."
                                        />
                                    </div>
                                </div>

                                {/* Proposals Section */}
                                <div className="space-y-2">
                                    {/* AI-generated field (read-only, cannot be edited) */}
                                    {report?.ai_proposals_text && report.ai_proposals_text.trim() && (
                                        <div>
                                            <div className="flex items-center gap-2 mb-1">
                                                <label className="text-sm font-medium text-green-700 dark:text-green-300">
                                                    Proposals (AI)
                                                </label>
                                                <Badge variant="secondary" className="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                                    <Bot className="h-3 w-3 mr-1" /> AI Generated
                                                </Badge>
                                            </div>
                                            <div className="bg-green-50/50 dark:bg-green-950/20 border border-green-200 dark:border-green-800 rounded-md p-3 min-h-[60px]">
                                                {renderAiText(report.ai_proposals_text)}
                                            </div>
                                        </div>
                                    )}
                                    {/* User field (editable input) */}
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">
                                            Proposals (Your Input)
                                        </label>
                                        <Textarea
                                            value={proposalsText}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                                setProposalsText(e.target.value)
                                            }
                                            rows={6}
                                            placeholder="Concrete suggestions or recommendations..."
                                        />
                                    </div>
                                </div>

                                {/* Custom LLM Prompt Section */}
                                <div className="pt-4 border-t">
                                    <h3 className="text-sm font-medium mb-2">Custom LLM Prompt</h3>
                                    <div className="flex gap-2">
                                        <Textarea
                                            value={customPrompt}
                                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                                                setCustomPrompt(e.target.value)
                                            }
                                            rows={2}
                                            placeholder="Ask the LLM a specific question about the group responses..."
                                            className="flex-1"
                                        />
                                        <Button
                                            onClick={handleCustomQuery}
                                            disabled={!customPrompt.trim() || isCustomQuerying}
                                            variant="outline"
                                            className="self-start flex-shrink-0"
                                        >
                                            {isCustomQuerying && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                            Ask AI
                                        </Button>
                                    </div>
                                </div>

                            </CardContent>
                        </Card>

                        {/* Others Section - Custom Query Results */}
                        {report?.custom_queries && report.custom_queries.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Bot className="h-5 w-5" />
                                        Others
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {report.custom_queries.map((query) => (
                                        <div key={query.id} className="border rounded-lg p-4 bg-card">
                                            <div className="flex items-start justify-between mb-2">
                                                <div className="flex-1">
                                                    <p className="text-sm font-medium">{query.prompt}</p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {query.provider ? `via ${query.provider}` : 'Unknown provider'} · {new Date(query.created_at).toLocaleString()}
                                                    </p>
                                                </div>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-8 w-8 p-0 text-muted-foreground hover:text-red-600 ml-2 flex-shrink-0"
                                                    onClick={() => handleDeleteCustomQuery(query.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <ul className="space-y-1 mt-2">
                                                {query.response.map((point: string, i: number) => (
                                                    <li key={i} className="flex items-start gap-2 text-sm">
                                                        <span className="text-primary mt-0.5 flex-shrink-0">•</span>
                                                        <span>{point}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {/* Action Buttons */}
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex flex-col sm:flex-row gap-2">
                                    <Button onClick={handleSaveReport} className="flex-1">
                                        Save Draft
                                    </Button>

                                    <Button
                                        onClick={handleSubmitSynthesis}
                                        disabled={isSubmitting || !report?.id}
                                        className="flex-1"
                                    >
                                        {isSubmitting ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Submitting...
                                            </>
                                        ) : (
                                            'Submit Synthesis'
                                        )}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </>
    );
}
