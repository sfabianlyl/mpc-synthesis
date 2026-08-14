import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { FileText, Pencil, Users, ArrowLeft, BookOpen } from 'lucide-react';

interface User {
    id: number;
    name: string;
}

interface Response {
    id: number;
    conversation_id: number;
    group_id: number;
    consensus_text: string;
    submitted_at: string | null;
    submitter?: User;
}

interface Group {
    id: number;
    name: string;
}

/**
 * Individual Synthesis Report (individual submission from a synthesis member).
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
    status: string;
    submitted_at?: string | null;
    created_at: string;
    updated_at: string;
    group?: Group;
    synthesis?: User;
    conversation?: { id: number; title: string };
    responses?: Response[];
}

/**
 * Master Synthesis (aggregated "synthesis of syntheses" for a conversation).
 */
interface MasterSynthesis {
    id: number;
    conversation_id: number;
    convergence_text?: string | null;
    consideration_text?: string | null;
    proposals_text?: string | null;
    other_text?: string | null;
    status: 'draft' | 'reviewed' | 'submitted';
    created_at: string;
    updated_at: string;
}

interface PageProps {
    report: SynthesisReport;
    masterSynthesis?: MasterSynthesis | null;
    relatedReports?: SynthesisReport[];
}

// Category configuration for display
const CATEGORIES = [
    { 
        key: 'convergence', 
        label: 'Convergence', 
        description: 'Points of agreement across all groups',
        color: 'text-emerald-600 dark:text-emerald-400',
        bgColor: 'bg-emerald-50 dark:bg-emerald-950/20',
        borderColor: 'border-emerald-200 dark:border-emerald-800',
    },
    { 
        key: 'consideration', 
        label: 'Matters for Consideration', 
        description: 'Critical issues requiring attention',
        color: 'text-amber-600 dark:text-amber-400',
        bgColor: 'bg-amber-50 dark:bg-amber-950/20',
        borderColor: 'border-amber-200 dark:border-amber-800',
    },
    { 
        key: 'proposals', 
        label: 'Proposals', 
        description: 'Actionable recommendations and suggestions',
        color: 'text-blue-600 dark:text-blue-400',
        bgColor: 'bg-blue-50 dark:bg-blue-950/20',
        borderColor: 'border-blue-200 dark:border-blue-800',
    },
    { 
        key: 'others', 
        label: 'Others', 
        description: 'Additional insights and observations',
        color: 'text-violet-600 dark:text-violet-400',
        bgColor: 'bg-violet-50 dark:bg-violet-950/20',
        borderColor: 'border-violet-200 dark:border-violet-800',
    },
] as const;

const renderContent = (text: string | null): React.ReactNode => {
    if (!text || !text.trim()) {
        return <p className="text-sm text-muted-foreground italic">No content available</p>;
    }

    const lines = text.split('\n').filter((line) => line.trim());

    if (lines.length > 1) {
        return (
            <ul className="space-y-1">
                {lines.map((line, i) => (
                    <li key={i} className="flex items-start gap-2">
                        <span className="text-primary mt-0.5 flex-shrink-0">•</span>
                        <span className="text-sm">{line.replace(/^-+\s*/, '')}</span>
                    </li>
                ))}
            </ul>
        );
    }

    return <p className="text-sm whitespace-pre-wrap">{text}</p>;
};

