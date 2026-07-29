import { Head, router, usePage } from '@inertiajs/react';
import { CheckCircle2, XCircle, Loader2, FileText, Users, Bot, FileDown } from 'lucide-react';
import { useState, useEffect, useMemo } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { debounce } from '@/lib/debounce';

interface GroupAssignment {
    id: number;
    name: string;
    responses: any[];
    consensusSubmission?: {
        is_submitted: boolean;
        consensus_text: string;
        submitted_at: string | null;
    } | null;
    note?: { id: number; text: string | null } | null;
}

interface Extraction {
    id: number;
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    provider: string | null;
    is_successful: boolean;
    extracted_at: string | null;
}

interface SynthesisDraft {
    id: number;
    convergence_text: string | null;
    consideration_text: string | null;
    proposals_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
}

interface PageProps {
    conversation: any;
    assignedGroups: GroupAssignment[];
    notes: Record<number, { id: number; text: string | null }>;
    draft: SynthesisDraft | null;
    extraction: Extraction | null;
    allConsensusSubmitted: boolean;
    consensusSubmissions: Record<number, any[]>;
    success?: string;
    canTrigger?: boolean;
}

export default function Review({
    conversation,
    assignedGroups,
    notes,
    draft,
    extraction,
    allConsensusSubmitted,
    consensusSubmissions,
    success: initialSuccess,
    canTrigger: initialCanTrigger,
}: PageProps) {
    const [isExtracting, setIsExtracting] = useState(false);
    const [isSynthesizing, setIsSynthesizing] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [analysisStatus, setAnalysisStatus] = useState<'idle' | 'success' | 'error'>('idle');
    const [analysisMessage, setAnalysisMessage] = useState('');
    const [extractionDisabled, setExtractionDisabled] = useState(false);

    // Local state for draft fields
    const [convergenceText, setConvergenceText] = useState(draft?.convergence_text ?? '');
    const [considerationText, setConsiderationText] = useState(draft?.consideration_text ?? '');
    const [proposalsText, setProposalsText] = useState(draft?.proposals_text ?? '');

    // Local state for notes per group
    const [groupNotes, setGroupNotes] = useState<Record<number, string>>(() => {
        const initial: Record<number, string> = {};
        assignedGroups.forEach((group) => {
            initial[group.id] = group.note?.text ?? '';
        });
        return initial;
    });

    useEffect(() => {
        if (initialSuccess) {
            setAnalysisStatus('success');
            setAnalysisMessage(initialSuccess);
            setTimeout(() => {
                setAnalysisStatus('idle');
                setAnalysisMessage('');
            }, 5000);
        }
    }, [initialSuccess]);

    // Check if manual extraction trigger should be disabled (already has successful extraction)
    useEffect(() => {
        if (!conversation?.id || extraction?.is_successful) {
            setExtractionDisabled(true);
            return;
        }

        // Use initial prop value if available, otherwise fetch from API
        if (initialCanTrigger !== undefined) {
            setExtractionDisabled(!initialCanTrigger);
            return;
        }

        router.get(`/synthesis/can-trigger-extraction/${conversation.id}`, {}, {
            preserveScroll: true,
            onSuccess: (page) => {
                const canTrigger = page.props.canTrigger;
                setExtractionDisabled(canTrigger === false);
            },
        });
    }, [conversation?.id, extraction?.is_successful, initialCanTrigger]);

    // Sync local state when draft prop changes
    useEffect(() => {
        if (draft) {
            setConvergenceText(draft.convergence_text ?? '');
            setConsiderationText(draft.consideration_text ?? '');
            setProposalsText(draft.proposals_text ?? '');
        }
    }, [draft]);

    // Debounced save handlers with 2s delay
    const debouncedSaveDraft = useMemo(
        () => debounce((field: string, value: string) => {
            if (!conversation?.id) return;
            router.patch(`/synthesis/draft/${conversation.id}`, { [field]: value }, {
                preserveScroll: true,
            });
        }, 2000),
        [conversation?.id]
    );

    const debouncedSaveNote = useMemo(
        () => debounce((groupId: number, text: string) => {
            if (!conversation?.id) return;
            router.post(`/synthesis/consensus-note/${conversation.id}`, {
                group_id: groupId,
                notes: text,
            }, {
                preserveScroll: true,
            });
        }, 2000),
        [conversation?.id]
    );

    // Handle draft field changes with autosave
    const handleDraftChange = (field: 'convergence_text' | 'consideration_text' | 'proposals_text', value: string) => {
        if (field === 'convergence_text') setConvergenceText(value);
        if (field === 'consideration_text') setConsiderationText(value);
        if (field === 'proposals_text') setProposalsText(value);
        debouncedSaveDraft(field, value);
    };

    // Handle note changes with autosave
    const handleNoteChange = (groupId: number, text: string) => {
        setGroupNotes((prev) => ({ ...prev, [groupId]: text }));
        debouncedSaveNote(groupId, text);
    };

    // Trigger LLM extraction
    const handleTriggerExtraction = () => {
        if (!conversation?.id) return;
        setIsExtracting(true);
        router.post(`/synthesis/extract/${conversation.id}`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setAnalysisStatus('success');
                setAnalysisMessage('Extraction job dispatched. Results will appear shortly.');
                setTimeout(() => setAnalysisStatus('idle'), 3000);
            },
            onError: () => {
                setAnalysisStatus('error');
                setAnalysisMessage('Failed to trigger extraction.');
            },
        });
    };

    // Trigger LLM synthesis (from notes)
    const handleTriggerSynthesis = () => {
        if (!conversation?.id || !draft?.id) return;
        setIsSynthesizing(true);
        // Use the existing ai-synthesis endpoint with draft data
        router.post(`/synthesis/ai-synthesis/${conversation.id}`, {
            selected_group_ids: [],
            convergence_text: convergenceText,
            consideration_text: considerationText,
            proposals_text: proposalsText,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setAnalysisStatus('success');
                setAnalysisMessage('Synthesis generated successfully.');
                setTimeout(() => setAnalysisStatus('idle'), 3000);
            },
            onError: () => {
                setAnalysisStatus('error');
                setAnalysisMessage('Failed to generate synthesis.');
            },
        });
    };

    // Submit draft
    const handleSubmitDraft = () => {
        if (!conversation?.id) return;
        setIsSubmitting(true);
        router.post(`/synthesis/submit-draft/${conversation.id}`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setAnalysisStatus('success');
                setAnalysisMessage('Synthesis submitted successfully.');
            },
            onError: () => {
                setAnalysisStatus('error');
                setAnalysisMessage('Failed to submit synthesis.');
            },
        });
    };

    // Render extraction text as bullet points
    const renderExtractionText = (text: string | null): React.ReactNode => {
        if (!text || !text.trim()) return <p className="text-muted-foreground text-sm italic">No content</p>;
        const bullets = text.split('\n').filter(line => line.trim());
        if (bullets.length <= 1) {
            return <p className="text-sm whitespace-pre-wrap">{text}</p>;
        }
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

    const isDraftSubmitted = draft?.is_submitted ?? false;

    return (
        <>
            <Head title={`Review - ${conversation?.title || 'Synthesis Review'}`} />
            <div className="p-8 max-w-[1920px] mx-auto">
                {/* Header */}
                <div className="mb-6 flex items-start justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold">{conversation?.title}</h1>
                        <p className="text-muted-foreground mt-1">Conversation ID: {conversation?.id}</p>
                        {draft && (
                            <Badge
                                variant={isDraftSubmitted ? 'default' : 'secondary'}
                                className="mt-2"
                            >
                                {isDraftSubmitted ? 'Submitted' : 'Draft'}
                            </Badge>
                        )}
                    </div>
                    <div className="flex gap-2 pb-2">
                        <a href={`/synthesis/collaborate/${conversation.id}`}>
                            <Button variant="outline" className="flex items-center gap-2">
                                <Users className="size-4" /> Collaborative Review
                            </Button>
                        </a>
                        {draft && (
                            <a href={`/synthesis/export-booklet/${conversation.id}`}>
                                <Button variant="outline" className="flex items-center gap-2">
                                    <FileDown className="size-4" /> Export Booklet
                                </Button>
                            </a>
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

                {/* ─── Left Column: Submitted Consensus + Notes ─────────────── */}
                <section className="space-y-4">
                    <h2 className="text-xl font-semibold">Submitted Consensus</h2>
                    <div className="space-y-4">
                        {assignedGroups.map((group) => (
                            <Card key={group.id}>
                                <CardHeader className="pb-3 border-b">
                                    <div className="flex justify-between items-center">
                                        <CardTitle className="text-base font-semibold">Group {group.name}</CardTitle>
                                        <Badge
                                            variant={group.consensusSubmission?.is_submitted ? 'default' : 'secondary'}
                                            className="text-xs"
                                        >
                                            {group.consensusSubmission?.is_submitted ? (
                                                <span className="flex items-center gap-1">
                                                    <CheckCircle2 className="h-3.5 w-3.5" /> Submitted
                                                </span>
                                            ) : (
                                                <span className="flex items-center gap-1">
                                                    <XCircle className="h-3.5 w-3.5" /> Pending
                                                </span>
                                            )}
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent className="pt-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        {/* Left Side: Consensus Text */}
                                        <div className="space-y-1.5">
                                            <label className="text-xs font-medium text-muted-foreground uppercase tracking-wider block">Group Consensus</label>
                                            {group.consensusSubmission?.is_submitted ? (
                                                <p className="text-sm whitespace-pre-wrap bg-muted/50 border rounded-md p-4 min-h-[140px] max-h-[300px] overflow-y-auto leading-relaxed">
                                                    {group.consensusSubmission.consensus_text}
                                                </p>
                                            ) : (
                                                <div className="bg-muted/30 border border-dashed rounded-md p-4 min-h-[140px] flex items-center justify-center">
                                                    <p className="text-muted-foreground text-sm italic">Pending group submission...</p>
                                                </div>
                                            )}
                                        </div>

                                        {/* Right Side: Notes */}
                                        <div className="space-y-1.5">
                                            <label className="text-xs font-medium text-muted-foreground uppercase tracking-wider block">Your Notes</label>
                                            <Textarea
                                                value={groupNotes[group.id] ?? ''}
                                                onChange={(e) => handleNoteChange(group.id, e.target.value)}
                                                disabled={isDraftSubmitted}
                                                rows={6}
                                                placeholder="Write your notes or observations here..."
                                                className="text-sm min-h-[140px]"
                                            />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </div>

                    {/* Submission Status Summary */}
                    <Card>
                        <CardContent className="pt-6">
                            {(() => {
                                const totalGroups = assignedGroups.length;
                                const submittedGroups = assignedGroups.filter(
                                    (g) => g.consensusSubmission?.is_submitted
                                ).length;

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
                </section>

                {/* ─── Right Column: Synthesis Panel ──────────────────────── */}
                <section className="space-y-4 pt-6 border-t">
                    <h2 className="text-xl font-semibold">Synthesis Panel</h2>
                    <div className="grid grid-cols-2 gap-4">
                        {/* Left Section: LLM Extractions (Read-Only) */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Bot className="h-5 w-5" />
                                    LLM Extraction
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Trigger button (disabled if successful extraction exists or API says disable) */}
                                {!extraction?.is_successful && !extractionDisabled && (
                                    <Button
                                        onClick={handleTriggerExtraction}
                                        disabled={isExtracting || extractionDisabled}
                                        variant="outline"
                                        className="w-full"
                                    >
                                        {isExtracting ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Extracting...
                                            </>
                                        ) : 'Trigger LLM Extraction'}
                                    </Button>
                                )}

                                {/* Auto-trigger indicator */}
                                {allConsensusSubmitted && !extraction?.is_successful && (
                                    <p className="text-xs text-muted-foreground">
                                        All consensuses submitted. Extraction will run automatically.
                                    </p>
                                )}

                                {/* Display extraction results (read-only) */}
                                {extraction?.is_successful && (
                                    <>
                                        <div>
                                            <label className="text-sm font-medium text-blue-700">Convergence</label>
                                            <div className="bg-blue-50/50 border rounded-md p-3 min-h-[60px] mt-1">
                                                {renderExtractionText(extraction.convergence_text)}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="text-sm font-medium text-purple-700">Matters for Consideration</label>
                                            <div className="bg-purple-50/50 border rounded-md p-3 min-h-[60px] mt-1">
                                                {renderExtractionText(extraction.consideration_text)}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="text-sm font-medium text-green-700">Proposals</label>
                                            <div className="bg-green-50/50 border rounded-md p-3 min-h-[60px] mt-1">
                                                {renderExtractionText(extraction.proposals_text)}
                                            </div>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Right Section: Synthesis Draft Editor (Editable) */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Synthesis Editor</CardTitle>
                                    <Button
                                        onClick={handleTriggerSynthesis}
                                        disabled={isSynthesizing || isDraftSubmitted}
                                        variant="outline"
                                        size="sm"
                                    >
                                        {isSynthesizing ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Synthesizing...
                                            </>
                                        ) : 'Trigger LLM Synthesis'}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                {/* Convergence */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Convergence</label>
                                    <Textarea
                                        value={convergenceText}
                                        onChange={(e) => handleDraftChange('convergence_text', e.target.value)}
                                        disabled={isDraftSubmitted}
                                        rows={6}
                                        placeholder="Points of agreement..."
                                    />
                                </div>

                                {/* Matters for Consideration */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Matters for Consideration</label>
                                    <Textarea
                                        value={considerationText}
                                        onChange={(e) => handleDraftChange('consideration_text', e.target.value)}
                                        disabled={isDraftSubmitted}
                                        rows={6}
                                        placeholder="Areas of concern or unresolved questions..."
                                    />
                                </div>

                                {/* Proposals */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Proposals</label>
                                    <Textarea
                                        value={proposalsText}
                                        onChange={(e) => handleDraftChange('proposals_text', e.target.value)}
                                        disabled={isDraftSubmitted}
                                        rows={6}
                                        placeholder="Concrete suggestions or recommendations..."
                                    />
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Submit Button (Bottom) */}
                    <Card>
                        <CardContent className="pt-6">
                            <Button
                                onClick={handleSubmitDraft}
                                disabled={isSubmitting || isDraftSubmitted}
                                className="w-full"
                            >
                                {isSubmitting ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Submitting...
                                    </>
                                ) : isDraftSubmitted ? (
                                    <span className="flex items-center gap-2">
                                        <CheckCircle2 className="h-4 w-4" />
                                        Submitted
                                    </span>
                                ) : (
                                    'Submit Synthesis'
                                )}
                            </Button>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </>
    );
}
