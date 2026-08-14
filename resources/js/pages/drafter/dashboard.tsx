import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { FileText, BookOpen, CheckCircle2, Clock, Sparkles } from 'lucide-react';

interface Group {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

/**
 * SynthesisReport represents an individual synthesis submission.
 */
interface SynthesisReport {
    id: number;
    conversation_id: number;
    user_id: number;
    synthesis_id: number;
    // Content fields
    convergence_text?: string | null;
    consideration_text?: string | null;
    proposals_text?: string | null;
    other_text?: string | null;
    
    is_master_synthesis?: boolean;
    status: 'draft' | 'reviewed' | 'submitted';
    submitted_at?: string | null;
    created_at: string;
    updated_at: string;
    
    group?: Group;
    synthesis?: User;
    conversation?: { id: number; title: string };
}

/**
 * MasterSynthesis represents the aggregated "synthesis of syntheses"
 * for a conversation, compiled during Collaborative Review.
 */
interface MasterSynthesis {
    id: number;
    conversation_id: number;
    convergence_text?: string | null;
    consideration_text?: string | null;
    proposals_text?: string | null;
    other_text?: string | null;
    status: 'draft' | 'reviewed' | 'submitted';
    booklet_file_path?: string | null;
    created_at: string;
    updated_at: string;
}

interface FinalDocument {
    id: number;
    event_id: number | null;
    title: string;
    emerging_themes?: string;
    is_ai_generated: boolean;
    drafted_by: number | null;
    drafted_at: string | null;
    source_report_ids: number[];
    retrieved_chunk_references: number[];
    exported_file_path: string | null;
    created_by?: number;
    created_at: string;
    creator?: User;
    event?: { id: number; title: string };
}

interface Event {
    id: number;
    title: string;
    start_date: string;
    end_date: string | null;
    location?: string;
}

interface Stats {
    total_reports: number;
    submitted_reports: number;
    draft_reports: number;
    documents_created: number;
    master_syntheses_ready: number;
}

interface PageProps {
    reports: SynthesisReport[];
    masterSyntheses: MasterSynthesis[];
    documents: FinalDocument[];
    events: Event[];
    stats: Stats;
}

export default function Dashboard({ reports, masterSyntheses, documents, events, stats }: PageProps) {
    // Group master syntheses by conversation for quick lookup
    const masterSynthesisByConversation = new Map(
        masterSyntheses.map(ms => [ms.conversation_id, ms])
    );

    // Group reports by conversation
    const reportsByConversation = reports.reduce<Record<number, SynthesisReport[]>>((acc, r) => {
        if (!acc[r.conversation_id]) {
            acc[r.conversation_id] = [];
        }
        acc[r.conversation_id].push(r);
        return acc;
    }, {});

    // Group events for display
    const eventsByTitle = events.reduce<Record<number, Event>>((acc, e) => {
        acc[e.id] = e;
        return acc;
    }, {});

    return (
        <>
            <Head title="Drafter Dashboard" />
            <div className="p-8 space-y-8">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Drafter Dashboard</h1>
                        <p className="text-muted-foreground mt-1">
                            Aggregate view of all synthesis team reports, master syntheses, and final documents.
                        </p>
                    </div>
                    <Link href="/drafter/export">
                        <Button>
                            <BookOpen className="mr-2 h-4 w-4" />
                            Export Final Document
                        </Button>
                    </Link>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-5">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2">
                                <FileText className="h-5 w-5 text-blue-600" />
                                <div>
                                    <div className="text-sm font-medium text-muted-foreground">Total Reports</div>
                                    <div className="text-2xl font-bold">{stats.total_reports}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2">
                                <CheckCircle2 className="h-5 w-5 text-green-600" />
                                <div>
                                    <div className="text-sm font-medium text-muted-foreground">Submitted</div>
                                    <div className="text-2xl font-bold">{stats.submitted_reports}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2">
                                <Clock className="h-5 w-5 text-amber-600" />
                                <div>
                                    <div className="text-sm font-medium text-muted-foreground">Draft</div>
                                    <div className="text-2xl font-bold">{stats.draft_reports}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2">
                                <Sparkles className="h-5 w-5 text-purple-600" />
                                <div>
                                    <div className="text-sm font-medium text-muted-foreground">Master Syntheses Ready</div>
                                    <div className="text-2xl font-bold">{stats.master_syntheses_ready}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="flex items-center gap-2">
                                <BookOpen className="h-5 w-5 text-indigo-600" />
                                <div>
                                    <div className="text-sm font-medium text-muted-foreground">Documents Created</div>
                                    <div className="text-2xl font-bold">{stats.documents_created}</div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* ─── Master Syntheses Section ───────────────────── */}
                {masterSyntheses.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Sparkles className="h-5 w-5 text-purple-600" />
                                Master Syntheses Ready for Booklet Generation
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Conversation</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Last Updated</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {masterSyntheses.map((ms) => (
                                        <TableRow key={ms.id}>
                                            <TableCell className="font-medium">
                                                Conversation #{ms.conversation_id}
                                            </TableCell>
                                            <TableCell>
                                                <Badge 
                                                    variant={ms.status === 'submitted' ? 'default' : 'secondary'}
                                                    className={
                                                        ms.status === 'submitted'
                                                            ? 'bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/10'
                                                            : 'bg-amber-500/10 text-amber-600 hover:bg-amber-500/10'
                                                    }
                                                >
                                                    {ms.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground text-sm">
                                                {new Date(ms.updated_at).toLocaleString()}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex items-center justify-end gap-2">
                                                    <Link href={`/drafter/report/${ms.conversation_id}`}>
                                                        <Button variant="link" size="sm" className="text-xs">View Details</Button>
                                                    </Link>
                                                    {ms.booklet_file_path && (
                                                        <Link href={`/drafter/export/${ms.id}`}>
                                                            <Button variant="link" size="sm" className="text-xs">Booklet</Button>
                                                        </Link>
                                                    )}
                                                    {!ms.booklet_file_path && (
                                                        <Link href="/drafter/export">
                                                            <Button variant="outline" size="sm" className="text-xs">
                                                                <BookOpen className="h-3 w-3 mr-1" /> Generate Booklet
                                                            </Button>
                                                        </Link>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                {/* ─── Synthesis Reports Table ────────────────────── */}
                <Card>
                    <CardHeader>
                        <CardTitle>Synthesis Reports (Individual Submissions)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {reports.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                No synthesis reports available. Synthesis team members need to submit their reports first.
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Conversation</TableHead>
                                        <TableHead>Group</TableHead>
                                        <TableHead>Synthesis Member</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reports.map((report) => (
                                        <TableRow key={report.id}>
                                            <TableCell className="font-medium">
                                                {report.conversation?.title || `Conversation #${report.conversation_id}`}
                                            </TableCell>
                                            <TableCell>{report.group?.name || `Group #${report.synthesis_id}`}</TableCell>
                                            <TableCell>{report.synthesis?.name || 'Unknown'}</TableCell>
                                            <TableCell>
                                                {report.is_master_synthesis ? (
                                                    <Badge variant="secondary" className="bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300">
                                                        Master
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Individual</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                    report.status === 'submitted' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 
                                                    report.status === 'reviewed' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300' :
                                                    report.status === 'draft' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : 
                                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                                }`}>
                                                    {report.status}
                                                </span>
                                            </TableCell>
                                            <TableCell>{new Date(report.updated_at).toLocaleDateString()}</TableCell>
                                            <TableCell className="text-right">
                                                <Link href={`/drafter/report/${report.id}`}>
                                                    <Button variant="link" size="sm">View Details</Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* ─── Final Documents Table ──────────────────────── */}
                <Card>
                    <CardHeader>
                        <CardTitle>Final Documents</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {documents.length === 0 ? (
                            <div className="text-center py-8 text-muted-foreground">
                                No final documents have been created yet. Use global synthesis to generate one.
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Title</TableHead>
                                        <TableHead>Event</TableHead>
                                        <TableHead>AI Generated</TableHead>
                                        <TableHead>Sources</TableHead>
                                        <TableHead>Created By</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documents.map((document) => (
                                        <TableRow key={document.id}>
                                            <TableCell className="font-medium">{document.title}</TableCell>
                                            <TableCell>
                                                {document.event ? (
                                                    <span className="text-sm">{document.event.title}</span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">—</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                {document.is_ai_generated ? (
                                                    <Badge variant="outline" className="text-xs bg-purple-50 dark:bg-purple-950/20 text-purple-600">
                                                        AI-Generated
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">Manual</Badge>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <span className="text-xs text-muted-foreground">
                                                    {document.source_report_ids?.length || 0} reports,
                                                    {document.retrieved_chunk_references?.length || 0} chunks
                                                </span>
                                            </TableCell>
                                            <TableCell>{document.creator?.name || (document.drafted_by ? `User #${document.drafted_by}` : 'Unknown')}</TableCell>
                                            <TableCell>{new Date(document.created_at).toLocaleDateString()}</TableCell>
                                            <TableCell className="text-right">
                                                <Link href={`/drafter/export/${document.id}`}>
                                                    <Button variant="link" size="sm">Export</Button>
                                                </Link>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
