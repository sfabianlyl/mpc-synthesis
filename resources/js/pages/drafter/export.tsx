import { Head, Link, useForm, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Label } from '@/components/ui/label';
import { Download, Loader2 } from 'lucide-react';
import { Dialog, DialogClose, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { useEffect, useState } from 'react';

interface User {
    id: number;
    name: string;
}

interface Event {
    id: number;
    title: string;
    start_date: string;
}

interface Conversation {
    id: number;
    title: string;
    status: string;
    event_id: number | null;
    created_at: string;
    event?: Event;
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
    document?: Document | null;
    reports?: SynthesisReport[];
    conversations?: Conversation[];
    events?: Event[];
}

export default function Export({ document: initialDocument, reports: initialReports = [], conversations: availableConversations = [], events: availableEvents = [] }: PageProps) {
    const { data, setData, post, processing } = useForm({
        format: 'pdf',
        documentId: initialDocument?.id ?? null,
    });

    // State for global synthesis dialog
    const [showSynthesisDialog, setShowSynthesisDialog] = useState(false);
    const [selectedConversationIds, setSelectedConversationIds] = useState<number[]>([]);
    const [synthesisTitle, setSynthesisTitle] = useState('');
    const [selectedEventId, setSelectedEventId] = useState<number | null>(null);

    // Auto-select all conversations when dialog opens
    useEffect(() => {
        if (showSynthesisDialog && availableConversations.length > 0 && selectedConversationIds.length === 0) {
            setSelectedConversationIds(availableConversations.map(c => c.id));
        }
    }, [showSynthesisDialog]);

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
        setShowSynthesisDialog(true);
        setSelectedConversationIds([]);
        setSynthesisTitle('');
        setSelectedEventId(availableEvents.length > 0 ? availableEvents[0].id : null);
    };

    const handleSelectAllConversations = () => {
        if (selectedConversationIds.length === availableConversations.length) {
            setSelectedConversationIds([]);
        } else {
            setSelectedConversationIds(availableConversations.map(c => c.id));
        }
    };

    const handleSubmitSynthesis = () => {
        if (selectedConversationIds.length === 0) {
            alert('Please select at least one conversation');
            return;
        }

        router.post('/drafter/global-synthesis', {
            conversation_ids: selectedConversationIds,
            title: synthesisTitle || `Global Synthesis - ${new Date().toLocaleDateString()}`,
            event_id: selectedEventId,
        }, {
            onSuccess: () => {
                alert('Global synthesis completed! Refreshing dashboard...');
                window.location.href = '/drafter';
            },
            onError: (errors) => {
                // Show actual validation errors for debugging
                const errorMessages = Object.entries(errors)
                    .map(([key, value]) => `${key}: ${value}`)
                    .join('\n');
                alert('Global synthesis failed:\n' + errorMessages);
            },
        });
    };

    // Group conversations by event for the dialog
    const conversationsByEvent: Record<number, Conversation[]> = {};
    availableConversations.forEach(conv => {
        if (conv.event_id) {
            if (!conversationsByEvent[conv.event_id]) {
                conversationsByEvent[conv.event_id] = [];
            }
            conversationsByEvent[conv.event_id].push(conv);
        }
    });

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

                {/* Global Synthesis Dialog */}
                <Dialog open={showSynthesisDialog} onOpenChange={setShowSynthesisDialog}>
                    <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Generate Global Synthesis</DialogTitle>
                            <DialogDescription>
                                Select the conversations (sessions) to include in the global synthesis.
                            </DialogDescription>
                        </DialogHeader>

                        <div className="space-y-4 py-4">
                            {/* Event Selection */}
                            {availableEvents.length > 1 && (
                                <div className="space-y-2">
                                    <Label htmlFor="event">Event</Label>
                                    <select
                                        id="event"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                        value={selectedEventId ?? ''}
                                        onChange={(e) => setSelectedEventId(e.target.value ? Number(e.target.value) : null)}
                                    >
                                        {availableEvents.map((event) => (
                                            <option key={event.id} value={event.id}>
                                                {event.title} ({new Date(event.start_date).toLocaleDateString()})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Select All Button */}
                            <div className="flex items-center justify-between p-3 rounded-md border bg-muted/50">
                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="select-all"
                                        checked={selectedConversationIds.length === availableConversations.length}
                                        onCheckedChange={handleSelectAllConversations}
                                    />
                                    <Label htmlFor="select-all" className="text-sm font-medium cursor-pointer">
                                        Select All Conversations ({availableConversations.length})
                                    </Label>
                                </div>
                            </div>

                            {/* Conversation List */}
                            <div className="space-y-2">
                                <Label>Conversations</Label>
                                <div className="max-h-60 overflow-y-auto rounded-md border space-y-1 p-2">
                                    {availableConversations.map((conv) => (
                                        <div
                                            key={conv.id}
                                            className="flex items-center space-x-2 p-2 rounded hover:bg-muted/50 cursor-pointer"
                                            onClick={() => {
                                                setSelectedConversationIds(prev =>
                                                    prev.includes(conv.id)
                                                        ? prev.filter(id => id !== conv.id)
                                                        : [...prev, conv.id]
                                                );
                                            }}
                                        >
                                            <Checkbox
                                                checked={selectedConversationIds.includes(conv.id)}
                                                onCheckedChange={() => {
                                                    setSelectedConversationIds(prev =>
                                                        prev.includes(conv.id)
                                                            ? prev.filter(id => id !== conv.id)
                                                            : [...prev, conv.id]
                                                    );
                                                }}
                                            />
                                            <div className="flex-1 min-w-0">
                                                <p className="text-sm font-medium truncate">{conv.title}</p>
                                                {conv.event && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {conv.event.title}
                                                    </p>
                                                )}
                                            </div>
                                            <span className={`px-2 py-0.5 rounded text-xs ${
                                                conv.status === 'submitted' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                            }`}>
                                                {conv.status}
                                            </span>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Title Input */}
                            <div className="space-y-2">
                                <Label htmlFor="synthesis-title">Document Title (optional)</Label>
                                <input
                                    id="synthesis-title"
                                    type="text"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                    placeholder="Leave empty for auto-generated title"
                                    value={synthesisTitle}
                                    onChange={(e) => setSynthesisTitle(e.target.value)}
                                />
                            </div>

                            {/* Selection Summary */}
                            <div className="text-sm text-muted-foreground p-3 rounded-md bg-muted/50">
                                {selectedConversationIds.length} conversation{selectedConversationIds.length !== 1 ? 's' : ''} selected
                            </div>
                        </div>

                        <DialogFooter>
                            <DialogClose asChild>
                                <Button variant="outline">Cancel</Button>
                            </DialogClose>
                            <Button onClick={handleSubmitSynthesis} disabled={processing || selectedConversationIds.length === 0}>
                                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Generate Synthesis
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                <div className="text-center text-sm text-muted-foreground">
                    <Link href="/drafter" className="underline hover:text-primary">
                        Back to Dashboard
                    </Link>
                </div>
            </div>
        </>
    );
}