export default function ReportDetail({ report, masterSynthesis, relatedReports = [] }: PageProps) {
    const isMaster = report.is_master_synthesis || masterSynthesis?.id;
    const currentMaster = masterSynthesis || (isMaster ? report as any : null);

    // Get categories with content from master synthesis or individual report
    const getCategories = () => {
        if (currentMaster) {
            return CATEGORIES.map(cat => ({
                ...cat,
                content: (currentMaster as any)[`${cat.key === 'consideration' ? 'consideration' : cat.key}_text`] as string | null,
            }));
        }
        
        return CATEGORIES.map(cat => ({
            ...cat,
            content: (report as any)[`${cat.key === 'consideration' ? 'consideration' : cat.key}_text`] as string | null,
        }));
    };

    const categories = getCategories();

    return (
        <>
            <Head title={`Report Detail - ${report.group?.name || `Conversation #${report.conversation_id}`}`} />
            <div className="p-8 space-y-8">
                {/* ─── Header ─────────────────────────────────────── */}
                <div className="flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">
                            {isMaster ? 'Master Synthesis' : 'Synthesis Report'} Detail
                        </h1>
                        <p className="text-muted-foreground mt-1">
                            {report.conversation?.title || `Conversation #${report.conversation_id}`}
                            {report.group && (
                                <span className="ml-2 text-sm">— {report.group.name}</span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Link href="/drafter">
                            <Button variant="outline" className="flex items-center gap-2">
                                <ArrowLeft className="size-4" /> Back to Dashboard
                            </Button>
                        </Link>
                    </div>
                </div>

                {/* ─── Report Info ────────────────────────────────── */}
                <Card>
                    <CardHeader>
                        <CardTitle>Report Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-4 text-sm">
                            <div>
                                <span className="text-muted-foreground block">Type:</span>
                                {isMaster ? (
                                    <Badge variant="secondary" className="bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 mt-1">
                                        Master Synthesis
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary" className="mt-1">Individual</Badge>
                                )}
                            </div>
                            <div>
                                <span className="text-muted-foreground block">Status:</span>
                                <span className={`ml-2 px-2 py-0.5 rounded text-xs font-medium ${
                                    report.status === 'submitted' ? 'bg-green-100 text-green-800' : 
                                    report.status === 'reviewed' ? 'bg-blue-100 text-blue-800' :
                                    report.status === 'draft' ? 'bg-yellow-100 text-yellow-800' : 
                                    'bg-gray-100 text-gray-800'
                                }`}>
                                    {report.status}
                                </span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block">Synthesis Member:</span>
                                <span className="ml-2">{report.synthesis?.name || 'Unknown'}</span>
                            </div>
                            <div>
                                <span className="text-muted-foreground block">Last Updated:</span>
                                <span className="ml-2">{new Date(report.updated_at).toLocaleString()}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* ─── Master Synthesis Overview (if available) ───── */}
                {masterSynthesis && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <BookOpen className="h-5 w-5 text-purple-600" />
                                Master Synthesis Overview
                            </CardTitle>
                            <CardDescription>
                                Aggregated synthesis for this conversation, organized by category.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {categories.map((cat) => (
                                <div key={cat.key} className={`p-4 rounded-lg border ${cat.bgColor} ${cat.borderColor}`}>
                                    <div className="flex items-center justify-between mb-2">
                                        <h4 className={`text-sm font-medium ${cat.color}`}>
                                            {cat.label}
                                        </h4>
                                        {(cat as any).content && (cat as any).content.trim() && (
                                            <Badge variant="outline" className="text-xs bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-300">
                                                <Pencil className="h-3 w-3 mr-1" /> Content
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground mb-2">{cat.description}</p>
                                    
                                    {/* Content */}
                                    {(cat as any).content && (cat as any).content.trim() && (
                                        <div>
                                            <p className="text-xs font-medium text-green-700 dark:text-green-300 mb-1">Master Content:</p>
                                            <div className="bg-white/50 dark:bg-black/10 rounded-md p-2 max-h-32 overflow-y-auto">
                                                {renderContent((cat as any).content)}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* ─── Synthesis Content ──────────────────────────── */}
                <div className="grid gap-6 md:grid-cols-2">
                    {categories.map((cat) => (
                        <Card key={cat.key}>
                            <CardHeader className={`${cat.bgColor} border-b ${cat.borderColor}`}>
                                <CardTitle className={`text-lg ${cat.color} flex items-center gap-2`}>
                                    {cat.label}
                                </CardTitle>
                                <CardDescription>{cat.description}</CardDescription>
                            </CardHeader>
                            <CardContent className="pt-4">
                                <Separator className="my-4" />

                                {/* Content */}
                                <div>
                                    <div className="flex items-center gap-2 mb-2">
                                        <Pencil className="h-4 w-4 text-green-600" />
                                        <span className="text-sm font-medium text-green-700 dark:text-green-300">Content:</span>
                                    </div>
                                    <div className="min-h-[60px]">
                                        {renderContent((cat as any).content)}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* ─── Source Group Consensuses (Traceability) ────── */}
                {report.responses && report.responses.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Source Group Consensuses (Traceability)
                            </CardTitle>
                            <CardDescription>
                                Original consensus responses from group members that contributed to this synthesis.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {report.responses.map((response) => (
                                <div key={response.id} className="p-4 rounded-lg border bg-muted/30">
                                    <div className="flex justify-between items-start mb-2">
                                        <div>
                                            <span className="font-medium text-sm">{response.submitter?.name || 'Anonymous'}</span>
                                            <p className="text-xs text-muted-foreground">Group #{response.group_id}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {response.submitted_at && (
                                                <span className="text-xs text-muted-foreground">
                                                    {new Date(response.submitted_at).toLocaleDateString()}
                                                </span>
                                            )}
                                            <Badge variant="outline" className="text-xs">
                                                Source
                                            </Badge>
                                        </div>
                                    </div>
                                    <Separator className="my-2" />
                                    <p className="text-sm whitespace-pre-wrap">{response.consensus_text}</p>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                {/* ─── Related Reports (Other Individual Syntheses) ─ */}
                {relatedReports.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Related Individual Syntheses</CardTitle>
                            <CardDescription>
                                Other individual synthesis submissions for this conversation.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-3">
                                {relatedReports.map((relatedReport) => (
                                    <Link 
                                        key={relatedReport.id} 
                                        href={`/drafter/report/${relatedReport.id}`}
                                        className="block p-3 rounded-lg border hover:bg-muted/50 transition-colors"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <div className="size-6 rounded-full bg-primary/10 flex items-center justify-center text-xs font-medium text-primary">
                                                    {relatedReport.synthesis?.name?.split(' ').map(n => n[0]).join('').slice(0, 2).toUpperCase()}
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium">{relatedReport.synthesis?.name || 'Unknown'}</p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {new Date(relatedReport.updated_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge 
                                                    variant={relatedReport.status === 'submitted' ? 'default' : 'secondary'}
                                                    className="text-xs"
                                                >
                                                    {relatedReport.status}
                                                </Badge>
                                            </div>
                                        </div>
                                    </Link>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
