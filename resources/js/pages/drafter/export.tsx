import { Head, Link, useForm, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Download, Loader2 } from 'lucide-react';

interface User {
    id: number;
    name: string;
}

interface Document {
    id: number;
    title: string;
    content_json: Record<string, unknown>;
    emerging_themes: string;
    exported_file_path: string | null;
    created_by: number | null;
    created_at: string;
    creator?: User;
}

interface SynthesisReport {
    id: number;
    group_id: number;
    synthesis_id: number;
    status: string;
    created_at: string;
    group?: { id: number; name: string };
    synthesis?: User;
}

interface PageProps {
    document?: Document;
    reports?: SynthesisReport[];
}

export default function Export({ document: initialDocument, reports: initialReports = [] }: PageProps) {
    const { data, setData, post, processing } = useForm({
        format: 'pdf',
        documentId: initialDocument?.id ?? null,
    });

    const handleGenerateDownload = () => {
        if (!initialDocument?.id) {
            alert('No document selected for export.');
            return;
        }

        // Use direct URL for PDF download (browsers need a direct URL for file downloads)
        const pdfUrl = `/drafter/export/${initialDocument.id}/pdf`;
        window.open(pdfUrl, '_blank');
    };

    const handleGlobalSynthesis = () => {
        router.post('/drafter/global-synthesis', {}, {
            onSuccess: () => {
                alert('Global synthesis completed! Refreshing dashboard...');
                window.location.href = '/drafter';
            },
            onError: (errors) => {
                alert('Global synthesis failed: ' + (errors.synthesis || 'Unknown error'));
            },
        });
    };

    return (
        <>
            <Head title="Export Final Document" />
            <div className="p-8 max-w-3xl mx-auto space-y-8">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight">Export Final Document</h1>
                    <p className="text-muted-foreground mt-1">
                        Generate and export the final synthesized document.
                    </p>
                </div>

                {/* Global Synthesis Section */}
                {!initialDocument && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Generate Global Synthesis</CardTitle>
                            <CardDescription>
                                Create a new final document by triggering global synthesis across all submitted reports.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button onClick={handleGlobalSynthesis} disabled={processing} className="w-full" size="lg">
                                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Generate Global Synthesis
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {/* Export Options */}
                {initialDocument && (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Document Information</CardTitle>
                                <CardDescription>{initialDocument.title}</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="text-sm text-muted-foreground">
                                    <p>Created: {new Date(initialDocument.created_at).toLocaleDateString()}</p>
                                    {initialDocument.creator && <p>By: {initialDocument.creator.name}</p>}
                                    {initialDocument.emerging_themes && (
                                        <div className="mt-2 p-3 bg-muted rounded-md">
                                            <p className="font-medium">Emerging Themes:</p>
                                            <p className="whitespace-pre-wrap">{initialDocument.emerging_themes}</p>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Export Options</CardTitle>
                                <CardDescription>Select the format for your final synthesized document.</CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <RadioGroup
                                    value={data.format}
                                    onValueChange={(value: string) => setData('format', value)}
                                    className="grid grid-cols-2 gap-4"
                                >
                                    <div>
                                        <RadioGroupItem value="pdf" id="pdf" className="peer sr-only" />
                                        <Label htmlFor="pdf" className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary cursor-pointer">
                                            <span className="font-medium">PDF Document</span>
                                            <span className="text-xs text-muted-foreground">Print-ready format</span>
                                        </Label>
                                    </div>
                                    <div>
                                        <RadioGroupItem value="markdown" id="markdown" className="peer sr-only" />
                                        <Label htmlFor="markdown" className="flex flex-col items-center justify-between rounded-md border-2 border-muted bg-popover p-4 hover:bg-accent hover:text-accent-foreground peer-data-[state=checked]:border-primary [&:has([data-state=checked])]:border-primary cursor-pointer">
                                            <span className="font-medium">Markdown</span>
                                            <span className="text-xs text-muted-foreground">Editable text format</span>
                                        </Label>
                                    </div>
                                </RadioGroup>

                                <Button 
                                    className="w-full mt-4" 
                                    size="lg" 
                                    onClick={handleGenerateDownload}
                                    disabled={processing}
                                >
                                    {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                    <Download className="mr-2 h-4 w-4" />
                                    Generate & Download
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Synthesis Reports Reference */}
                        {initialReports.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Synthesis Reports Included</CardTitle>
                                    <CardDescription>{initialReports.length} reports were used for this synthesis.</CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        {initialReports.slice(0, 5).map((report) => (
                                            <div key={report.id} className="flex items-center justify-between p-2 rounded bg-muted/50">
                                                <span className="text-sm">{report.group?.name || `Group #${report.group_id}`}</span>
                                                <span className={`px-2 py-0.5 rounded text-xs ${
                                                    report.status === 'submitted' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'
                                                }`}>
                                                    {report.status}
                                                </span>
                                            </div>
                                        ))}
                                        {initialReports.length > 5 && (
                                            <p className="text-sm text-muted-foreground text-center">
                                                + {initialReports.length - 5} more reports
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </>
                )}

                <div className="text-center text-sm text-muted-foreground">
                    <Link href="/drafter" className="underline hover:text-primary">
                        Back to Dashboard
                    </Link>
                </div>
            </div>
        </>
    );
}
