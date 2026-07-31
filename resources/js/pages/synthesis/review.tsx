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
    other_text: string | null;
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
    const [bookletUrl, setBookletUrl] = useState<string | null>(null);
    const [bookletWindow, setBookletWindow] = useState<Window | null>(null);
    
    // Local state for extraction results (for immediate display)
    const [extractionResults, setExtractionResults] = useState<{
        convergence_text: string | null;
        consideration_text: string | null;
        proposals_text: string | null;
    } | null>(null);

    // Local state for draft fields
    const [convergenceText, setConvergenceText] = useState(draft?.convergence_text ?? '');
    const [considerationText, setConsiderationText] = useState(draft?.consideration_text ?? '');
    const [proposalsText, setProposalsText] = useState(draft?.proposals_text ?? '');
    const [othersText, setOthersText] = useState(draft?.other_text ?? '');

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

        fetch(`/synthesis/can-trigger-extraction/${conversation.id}`, {
            method: 'GET',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
            },
        }).then(res => res.json()).then(data => {
            setExtractionDisabled(data.can_trigger === false);
        }).catch(() => {
            setExtractionDisabled(false);
        });
    }, [conversation?.id, extraction?.is_successful, initialCanTrigger]);

    // Sync local state when draft prop changes
    useEffect(() => {
        if (draft) {
            setConvergenceText(draft.convergence_text ?? '');
            setConsiderationText(draft.consideration_text ?? '');
            setProposalsText(draft.proposals_text ?? '');
            setOthersText(draft.other_text ?? '');
        }
    }, [draft]);

    // Debounced save handlers with 2s delay — uses fetch to avoid Inertia popup
    const debouncedSaveDraft = useMemo(
        () => debounce((field: string, value: string) => {
            if (!conversation?.id) return;
            fetch(`/synthesis/draft/${conversation.id}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ [field]: value }),
            }).catch(() => {
                // Silently fail — autosave errors should not disrupt the user
            });
        }, 2000),
        [conversation?.id]
    );

    // Debounced batch save: saves ALL current notes in one request using fetch
    // so that quickly tabbing between boxes doesn't lose unsaved changes.
    const debouncedSaveNotes = useMemo(
        () => debounce((notes: Record<number, string>) => {
            if (!conversation?.id) return;
            const payload = Object.entries(notes).map(([groupId, text]) => ({
                group_id: Number(groupId),
                text,
            }));
            fetch(`/synthesis/consensus-notes-batch/${conversation.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                },
                body: JSON.stringify({ notes: payload }),
            }).catch(() => {
                // Silently fail
            });
        }, 2000),
        [conversation?.id]
    );

    // Handle draft field changes with autosave
    const handleDraftChange = (field: 'convergence_text' | 'consideration_text' | 'proposals_text' | 'other_text', value: string) => {
        if (field === 'convergence_text') setConvergenceText(value);
        if (field === 'consideration_text') setConsiderationText(value);
        if (field === 'proposals_text') setProposalsText(value);
        if (field === 'other_text') setOthersText(value);
        debouncedSaveDraft(field, value);
    };

    // Handle note changes with autosave — passes entire updated notes map
    // to the debounced batch saver so all boxes are captured in one request.
    const handleNoteChange = (groupId: number, text: string) => {
        setGroupNotes((prev) => {
            const updated = { ...prev, [groupId]: text };
            debouncedSaveNotes(updated);
            return updated;
        });
    };

    // Trigger LLM extraction — synchronous call with immediate result display
    const handleTriggerExtraction = async () => {
        if (!conversation?.id) return;
        setIsExtracting(true);
        setAnalysisStatus('idle');
        setExtractionResults(null);
        try {
            const res = await fetch(`/synthesis/extract-sync/${conversation.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();
            if (data.success) {
                setExtractionResults({
                    convergence_text: data.convergence_text ?? null,
                    consideration_text: data.consideration_text ?? null,
                    proposals_text: data.proposals_text ?? null,
                });
                setAnalysisStatus('success');
                setAnalysisMessage('Extraction completed successfully.');
                setTimeout(() => {
                    setAnalysisStatus('idle');
                    setAnalysisMessage('');
                }, 5000);
            } else {
                setAnalysisStatus('error');
                setAnalysisMessage(data.message || 'Failed to extract.');
            }
        } catch {
            setAnalysisStatus('error');
            setAnalysisMessage('Failed to extract.');
        } finally {
            setIsExtracting(false);
        }
    };

    // Trigger LLM synthesis (from notes) — synchronous call with immediate result display
    const handleTriggerSynthesis = async () => {
        if (!conversation?.id || !draft?.id) return;
        setIsSynthesizing(true);
        setAnalysisStatus('idle');
        try {
            const res = await fetch(`/synthesis/synthesize-draft-sync/${conversation.id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    selected_group_ids: [],
                    convergence_text: convergenceText,
                    consideration_text: considerationText,
                    proposals_text: proposalsText,
                }),
            });
            const data = await res.json();
            if (data.success) {
                // Update the draft fields with the LLM results
                setConvergenceText(data.convergence_text ?? convergenceText);
                setConsiderationText(data.consideration_text ?? considerationText);
                setProposalsText(data.proposals_text ?? proposalsText);
                setOthersText(data.other_text ?? othersText);
                setAnalysisStatus('success');
                setAnalysisMessage('Synthesis generated successfully.');
                setTimeout(() => {
                    setAnalysisStatus('idle');
                    setAnalysisMessage('');
                }, 5000);
            } else {
                setAnalysisStatus('error');
                setAnalysisMessage(data.message || 'Failed to generate synthesis.');
            }
        } catch {
            setAnalysisStatus('error');
            setAnalysisMessage('Failed to generate synthesis.');
        } finally {
            setIsSynthesizing(false);
        }
    };

    // Submit draft — uses fetch to avoid Inertia popup
    const handleSubmitDraft = () => {
        if (!conversation?.id) return;
        setIsSubmitting(true);
        fetch(`/synthesis/submit-draft/${conversation.id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
                'Accept': 'application/json',
            },
        }).then(res => res.json()).then(data => {
            if (data.success) {
                setAnalysisStatus('success');
                setAnalysisMessage(data.message || 'Synthesis submitted successfully.');
                setTimeout(() => setAnalysisStatus('idle'), 3000);
                // Reload page to reflect changes
                window.location.href = `/synthesis/collaborate/${conversation.id}`;
            } else {
                setAnalysisStatus('error');
                setAnalysisMessage(data.message || 'Failed to submit synthesis.');
            }
        }).catch(() => {
            setAnalysisStatus('error');
            setAnalysisMessage('Failed to submit synthesis.');
        }).finally(() => {
            setIsSubmitting(false);
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
                            <>
                                {/* Download PDF button - shown when booklet is open */}
                                {bookletUrl && bookletWindow ? (
                                    <Button
                                        variant="outline"
                                        className="flex items-center gap-2"
                                        onClick={() => bookletWindow?.print()}
                                    >
                                        <FileDown className="size-4" /> Download PDF
                                    </Button>
                                ) : null}
                                {/* Export Booklet button */}
                                <Button
                                    variant="outline"
                                    className="flex items-center gap-2"
                                    onClick={() => {
                                        const url = `/synthesis/export-booklet/${conversation.id}`;
                                        const win = window.open(url, '_blank');
                                        setBookletUrl(url);
                                        setBookletWindow(win);
                                    }}
                                >
                                    <FileDown className="size-4" /> Export Booklet
                                </Button>
                            </>
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

                <section className="space-y-4">
                    {/* Instructions for synthesis members */}
                    <h2 className="font-semibold">Individual or Small Group Action</h2>
 
                    <h4>The task is to discern the factors of:</h4>
                    <ol type="A">
                        <li>A. Convergence: Independent points coming together to point toward one clear, unified conclusion or emerging conclusion. </li>
                        <li>B. Matters for consideration: A dissenting statement or totally new and unheard of ideas or improved ways of doing existing ideas or objections.</li>
                        <li>C. Recommendation: actionable or something clear enough to be put into practical use. Recommendation may also be explained as other required action before being able to address the pertaining matter.</li>
                    </ol>
                    
                    <h4 className="font-semibold">Method</h4>
                    <ol>
                        <li>1. Read the consensus paragraphs assigned</li>
                        <li>2. Identify implicit and explicit points from the consensus paragraphs respectively </li>
                        <li>3. Then you may use the LLM trigger to assist in the formulation of Convergence + Matters for consideration + Proposal</li>
                        <li>4. Complete in full and proper sentences under Synthesis Editor your Convergence + Matters for consideration + Proposal for further discussion and discernment under collaborative review</li>
                    </ol>
                    
                    
                </section>

                {/* ─── Left Column: Submitted Consensus + Notes ─────────────── */}
                <section className="space-y-4">
                    
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

                                {/* Display extraction results (read-only) — shows sync results or API extraction */}
                                {(extractionResults || extraction?.is_successful) && (
                                    <>
                                        <div>
                                            <label className="text-sm font-medium text-blue-700">Convergence</label>
                                            <div className="bg-blue-50/50 border rounded-md p-3 min-h-[60px] mt-1">
                                                {renderExtractionText(extractionResults?.convergence_text ?? extraction?.convergence_text ?? null)}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="text-sm font-medium text-purple-700">Matters for Consideration</label>
                                            <div className="bg-purple-50/50 border rounded-md p-3 min-h-[60px] mt-1">
                                                {renderExtractionText(extractionResults?.consideration_text ?? extraction?.consideration_text ?? null)}
                                            </div>
                                        </div>
                                        <div>
                                            <label className="text-sm font-medium text-green-700">Proposals</label>
                                            <div className="bg-green-50/50 border rounded-md p-3 min-h-[60px] mt-1">
                                                {renderExtractionText(extractionResults?.proposals_text ?? extraction?.proposals_text ?? null)}
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

                                {/* Others */}
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Others</label>
                                    <Textarea
                                        value={othersText}
                                        onChange={(e) => handleDraftChange('other_text', e.target.value)}
                                        disabled={isDraftSubmitted}
                                        rows={6}
                                        placeholder="Other syntheses not mentioned..."
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
