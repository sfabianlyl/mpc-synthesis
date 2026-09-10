import { useState, useMemo } from 'react';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, MessageSquare, Search, Users, Calendar } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';

interface ResponseItem {
    id: number;
    group_name: string;
    consensus_text: string;
    submitted_at?: string | null;
}

interface ConversationData {
    id: number;
    title: string;
    description?: string | null;
    status: string;
    event?: {
        id: number;
        title: string;
    } | null;
}

interface PageProps {
    conversation: ConversationData;
    responses: ResponseItem[];
}

export default function ConsensusParagraphs({ conversation, responses = [] }: PageProps) {
    const [searchQuery, setSearchQuery] = useState('');

    const filteredResponses = useMemo(() => {
        if (!searchQuery.trim()) return responses;
        const query = searchQuery.toLowerCase();
        return responses.filter(
            (r) =>
                r.group_name.toLowerCase().includes(query) ||
                r.consensus_text.toLowerCase().includes(query)
        );
    }, [responses, searchQuery]);

    const formatDate = (dateStr?: string | null) => {
        if (!dateStr) return null;
        try {
            return new Date(dateStr).toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
            });
        } catch {
            return null;
        }
    };

    return (
        <>
            <Head title={`Consensus Paragraphs - ${conversation.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-6xl mx-auto">
                {/* Back to Dashboard & Header */}
                <div className="flex flex-col gap-4">
                    <div>
                        <Link href="/pastoral/dashboard">
                            <Button variant="ghost" size="sm" className="gap-1.5 text-xs -ml-2 text-muted-foreground hover:text-foreground">
                                <ArrowLeft className="size-4" />
                                Back to Pastoral Dashboard
                            </Button>
                        </Link>
                    </div>

                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-2">
                                <h1 className="text-2xl sm:text-3xl font-bold tracking-tight flex items-center gap-2.5">
                                    <MessageSquare className="size-7 text-indigo-600 shrink-0" />
                                    Consensus Paragraphs
                                </h1>
                            </div>
                            <div className="flex items-center gap-2 flex-wrap text-sm text-muted-foreground mt-0.5">
                                <span className="font-medium text-foreground flex items-center gap-1.5">
                                    <BookOpen className="size-4 text-indigo-500" />
                                    {conversation.title}
                                </span>
                                {conversation.event && (
                                    <>
                                        <span>•</span>
                                        <span className="flex items-center gap-1">
                                            <Calendar className="size-3.5" />
                                            {conversation.event.title}
                                        </span>
                                    </>
                                )}
                            </div>
                            {conversation.description && (
                                <p className="text-sm text-muted-foreground mt-1 line-clamp-2">
                                    {conversation.description}
                                </p>
                            )}
                        </div>

                        <div className="flex items-center gap-2 shrink-0">
                            <Badge variant="secondary" className="px-3 py-1 text-xs font-medium">
                                {responses.length} {responses.length === 1 ? 'Response' : 'Responses'}
                            </Badge>
                        </div>
                    </div>
                </div>

                {/* Filter / Search Bar if there are multiple responses */}
                {responses.length > 0 && (
                    <div className="relative max-w-sm">
                        <Search className="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                        <Input
                            placeholder="Filter by group name or text..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="pl-8 text-sm"
                        />
                    </div>
                )}

                {/* Responses List */}
                {responses.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="py-16 flex flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                            <MessageSquare className="size-10 opacity-30" />
                            <p className="font-medium text-foreground">No consensus paragraphs yet</p>
                            <p className="text-sm">There are no responses submitted for this conversation.</p>
                        </CardContent>
                    </Card>
                ) : filteredResponses.length === 0 ? (
                    <Card className="border-dashed">
                        <CardContent className="py-12 flex flex-col items-center justify-center gap-2 text-center text-muted-foreground">
                            <Search className="size-8 opacity-30" />
                            <p className="font-medium text-foreground">No matching responses</p>
                            <p className="text-sm">No consensus paragraph matched &quot;{searchQuery}&quot;.</p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-1 md:grid-cols-2">
                        {filteredResponses.map((item) => {
                            const formattedDate = formatDate(item.submitted_at);
                            return (
                                <Card key={item.id} className="border-sidebar-border/60 flex flex-col justify-between">
                                    <CardHeader className="pb-3 border-b border-sidebar-border/30 bg-muted/15">
                                        <div className="flex items-center justify-between gap-2">
                                            <CardTitle className="text-base font-semibold flex items-center gap-2">
                                                <Users className="size-4 text-indigo-500 shrink-0" />
                                                {item.group_name}
                                            </CardTitle>
                                            {formattedDate && (
                                                <span className="text-xs text-muted-foreground">
                                                    {formattedDate}
                                                </span>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="pt-4 flex-1">
                                        <div className="whitespace-pre-wrap text-sm leading-relaxed text-foreground bg-muted/20 p-3.5 rounded-md border border-sidebar-border/20 min-h-[100px]">
                                            {item.consensus_text ? item.consensus_text : (
                                                <span className="italic text-muted-foreground">No text provided.</span>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                )}
            </div>
        </>
    );
}

ConsensusParagraphs.layout = (page: React.ReactNode) => {
    const props = page as any;
    const { conversation } = props?.props || {};

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
                {
                    title: `Consensus Paragraphs: ${conversation?.title || 'Conversation'}`,
                    href: `/pastoral/consensus-paragraphs/${conversation?.id || ''}`,
                },
            ]}
        >
            {page}
        </AppLayout>
    );
};
