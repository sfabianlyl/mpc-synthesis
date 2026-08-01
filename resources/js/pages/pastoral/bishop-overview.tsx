import { Head } from '@inertiajs/react';
import { Printer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';

interface Event {
    id: number;
    title: string;
    start_date: string;
    end_date: string | null;
}

interface MasterDecisionSummary {
    id: number | null;
    master_text: string | null;
    is_editable: boolean;
    generated_by: string | null;
    generated_at: string | null;
}

interface DioceseDecisionDraft {
    id: number;
    event_id: number;
    user_id: number;
    diocese_id: number | null;
    draft_text: string | null;
    is_submitted: boolean;
    submitted_at: string | null;
    user?: {
        name: string;
        email: string;
    };
    diocese?: {
        name: string;
    };
}

interface BishopPageProps {
    event: Event;
    masterDecisionSummary: MasterDecisionSummary | null;
    dioceseDecisionDrafts: DioceseDecisionDraft[];
}

const formatDate = (dateStr: string | null): string => {
    if (!dateStr) return 'N/A';
    return new Date(dateStr).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function BishopOverview({ event, masterDecisionSummary, dioceseDecisionDrafts }: BishopPageProps) {
    const handlePrint = () => {
        window.print();
    };

    return (
        <>
            <Head title={`Bishop's Overview - ${event.title}`} />
            <div className="p-8 flex flex-col gap-6 max-w-4xl mx-auto print:p-0">
                {/* Header Section */}
                <div className="flex flex-col gap-2 print:gap-1">
                    <h1 className="text-3xl font-bold tracking-tight">Bishop's Overview</h1>
                    <Separator />
                    <div className="flex flex-col gap-1 mt-2">
                        <p className="text-xl font-semibold">{event.title}</p>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(event.start_date)} — {event.end_date ? formatDate(event.end_date) : 'Ongoing'}
                        </p>
                    </div>
                </div>

                {/* Master Decision Display */}
                <Card className="border-2 print:border-none print:shadow-none">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-lg">Master Decision</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {masterDecisionSummary?.master_text ? (
                            <div className="whitespace-pre-wrap text-base leading-relaxed text-foreground min-h-[200px] p-4 bg-muted/30 rounded-md">
                                {masterDecisionSummary.master_text}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center min-h-[200px] text-muted-foreground">
                                <p className="text-base italic">No master decision has been generated yet.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Diocese Decision Drafts */}
                <Card className="print:border-none print:shadow-none">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-lg">Diocese Decision Drafts</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {dioceseDecisionDrafts.length?? 0 > 0 ? (
                            <div className="flex flex-col gap-4">
                                {dioceseDecisionDrafts.map((draft) => (
                                    <div key={draft.id} className="p-4 bg-muted/30 rounded-md border">
                                        <div className="flex flex-col gap-2">
                                            <div className="flex items-center justify-between">
                                                <div className="flex flex-col gap-0.5">
                                                    {draft.diocese && (
                                                        <p className="text-sm text-muted-foreground">
                                                            Diocese: {draft.diocese.name}
                                                        </p>
                                                    )}
                                                </div>
                                                
                                            </div>
                                            <Separator />
                                            {draft.draft_text ? (
                                                <div className="whitespace-pre-wrap text-sm leading-relaxed text-foreground">
                                                    {draft.draft_text}
                                                </div>
                                            ) : (
                                                <p className="text-sm italic text-muted-foreground">No draft text provided.</p>
                                            )}
                                            
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center min-h-[100px] text-muted-foreground">
                                <p className="text-base italic">No diocese decision drafts have been submitted yet.</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Print Button */}
                <div className="flex justify-end print:hidden">
                    <Button variant="outline" size="sm" className="gap-2" onClick={handlePrint}>
                        <Printer className="size-4" />
                        Print / Export
                    </Button>
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
                { title: "Bishop's Overview", href: `/pastoral/bishop/${event?.id}` },
            ]}
        >
            {page}
        </AppLayout>
    );
};
