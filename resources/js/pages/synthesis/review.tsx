import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, XCircle, Loader2, FileText } from 'lucide-react';
import { useState, useEffect } from 'react';

interface PageProps {
    conversation: any;
    groups: any[];
    responses: any[];
    report: any;
    success?: string;
    errors?: Record<string, string>;
    consensusSubmissions: Record<number, any[]>;
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

    const handleTriggerLLM = () => {
        setIsAnalyzing(true);
        setAnalysisStatus('idle');
        setAnalysisMessage('');
        router.post(
            `/synthesis/analyze/${conversation.id}`,
            {},
            {
                preserveScroll: true,
                onSuccess: (page: any) => {
                    const newReport = page.props.report;
                    if (newReport) {
                        setConvergenceText((prev: string) => {
                            const newText = newReport.convergence_text ?? '';
                            return newText ? (prev ? prev + '\n\n' + newText : newText) : prev;
                        });
                        setConsiderationText((prev: string) => {
                            const newText = newReport.consideration_text ?? '';
                            return newText ? (prev ? prev + '\n\n' + newText : newText) : prev;
                        });
                        setProposalsText((prev: string) => {
                            const newText = newReport.proposals_text ?? '';
                            return newText ? (prev ? prev + '\n\n' + newText : newText) : prev;
                        });
                    }
                    setIsAnalyzing(false);
                    setAnalysisStatus('success');
                    setAnalysisMessage(
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
        if (!report?.id) return;
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
        if (!report?.id) return;
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

    // Collect all consensus submissions from the groups
    const allConsensusSubmissions: any[] = [];
    for (const group of groups) {
        const submissions = consensusSubmissions[group.id] || [];
        for (const submission of submissions) {
            allConsensusSubmissions.push({
                ...submission,
                groupName: group.name,
                groupId: group.id,
            });
        }
    }

    return (
        <>
            <Head title={`Review - ${conversation?.title || 'Synthesis Review'}`} />
            <div className="p-8 max-w-[1920px] mx-auto">
                {/* Header */}
                <div className="mb-6">
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
                                {allConsensusSubmissions.length > 0 ? (
                                    allConsensusSubmissions.map((submission: any, index: number) => (
                                        <div
                                            key={`${submission.facilitator_id}-${index}`}
                                            className="border rounded-lg p-4 bg-card"
                                        >
                                            <div className="flex justify-between items-start mb-2">
                                                <div>
                                                    <p className="font-medium text-sm">
                                                        {submission.facilitator_name || 'Anonymous'}
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
                                        No consensus submissions received from any group in your assigned conversations.
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
                                <div className="space-y-3">
                                    {groups.map((group: any) => {
                                        const submissions = consensusSubmissions[group.id] || [];
                                        const hasSubmitted = submissions.some(
                                            (s: any) => s.is_submitted
                                        );
                                        return (
                                            <div key={group.id} className="flex items-center justify-between">
                                                <span className="text-sm">{group.name}</span>
                                                <Badge variant={hasSubmitted ? 'default' : 'secondary'} className="text-xs">
                                                    {hasSubmitted ? 'Submitted' : 'Pending'}
                                                </Badge>
                                            </div>
                                        );
                                    })}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column - Synthesis Editor */}
                    <div className="space-y-4">
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle>Synthesis Editor</CardTitle>
                                    <Button
                                        onClick={handleTriggerLLM}
                                        disabled={isAnalyzing}
                                        variant="outline"
                                        size="sm"
                                    >
                                        {isAnalyzing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                        {isAnalyzing ? 'Processing...' : 'Trigger LLM Synthesis'}
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-4">
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">
                                            Convergence (A)
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
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">
                                            Matters for Consideration (B)
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
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">
                                            Proposals (C)
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

                                <div className="flex flex-col sm:flex-row gap-2 pt-4 border-t">
                                    <Button onClick={handleSaveReport} className="flex-1">
                                        Save Draft
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => handleStatusUpdate('reviewed')}
                                        disabled={!report?.id}
                                        className="flex-1"
                                    >
                                        Mark Reviewed
                                    </Button>
                                    <Button
                                        variant="outline"
                                        onClick={() => handleStatusUpdate('submitted')}
                                        disabled={!report?.id}
                                        className="flex-1"
                                    >
                                        Submit Synthesis
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
