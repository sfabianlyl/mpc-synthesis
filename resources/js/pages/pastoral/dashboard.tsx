import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CheckCircle2,
    ClipboardList,
    FileDown,
    FileText,
    LayoutGrid,
    Lock,
    ScrollText,
    Shield,
    Users,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface Conversation {
    id: number;
    title: string;
    status: string;
    description: string | null;
    event_title: string | null;
    has_reports: boolean;
    user_draft_submitted: boolean;
    event_id: number | null;
    media_files: Array<{ name: string; path: string; size: number }>;
}

interface PageProps {
    conversations: Conversation[];
}

const statusConfig: Record<string, { label: string; className: string }> = {
    active: { label: 'Active', className: 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 border-emerald-200 dark:border-emerald-800' },
    closed: { label: 'Closed', className: 'bg-slate-100 dark:bg-slate-800/40 text-slate-600 border-slate-200 dark:border-slate-700' },
    draft: { label: 'Draft', className: 'bg-amber-50 dark:bg-amber-950/20 text-amber-700 border-amber-200 dark:border-amber-800' },
};

export default function Dashboard({ conversations }: PageProps) {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    return (
        <>
            <Head title="Pastoral Dashboard" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <h1 className="text-3xl font-bold tracking-tight">Pastoral Dashboard</h1>
                    <p className="text-muted-foreground">
                        Welcome, {user?.name}. Review conversations, submit decision drafts, and view final pastoral decisions.
                    </p>
                </div>

                {/* Quick-action cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/20 rounded-lg">
                                <ScrollText className="h-5 w-5 text-indigo-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Conversations</p>
                                <p className="text-xs text-muted-foreground">{conversations.length} total</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/20 rounded-lg">
                                <FileText className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Reports Ready</p>
                                <p className="text-xs text-muted-foreground">
                                    {conversations.filter((c) => c.has_reports).length} available
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-violet-50 dark:bg-violet-950/20 rounded-lg">
                                <CheckCircle2 className="h-5 w-5 text-violet-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Decisions Submitted</p>
                                <p className="text-xs text-muted-foreground">
                                    {conversations.filter((c) => c.user_draft_submitted).length} of {conversations.length}
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Event-level Decision Action Buttons */}
                {(() => {
                    const eventIds = [...new Set(conversations.filter(c => c.event_id).map(c => c.event_id))];
                    if (eventIds.length === 0) return null;
                    
                    // Use the first event_id if only one, otherwise show a note
                    const eventId = eventIds[0];
                    const hasMultipleEvents = eventIds.length > 1;
                    
                    return (
                        <div className="flex flex-col gap-3">
                            <h2 className="text-lg font-semibold">Event-level Decision Making</h2>
                            {hasMultipleEvents && (
                                <p className="text-sm text-muted-foreground">
                                    Showing buttons for the first event. Conversations span {eventIds.length} events.
                                </p>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <Link href={`/pastoral/decision/${eventId}`}>
                                    <Button variant="default" size="sm" className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                                        <FileText className="size-4" />
                                        Begin Decision Making
                                        <ArrowRight className="size-3" />
                                    </Button>
                                </Link>
                                
                                <Link href={`/pastoral/collaborate/${eventId}`}>
                                    <Button variant="outline" size="sm" className="gap-2">
                                        <Users className="size-4" />
                                        Collaborative Decision
                                        <ArrowRight className="size-3" />
                                    </Button>
                                </Link>
                                
                                <Link href={`/pastoral/bishop-overview/${eventId}`}>
                                    <Button variant="outline" size="sm" className="gap-2">
                                        <Shield className="size-4" />
                                        Bishop's Overview
                                        <ArrowRight className="size-3" />
                                    </Button>
                                </Link>
                            </div>
                        </div>
                    );
                })()}

                {/* Conversations List */}
                <div className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold">Conversations</h2>

                    {conversations.length === 0 ? (
                        <Card>
                            <CardContent className="py-16 flex flex-col items-center gap-3 text-muted-foreground">
                                <LayoutGrid className="size-10 opacity-30" />
                                <p className="text-sm">No conversations available yet.</p>
                            </CardContent>
                        </Card>
                    ) : (
                        conversations.map((conversation) => {
                            const statusStyle = statusConfig[conversation.status] ?? statusConfig['draft'];
                            return (
                                <Card key={conversation.id} className="border-sidebar-border/50">
                                    <CardHeader className="pb-3">
                                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                                            <div className="flex flex-col gap-1">
                                                <CardTitle className="text-base flex items-center gap-2">
                                                    <BookOpen className="size-4 text-indigo-500 shrink-0" />
                                                    {conversation.title}
                                                </CardTitle>
                                                {conversation.event_title && (
                                                    <CardDescription className="text-xs">
                                                        Event: {conversation.event_title}
                                                    </CardDescription>
                                                )}
                                                {conversation.description && (
                                                    <p className="text-sm text-muted-foreground line-clamp-2">
                                                        {conversation.description}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex items-center gap-2 shrink-0">
                                                <Badge variant="outline" className={`text-xs px-2 py-0.5 ${statusStyle.className}`}>
                                                    {statusStyle.label}
                                                </Badge>
                                                {conversation.user_draft_submitted && (
                                                    <Badge variant="outline" className="text-xs px-2 py-0.5 bg-violet-50 dark:bg-violet-950/20 text-violet-700 border-violet-200 dark:border-violet-800 flex items-center gap-1">
                                                        <Lock className="size-3" />
                                                        Decision Submitted
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="border-t border-sidebar-border/30 pt-4">
                                        <div className="flex flex-wrap gap-2">
                                            {/* Reports link – only if synthesis is submitted */}
                                            {conversation.has_reports ? (
                                                <Link href={`/synthesis/export-booklet/${conversation.id}`} target="_blank">
                                                    <Button variant="outline" size="sm" className="gap-2">
                                                        <FileText className="size-4" />
                                                        View Booklet
                                                        <ArrowRight className="size-3" />
                                                    </Button>
                                                </Link>
                                            ) : (
                                                <Button variant="outline" size="sm" className="gap-2 opacity-50 cursor-not-allowed" disabled>
                                                    <FileText className="size-4" />
                                                    Reports Not Ready
                                                </Button>
                                            )}
                                        </div>

                                        {/* Media Files - Open in New Tab */}
                                        {conversation.media_files && conversation.media_files.length > 0 ? (
                                            <div className="border-t border-sidebar-border/30 pt-3 mt-2">
                                                <p className="text-xs font-medium text-muted-foreground mb-2">Downloads:</p>
                                                <div className="flex flex-wrap gap-1">
                                                    {conversation.media_files.map((file) => (
                                                        <a
                                                            key={file.name}
                                                            href={file.path}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="inline-flex items-center gap-1 px-2 h-7 rounded-md border border-transparent hover:bg-muted/50 transition-colors cursor-pointer"
                                                        >
                                                            <FileDown className="size-3 text-muted-foreground" />
                                                            <span className="text-xs">{file.name}</span>
                                                        </a>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Pastoral Dashboard', href: '/pastoral/dashboard' }]}>{page}</AppLayout>
);
