import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Send, Users, Calendar } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { Conversation, Group } from '@/types/models';

interface PageProps {
    groups: Group[];
    currentGroup?: Group;
    conversations: Conversation[];
}

export default function Submit({ groups, currentGroup, conversations }: PageProps) {
    const { flash } = usePage().props as any;

    const { data, setData, post, processing, errors, reset, wasSuccessful } = useForm({
        conversation_id: conversations.length > 0 ? String(conversations[0].id) : '',
        group_id: currentGroup ? String(currentGroup.id) : '',
        consensus_text: '',
    });

    useEffect(() => {
        if (currentGroup) {
            setData('group_id', String(currentGroup.id));
        }
    }, [currentGroup]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.conversation_id || !data.group_id || !data.consensus_text.trim()) {
return;
}

        post('/respond', {
            onSuccess: () => {
                reset('consensus_text');
            },
        });
    };

    return (
        <>
            <Head title="Facilitator Portal - Submit Response" />
            <div className="p-8 max-w-4xl mx-auto flex flex-col gap-6">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="text-3xl font-bold tracking-tight">Group Consensus Submission</h1>
                    <p className="text-muted-foreground">
                        Submit the finalized consensus paragraph for your group for the selected conference session.
                    </p>
                </div>

                {flash?.success && (
                    <div className="flex items-center gap-3 p-4 rounded-lg border bg-emerald-500/10 border-emerald-500/20 text-emerald-600">
                        <CheckCircle className="size-5 shrink-0" />
                        <span className="text-sm font-medium">{flash.success}</span>
                    </div>
                )}

                {errors.consensus_text && (
                    <div className="flex items-center gap-3 p-4 rounded-lg border bg-destructive/10 border-destructive/20 text-destructive">
                        <AlertCircle className="size-5 shrink-0" />
                        <span className="text-sm font-medium">{errors.consensus_text}</span>
                    </div>
                )}

                <Card className="border-sidebar-border/50">
                    <CardHeader className="border-b border-sidebar-border/30 bg-muted/20">
                        <CardTitle className="flex items-center gap-2 text-lg">
                            <Send className="size-5 text-primary" />
                            Consensus Form
                        </CardTitle>
                        <CardDescription>
                            All inputs are required. Responses cannot be modified once submitted.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Conversation Selector */}
                            <div className="space-y-2">
                                <Label htmlFor="conversation" className="flex items-center gap-2">
                                    <Calendar className="size-4 text-muted-foreground" />
                                    Conference Session
                                </Label>
                                {conversations.length === 0 ? (
                                    <div className="text-sm text-destructive font-medium p-3 border border-destructive/20 bg-destructive/10 rounded-md">
                                        No active sessions are open for submissions right now.
                                    </div>
                                ) : (
                                    <Select
                                        value={data.conversation_id}
                                        onValueChange={(val) => setData('conversation_id', val)}
                                    >
                                        <SelectTrigger id="conversation">
                                            <SelectValue placeholder="Select Session" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {conversations.map((c) => (
                                                <SelectItem key={c.id} value={String(c.id)}>
                                                    {c.title}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {errors.conversation_id && (
                                    <p className="text-xs text-destructive">{errors.conversation_id}</p>
                                )}
                            </div>

                            {/* Group Selector */}
                            <div className="space-y-2">
                                <Label htmlFor="group" className="flex items-center gap-2">
                                    <Users className="size-4 text-muted-foreground" />
                                    Group Assignment
                                </Label>
                                {currentGroup ? (
                                    <div className="flex items-center justify-between p-3 border rounded-md bg-muted/40 text-sm font-semibold">
                                        <span>{currentGroup.name}</span>
                                        <span className="text-xs text-muted-foreground bg-primary/10 text-primary px-2.5 py-0.5 rounded-full">
                                            Pre-assigned
                                        </span>
                                    </div>
                                ) : (
                                    <Select
                                        value={data.group_id}
                                        onValueChange={(val) => setData('group_id', val)}
                                    >
                                        <SelectTrigger id="group">
                                            <SelectValue placeholder="Select a Group" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {groups.map((g) => (
                                                <SelectItem key={g.id} value={String(g.id)}>
                                                    {g.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {errors.group_id && (
                                    <p className="text-xs text-destructive">{errors.group_id}</p>
                                )}
                            </div>

                            {/* Response content */}
                            <div className="space-y-2">
                                <Label htmlFor="consensus_text">Consensus Paragraph</Label>
                                <Textarea
                                    id="consensus_text"
                                    value={data.consensus_text}
                                    onChange={(e) => setData('consensus_text', e.target.value)}
                                    rows={8}
                                    placeholder="Enter your group's consensus paragraph here..."
                                    className="resize-none"
                                />
                                <div className="flex justify-between text-xs text-muted-foreground pt-1">
                                    <span>Recommended length: ~1 paragraph (approx. 100-200 words)</span>
                                    <span>{data.consensus_text.length} characters</span>
                                </div>
                            </div>

                            <Button 
                                type="submit" 
                                disabled={!data.conversation_id || !data.group_id || !data.consensus_text.trim() || processing}
                                className="w-full sm:w-auto"
                            >
                                {processing ? 'Submitting...' : 'Submit Consensus'}
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Submit.layout = (page: React.ReactNode) => <AppLayout breadcrumb="Submit Consensus">{page}</AppLayout>;