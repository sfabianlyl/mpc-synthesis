import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CheckCircle2,
    Clock,
    FileDown,
    FileText,
    LayoutGrid,
    Lock,
    ScrollText,
    Shield,
    Users,
    Layers,
    MessageSquare,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';

interface ConversationItem {
    id: number;
    title: string;
    status: string;
    description: string | null;
    event_id: number | null;
    event_title: string | null;
    has_master_synthesis?: boolean;
    user_draft_submitted: boolean;
    submitted_drafts_count: number;
    total_pastoral_count: number;
    all_drafts_submitted: boolean;
    diocese_decision_part_exists?: boolean;
    diocese_decision_part_submitted?: boolean;
    national_decision_part_exists?: boolean;
    national_decision_part_submitted?: boolean;
    decision_part_exists?: boolean;
    decision_part_submitted: boolean;
    media_files: Array<{ name: string; path: string; size: number }>;
}

interface EventItem {
    id: number;
    title: string;
    conversations_count: number;
    all_decision_parts_submitted: boolean;
    master_decision_submitted: boolean;
}

interface PageProps {
    conversations: ConversationItem[];
    events: EventItem[];
}

const statusConfig: Record<string, { label: string; className: string }> = {
    active: { label: 'Active', className: 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 border-emerald-200 dark:border-emerald-800' },
    closed: { label: 'Closed', className: 'bg-slate-100 dark:bg-slate-800/40 text-slate-600 border-slate-200 dark:border-slate-700' },
    draft: { label: 'Draft', className: 'bg-amber-50 dark:bg-amber-950/20 text-amber-700 border-amber-200 dark:border-amber-800' },
};

export default function Dashboard({ conversations = [], events = [] }: PageProps) {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    const userDraftsCount = conversations.filter((c) => c.user_draft_submitted).length;
    const finalizedPartsCount = conversations.filter((c) => c.decision_part_submitted).length;
    const readyToMasterEvents = events.filter((e) => e.all_decision_parts_submitted).length;

    return (
        <>
            <Head title="Pastoral Dashboard" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col gap-1">
                    <h1 className="text-3xl font-bold tracking-tight">Pastoral Dashboard</h1>
                    <p className="text-muted-foreground">
                        Welcome, {user?.name}. Follow the 3-step workflow: submit individual drafts, collaborate on decision parts for each CIS session, and consolidate the master decision for all conversations.
                    </p>
                </div>


                

                {/* Discernment Sessions / Conversations List */}
                <div className="flex flex-col gap-4">
                    <h2 className="text-lg font-semibold flex items-center gap-2">
                        <ScrollText className="size-5 text-indigo-600" />
                        CIS Sessions (Step 1 & 2)
                    </h2>

                    {conversations.length === 0 ? (
                        <Card>
                            <CardContent className="py-16 flex flex-col items-center gap-3 text-muted-foreground">
                                <LayoutGrid className="size-10 opacity-30" />
                                <p className="text-sm">No conversations available yet.</p>
                            </CardContent>
                        </Card>
                    ) : (
                        conversations.map((conv) => {
                            const statusStyle = statusConfig[conv.status] ?? statusConfig['draft'];
                            return (
                                <Card key={conv.id} className="border-sidebar-border/60">
                                    <CardHeader className="pb-3">
                                        <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-2">
                                            <div className="flex flex-col gap-1">
                                                <CardTitle className="text-base flex items-center gap-2">
                                                    <BookOpen className="size-4 text-indigo-500 shrink-0" />
                                                    {conv.title}
                                                </CardTitle>
                                                {conv.description && (
                                                    <p className="text-sm text-muted-foreground line-clamp-2">
                                                        {conv.description}
                                                    </p>
                                                )}
                                            </div>

                                            {/* Status Badges */}
                                            <div className="flex flex-wrap items-center gap-2 shrink-0">
                                                {conv.user_draft_submitted ? (
                                                    <Badge variant="outline" className="text-xs px-2 py-0.5 bg-emerald-50 dark:bg-emerald-950/20 text-emerald-700 border-emerald-300 flex items-center gap-1">
                                                        <CheckCircle2 className="size-3" />
                                                        Draft Submitted
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline" className="text-xs px-2 py-0.5 bg-amber-50 dark:bg-amber-950/20 text-amber-700 border-amber-300 flex items-center gap-1">
                                                        <Clock className="size-3" />
                                                        Pending Your Draft
                                                    </Badge>
                                                )}

                                                {conv.decision_part_submitted ? (
                                                    <Badge variant="default" className="text-xs px-2 py-0.5 bg-indigo-600 flex items-center gap-1">
                                                        <CheckCircle2 className="size-3" />
                                                        Decision Part Finalized
                                                    </Badge>
                                                ) : conv.all_drafts_submitted ? (
                                                    <Badge variant="outline" className="text-xs px-2 py-0.5 bg-purple-50 text-purple-700 border-purple-300">
                                                        Ready to Collaborate
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary" className="text-xs px-2 py-0.5 text-muted-foreground">
                                                        {conv.submitted_drafts_count}/{conv.total_pastoral_count} Drafts
                                                    </Badge>
                                                )}
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="border-t border-sidebar-border/30 pt-3 flex flex-wrap items-center justify-between gap-3">
                                        <div className="flex flex-wrap gap-2">
                                            {/* Step 1: Decision Draft button */}
                                            <Link href={`/pastoral/decision/${conv.id}`}>
                                                <Button
                                                    size="sm"
                                                    variant={conv.user_draft_submitted ? 'outline' : 'default'}
                                                    className={`gap-1.5 text-xs ${
                                                        !conv.user_draft_submitted ? 'bg-indigo-600 hover:bg-indigo-700' : ''
                                                    }`}
                                                >
                                                    <FileText className="size-3.5" />
                                                    Begin Discernment
                                                </Button>
                                            </Link>

                                            {/* Step 2: Collaborative Decision button */}
                                            <Link href={`/pastoral/collaborate/${conv.id}`}>
                                                <Button
                                                    size="sm"
                                                    variant={conv.all_drafts_submitted ? 'default' : 'outline'}
                                                    className={`gap-1.5 text-xs ${
                                                        conv.all_drafts_submitted && !conv.decision_part_submitted
                                                            ? 'bg-purple-600 hover:bg-purple-700'
                                                            : ''
                                                    }`}
                                                >
                                                    <Users className="size-3.5" />
                                                    Collaborate on Decision Part
                                                </Button>
                                            </Link>

                                            {/* Consensus Paragraphs button */}
                                            <Link href={`/pastoral/consensus-paragraphs/${conv.id}`}>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="gap-1.5 text-xs"
                                                >
                                                    <MessageSquare className="size-3.5" />
                                                    Consensus Paragraphs
                                                </Button>
                                            </Link>

                                            {/* Booklet export link */}
                                            {conv.has_master_synthesis && (
                                                <Link href={`/pastoral/export-booklet/${conv.id}`}>
                                                    <Button variant="ghost" size="sm" className="gap-1.5 text-xs text-muted-foreground">
                                                        <FileText className="size-3.5" />
                                                        View Booklet
                                                    </Button>
                                                </Link>
                                            )}
                                        </div>

                                        {/* Media Files */}
                                        {conv.media_files && conv.media_files.length > 0 && (
                                            <div className="flex items-center gap-1 text-xs text-muted-foreground">
                                                <span>Downloads:</span>
                                                {conv.media_files.map((file) => (
                                                    <a
                                                        key={file.name}
                                                        href={file.path}
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        className="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded border hover:bg-muted text-[11px]"
                                                    >
                                                        <FileDown className="size-3" />
                                                        {file.name}
                                                    </a>
                                                ))}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            );
                        })
                    )}
                </div>

                {/* Event Master Decision Action Banner */}
                {events.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="text-lg font-semibold flex items-center gap-2">
                            <Shield className="size-5 text-indigo-600" />
                            Event Master Consolidation (Step 3)
                        </h2>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {events.map((event) => (
                                <Card key={event.id} className="border-sidebar-border/60">
                                    <CardHeader className="pb-3">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="text-base">{event.title}</CardTitle>
                                            {event.master_decision_submitted ? (
                                                <Badge variant="default" className="bg-emerald-600 text-xs">
                                                    Master Finalized
                                                </Badge>
                                            ) : event.all_decision_parts_submitted ? (
                                                <Badge variant="outline" className="text-indigo-600 border-indigo-300 bg-indigo-50 dark:bg-indigo-950/20 text-xs">
                                                    Ready to Finalize
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline" className="text-amber-600 border-amber-300 text-xs flex items-center gap-1">
                                                    <Lock className="size-3" /> Waiting for Sessions
                                                </Badge>
                                            )}
                                        </div>
                                        <CardDescription className="text-xs">
                                            {event.conversations_count} discernment sessions in this event
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="pt-0 flex flex-wrap gap-2">
                                        <Link href={`/pastoral/master-decision/${event.id}`}>
                                            <Button
                                                size="sm"
                                                variant={event.all_decision_parts_submitted ? 'default' : 'outline'}
                                                className={`gap-1.5 text-xs ${
                                                    event.all_decision_parts_submitted && !event.master_decision_submitted
                                                        ? 'bg-indigo-600 hover:bg-indigo-700'
                                                        : ''
                                                }`}
                                            >
                                                <Layers className="size-3.5" />
                                                Master Decision Review
                                                <ArrowRight className="size-3" />
                                            </Button>
                                        </Link>

                                        <Link href={`/pastoral/bishop-overview/${event.id}`}>
                                            <Button size="sm" variant="outline" className="gap-1.5 text-xs">
                                                <Shield className="size-3.5" />
                                                Bishop's Overview
                                            </Button>
                                        </Link>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={[{ title: 'Pastoral Dashboard', href: '/pastoral/dashboard' }]}>{page}</AppLayout>
);
