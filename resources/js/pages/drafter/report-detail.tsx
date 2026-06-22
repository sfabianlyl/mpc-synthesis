import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';

interface User {
    id: number;
    name: string;
}

interface Response {
    id: number;
    consensus_text: string;
    submitted_at: string;
    submitter?: User;
}

interface Group {
    id: number;
    name: string;
}

interface Report {
    id: number;
    group_id: number;
    synthesis_id: number;
    convergence_text: string;
    consideration_text: string;
    proposals_text: string;
    llm_generated: boolean;
    status: string;
    created_at: string;
    updated_at: string;
    group?: Group;
    synthesis?: User;
    responses?: Response[];
}

interface PageProps {
    report: Report;
}

export default function ReportDetail({ report }: PageProps) {
    return (
        <>
            <Head title={`Report Detail - ${report.group?.name || `Group #${report.group_id}`}`} />
            <div className="p-8 space-y-8">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Report Detail</h1>
                        <p className="text-muted-foreground mt-1">
                            {report.group?.name || `Group #${report.group_id}`} — Synthesis Report #{report.id}
                        </p>
                    </div>
                    <Link href="/drafter">
                        <Button variant="outline">Back to Dashboard</Button>
                    </Link>
                </div>

                {/* Report Info */}
                <Card>
                    <CardHeader>
                        <CardTitle>Report Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="text-muted-foreground">Status:</span>
                                <span className={`ml-2 px-2 py-0.5 rounded text-xs font-medium ${
                                    report.status === 'submitted' ? 'bg-green-100 text-green-800' : 
                                    report.status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 
                                    'bg-gray-100 text-gray-800'
                                }`}>
                                    {report.status}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">LLM Generated:</span>
                                <span className="ml-2">{report.llm_generated ? 'Yes' : 'No'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Synthesis Member:</span>
                                <span className="ml-2">{report.synthesis?.name || 'Unknown'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground">Created:</span>
                                <span className="ml-2">{new Date(report.created_at).toLocaleDateString()}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Synthesis Content */}
                <div className="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Convergence</CardTitle>
                            <CardDescription>Areas of agreement</CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {report.convergence_text || <span className="text-muted-foreground">No convergence text available.</span>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Matters for Consideration</CardTitle>
                            <CardDescription>Items needing attention</CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {report.consideration_text || <span className="text-muted-foreground">No considerations available.</span>}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="text-lg">Proposals</CardTitle>
                            <CardDescription>Suggested actions</CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm whitespace-pre-wrap">
                            {report.proposals_text || <span className="text-muted-foreground">No proposals available.</span>}
                        </CardContent>
                    </Card>
                </div>

                {/* Original Responses (Drill-down) */}
                {report.responses && report.responses.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Original Responses (Drill-down)</CardTitle>
                            <CardDescription>Source responses from group members</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {report.responses.map((response) => (
                                <div key={response.id} className="p-4 rounded-lg border bg-muted/30">
                                    <div className="flex justify-between items-start mb-2">
                                        <span className="font-medium text-sm">{response.submitter?.name || 'Anonymous'}</span>
                                        <span className="text-xs text-muted-foreground">
                                            {new Date(response.submitted_at).toLocaleDateString()}
                                        </span>
                                    </div>
                                    <Separator className="my-2" />
                                    <p className="text-sm whitespace-pre-wrap">{response.consensus_text}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
