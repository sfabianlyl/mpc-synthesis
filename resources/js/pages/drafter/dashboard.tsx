import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';

interface Group {
    id: number;
    name: string;
}

interface User {
    id: number;
    name: string;
}

interface SynthesisReport {
    id: number;
    group_id: number;
    synthesis_id: number;
    status: string;
    created_at: string;
    group?: Group;
    synthesis?: User;
}

interface FinalDocument {
    id: number;
    title: string;
    emerging_themes: string;
    created_by?: number;
    created_at: string;
    creator?: User;
}

interface Stats {
    total_reports: number;
    submitted_reports: number;
    draft_reports: number;
    documents_created: number;
}

interface PageProps {
    reports: SynthesisReport[];
    documents: FinalDocument[];
    stats: Stats;
}

export default function Dashboard({ reports, documents, stats }: PageProps) {
    return (
        <>
            <Head title="Drafter Dashboard" />
            <div className="p-8 space-y-8">
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Drafter Dashboard</h1>
                        <p className="text-muted-foreground mt-1">
                            Aggregate view of all synthesis team reports and final documents.
                        </p>
                    </div>
                    <Link href="/drafter/export">
                        <Button>Export Final Document</Button>
                    </Link>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-4">
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm font-medium text-muted-foreground">Total Reports</div>
                            <div className="text-2xl font-bold">{stats.total_reports}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm font-medium text-muted-foreground">Submitted</div>
                            <div className="text-2xl font-bold">{stats.submitted_reports}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm font-medium text-muted-foreground">Draft</div>
                            <div className="text-2xl font-bold">{stats.draft_reports}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-sm font-medium text-muted-foreground">Documents Created</div>
                            <div className="text-2xl font-bold">{stats.documents_created}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Synthesis Reports Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Synthesis Reports</CardTitle>
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
                                        <TableHead>Group</TableHead>
                                        <TableHead>Synthesis Member</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {reports.map((report) => (
                                        <TableRow key={report.id}>
                                            <TableCell className="font-medium">{report.group?.name || `Group #${report.group_id}`}</TableCell>
                                            <TableCell>{report.synthesis?.name || 'Unknown'}</TableCell>
                                            <TableCell>
                                                <span className={`px-2 py-1 rounded text-xs font-medium ${
                                                    report.status === 'submitted' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300' : 
                                                    report.status === 'draft' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300' : 
                                                    'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                                }`}>
                                                    {report.status}
                                                </span>
                                            </TableCell>
                                            <TableCell>{new Date(report.created_at).toLocaleDateString()}</TableCell>
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

                {/* Final Documents Table */}
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
                                        <TableHead>Created By</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {documents.map((document) => (
                                        <TableRow key={document.id}>
                                            <TableCell className="font-medium">{document.title}</TableCell>
                                            <TableCell>{document.creator?.name || 'Unknown'}</TableCell>
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
