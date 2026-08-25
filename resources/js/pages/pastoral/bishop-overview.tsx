import { Head } from '@inertiajs/react';
import { Printer, Shield, BookOpen, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';

interface Event {
    id: number;
    title: string;
    start_date?: string;
    end_date?: string | null;
}

interface MasterDecision {
    id: number | null;
    master_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    submitted_by: string | null;
    generated_by: string | null;
    generated_at: string | null;
}

interface ConversationPartItem {
    id: number;
    title: string;
    diocese_decision_part: {
        id: number;
        diocesan_text: string | null;
        is_submitted: boolean;
        submitted_at: string | null;
    } | null;
    national_decision_part: {
        id: number;
        national_text: string | null;
        is_submitted: boolean;
        submitted_at: string | null;
    } | null;
}

interface BishopPageProps {
    event: Event;
    masterDecision: MasterDecision | null;
    conversationsWithParts: ConversationPartItem[];
}

const formatDate = (dateStr?: string | null): string => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

export default function BishopOverview({ event, masterDecision, conversationsWithParts = [] }: BishopPageProps) {
    const handlePrint = () => {
        window.print();
    };

    return (
        <>
            <Head title={`Bishop's Overview - ${event.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-4xl mx-auto print:p-0">
                {/* Header Section */}
                <div className="flex flex-col gap-2 print:gap-1">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-3xl font-bold tracking-tight">Bishop's Executive Briefing</h1>
                            <p className="text-sm text-muted-foreground mt-0.5">
                                Pastoral Decisions Summary & Session Breakdowns
                            </p>
                        </div>
                        <Button variant="outline" size="sm" className="gap-2 print:hidden" onClick={handlePrint}>
                            <Printer className="size-4" />
                            Print Briefing
                        </Button>
                    </div>
                    <Separator />
                    <div className="flex flex-col gap-0.5 mt-2">
                        <p className="text-xl font-semibold">{event.title}</p>
                        <p className="text-xs text-muted-foreground">
                            {formatDate(event.start_date)} — {event.end_date ? formatDate(event.end_date) : 'Ongoing'}
                        </p>
                    </div>
                </div>

                {/* Master Decision Section */}
                <Card className="border-2 print:border-none print:shadow-none">
                    <CardHeader className="pb-3 bg-muted/20 print:bg-transparent">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Shield className="size-5 text-indigo-600 print:text-black" />
                            Official Master Pastoral Decision
                        </CardTitle>
                        {masterDecision?.submitted_by && (
                            <CardDescription className="text-xs">
                                Finalized by {masterDecision.submitted_by}
                                {masterDecision.submitted_at && ` on ${new Date(masterDecision.submitted_at).toLocaleDateString()}`}
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent className="pt-4">
                        {masterDecision?.master_text ? (
                            <div className="whitespace-pre-wrap text-sm leading-relaxed text-foreground min-h-[160px] p-4 bg-muted/30 print:bg-transparent rounded-md font-mono">
                                {masterDecision.master_text}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center min-h-[120px] text-muted-foreground">
                                <p className="text-sm italic">No master pastoral decision has been finalized yet.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* DioceseDecisionParts */}
                <div className="space-y-4">
                    <h2 className="text-lg font-semibold flex items-center gap-2">
                        <BookOpen className="size-5 text-indigo-600 print:text-black" />
                        DioceseDecisionParts
                    </h2>

                    {conversationsWithParts.length > 0 ? (
                        <div className="flex flex-col gap-4">
                            {conversationsWithParts.map((item) => (
                                <Card key={item.id} className="print:border-none print:shadow-none border">
                                    <CardHeader className="pb-2 bg-muted/15 print:bg-transparent">
                                        <div className="flex items-center justify-between">
                                            <CardTitle className="text-sm font-semibold">{item.title}</CardTitle>
                                            {(item.diocese_decision_part?.is_submitted || item.national_decision_part?.is_submitted) && (
                                                <span className="text-xs text-emerald-600 font-medium flex items-center gap-1">
                                                    <CheckCircle2 className="size-3" /> Finalized
                                                </span>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="pt-3">
                                        {item.diocese_decision_part?.diocesan_text || item.national_decision_part?.national_text ? (
                                            <>
                                                {item.diocese_decision_part?.diocesan_text && (
                                                    <div className="mb-3">
                                                        <p className="text-xs font-medium text-muted-foreground mb-1">Diocesan Text</p>
                                                        <div className="whitespace-pre-wrap text-xs leading-relaxed text-foreground p-3 bg-muted/20 print:bg-transparent rounded-md font-mono">
                                                            {item.diocese_decision_part.diocesan_text}
                                                        </div>
                                                    </div>
                                                )}
                                                {item.national_decision_part?.national_text && (
                                                    <div>
                                                        <p className="text-xs font-medium text-muted-foreground mb-1">National Text</p>
                                                        <div className="whitespace-pre-wrap text-xs leading-relaxed text-foreground p-3 bg-muted/20 print:bg-transparent rounded-md font-mono">
                                                            {item.national_decision_part.national_text}
                                                        </div>
                                                    </div>
                                                )}
                                            </>
                                        ) : (
                                            <p className="text-xs italic text-muted-foreground">
                                                No decision part finalized for this session.
                                            </p>
                                        )}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : (
                        <Card className="border-dashed bg-muted/20">
                            <CardContent className="py-8 text-center text-muted-foreground text-xs">
                                No discernment sessions found for this event.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

BishopOverview.layout = (page: React.ReactNode) => {
    const props = page as any;
    const { event } = props?.props || {};

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Pastoral Dashboard', href: '/pastoral/dashboard' },
                { title: `Bishop's Overview: ${event?.title || 'Event'}`, href: `/pastoral/bishop-overview/${event?.id || ''}` },
            ]}
        >
            {page}
        </AppLayout>
    );
};
