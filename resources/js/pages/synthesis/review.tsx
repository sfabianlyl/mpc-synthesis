import { Head, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { CheckCircle2, XCircle, Loader2 } from 'lucide-react';
import { useState, useEffect } from 'react';

interface PageProps {
    group: any; // Current group details
    responses: any[];
    report: any;
    success?: string;
    errors?: Record<string, string>;
}

export default function Review({ group, responses, report, success: initialSuccess, errors: initialErrors }: PageProps) {
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
            // Refresh report data from props if available via page reload
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
        router.post(`/synthesis/analyze/${group.id}`, {}, {
            onSuccess: (page: any) => {
                const newReport = page.props.report;
                if (newReport) {
                    setConvergenceText((newReport.convergence_text as string) ?? '');
                    setConsiderationText((newReport.consideration_text as string) ?? '');
                    setProposalsText((newReport.proposals_text as string) ?? '');
                }
                setIsAnalyzing(false);
                setAnalysisStatus('success');
                setAnalysisMessage('LLM analysis completed successfully using ' + (page.props?.provider || 'unknown') + ' provider.');
            },
            onError: (errors) => {
                setIsAnalyzing(false);
                setAnalysisStatus('error');
                setAnalysisMessage(errors.analysis || 'Analysis failed. Please try again.');
            },
        });
    };

    const handleSaveReport = () => {
        if (!report?.id) return;
        router.patch(`/synthesis/report/${report.id}`, {
            convergence_text: convergenceText,
            consideration_text: considerationText,
            proposals_text: proposalsText,
        }, {
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
        });
    };

    const handleStatusUpdate = (status: 'draft' | 'reviewed' | 'submitted') => {
        if (!report?.id) return;
        router.patch(`/synthesis/report/${report.id}`, { status }, {
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
                setAnalysisMessage(`Failed to update status.`);
            },
        });
    };

    return (
        <>
            <Head title={`Review - ${group?.name}`} />
            <div className="p-8 max-w-6xl mx-auto">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold">{group?.name}</h1>
                    <p className="text-muted-foreground">Group ID: {group?.id}</p>
                    {report && (
                        <Badge variant={report.status === 'submitted' ? 'default' : 'secondary'} className="mt-2">
                            Status: {report.status}
                            {report.llm_generated && <span className="ml-1 text-xs">(LLM Generated)</span>}
                        </Badge>
                    )}
                </div>

                {/* Analysis Status Indicator */}
                {analysisStatus !== 'idle' && (
                    <div className={`mb-6 flex items-center gap-2 p-4 rounded-lg border ${
                        analysisStatus === 'success'
                            ? 'bg-green-50 border-green-200 text-green-800'
                            : 'bg-red-50 border-red-200 text-red-800'
                    }`}>
                        {analysisStatus === 'success' ? (
                            <CheckCircle2 className="h-5 w-5" />
                        ) : (
                            <XCircle className="h-5 w-5" />
                        )}
                        <span>{analysisMessage}</span>
                    </div>
                )}

                <Tabs defaultValue="responses" className="w-full">
                    <TabsList>
                        <TabsTrigger value="responses">Responses</TabsTrigger>
                        <TabsTrigger value="synthesis">Synthesis Review</TabsTrigger>
                    </TabsList>
                    
                    <TabsContent value="responses">
                        <Card>
                            <CardHeader>
                                <CardTitle>Submitted Responses</CardTitle>
                            </CardHeader>
                            <CardContent>
                                {responses.length > 0 ? (
                                    responses.map((res: any) => (
                                        <div key={res.id} className="border-b pb-4 mb-4 last:border-0">
                                            <div className="flex justify-between text-sm text-muted-foreground mb-2">
                                                <span>{res.submitter?.name || 'Anonymous'}</span>
                                                <span>{new Date(res.submitted_at).toLocaleDateString()}</span>
                                            </div>
                                            <p className="whitespace-pre-wrap">{res.consensus_text}</p>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-muted-foreground">No responses submitted yet.</p>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>

                    <TabsContent value="synthesis">
                        <Card>
                            <CardHeader>
                                <CardTitle>LLM Analysis & Manual Review</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex gap-2">
                                    <Button onClick={handleTriggerLLM} disabled={isAnalyzing}>
                                        {isAnalyzing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                        {isAnalyzing ? 'Analyzing...' : 'Trigger LLM Synthesis'}
                                    </Button>
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">Convergence (A)</label>
                                        <Textarea
                                                value={convergenceText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setConvergenceText(e.target.value)}
                                                rows={5}
                                                placeholder="Points of agreement..."
                                            />
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">Matters for Consideration (B)</label>
                                        <Textarea
                                                value={considerationText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setConsiderationText(e.target.value)}
                                                rows={5}
                                                placeholder="Areas of concern or unresolved questions..."
                                            />
                                    </div>
                                    <div>
                                        <label className="text-sm font-medium mb-1 block">Proposals (C)</label>
                                        <Textarea
                                                value={proposalsText}
                                                onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setProposalsText(e.target.value)}
                                                rows={5}
                                                placeholder="Concrete suggestions or recommendations..."
                                            />
                                    </div>
                                </div>
                                
                                <div className="flex justify-between items-center">
                                    <div className="flex gap-2">
                                        <Button variant="outline" onClick={() => handleStatusUpdate('draft')}>Mark Draft</Button>
                                        <Button variant="outline" onClick={() => handleStatusUpdate('reviewed')}>Mark Reviewed</Button>
                                        <Button variant="outline" onClick={() => handleStatusUpdate('submitted')}>Submit Report</Button>
                                    </div>
                                    <Button onClick={handleSaveReport}>Save Report</Button>
                                </div>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}
