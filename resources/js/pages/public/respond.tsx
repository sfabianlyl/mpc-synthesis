import { Head, useForm, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { AlertCircle, CheckCircle2, Send, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';

interface EventData {
    id: number;
    title: string;
    start_date: string;
    location?: string;
}

interface GroupData {
    id: number;
    name: string;
}

interface ConversationData {
    id: number;
    title: string;
    description?: string;
}

interface RespondProps {
    event_id: number;
    group_id: number;
    event: EventData;
    group: GroupData;
    conversations: ConversationData[];
}

export default function Respond({ event_id, group_id, event, group, conversations }: RespondProps) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };

    const { data, setData, post, processing, errors, wasSuccessful } = useForm({
        conversation_id: conversations.length > 0 ? String(conversations[0].id) : '',
        consensus_text: '',
    });

    // Ensure first conversation is selected
    useEffect(() => {
        if (conversations.length > 0 && (!data.conversation_id || data.conversation_id === '')) {
            setData('conversation_id', String(conversations[0].id));
        }
    }, [conversations, data.conversation_id]);

    // Handle success redirect after submission
    const isSuccess = wasSuccessful;

    const formatDate = (dateStr: string): string => {
        return new Date(dateStr).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.conversation_id || !data.consensus_text.trim()) return;

        post(`/respond/${event_id}/${group_id}`, {
            onSuccess: () => {
                // Show success message
            },
        });
    };

    // Success state
    if (isSuccess) {
        return (
            <>
                <Head title="Submission Successful" />
                <div className="flex min-h-screen w-full items-center justify-center bg-gradient-to-br from-blue-50 via-indigo-50 to-slate-100 dark:from-blue-950 dark:via-indigo-950 dark:to-slate-950">
                    <div className="w-full max-w-3xl px-6 py-12 sm:px-8 sm:py-16">
                        <div className="rounded-2xl border-2 border-emerald-300 bg-white shadow-xl shadow-emerald-200/40 dark:border-emerald-700 dark:bg-slate-900 dark:shadow-emerald-900/30">
                            <div className="px-8 py-10 sm:px-10 sm:py-12 text-center">
                                <div className="mx-auto mb-6 flex size-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-900/40">
                                    <CheckCircle2 className="size-10 text-emerald-700 dark:text-emerald-400" />
                                </div>
                                <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-50" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                                    Submission Successful
                                </h1>
                                <p className="mt-4 text-xl text-slate-700 dark:text-slate-300" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                                    Your group's consensus has been submitted successfully. Thank you for your participation!
                                </p>
                                {flash?.success && (
                                    <p className="mt-4 text-lg text-emerald-700 dark:text-emerald-400" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                                        {flash.success}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title={`Submit Response - ${event.title}`} />

            {/* Error Alert */}
            {(Object.keys(errors).length > 0 || flash?.error) && (
                <div className="mb-8 rounded-xl border-2 border-red-300 bg-red-50 p-5 dark:border-red-800 dark:bg-red-950/40">
                    <div className="flex items-start gap-4">
                        <AlertCircle className="mt-1 size-6 text-red-600 dark:text-red-400" />
                        <div className="flex-1">
                            {Object.keys(errors).length > 0 && (
                                <ul className="list-disc pl-5 text-lg text-red-800 dark:text-red-300" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                                    {Object.values(errors).map((error, index) => (
                                        <li key={index}>{error}</li>
                                    ))}
                                </ul>
                            )}
                            {flash?.error && (
                                <p className="text-lg text-red-800 dark:text-red-300" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                                    {flash.error}
                                </p>
                            )}
                        </div>
                    </div>
                </div>
            )}

            <form onSubmit={handleSubmit} className="p-6">
                {/* Event & Group Info */}
                <div className="mb-10 space-y-5">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900 dark:text-slate-50" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                            {event.title}
                        </h1>
                    </div>

                    {/* Info badges with larger icons and clearer labels */}
                    <div className="flex flex-wrap items-center gap-4 text-lg text-slate-700 dark:text-slate-300" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                        <div className="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="size-5 text-blue-700 dark:text-blue-400">
                                <rect width="20" height="18" x="2" y="3" rx="2" />
                                <path d="M8 21h8" />
                                <path d="M12 17v4" />
                            </svg>
                            <span>Your Group: <strong className="text-slate-900 dark:text-slate-50">{group.name}</strong></span>
                        </div>
                        <div className="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="size-5 text-blue-700 dark:text-blue-400">
                                <path d="M8 2v4" />
                                <path d="M16 2v4" />
                                <rect width="18" height="18" x="3" y="4" rx="2" />
                                <path d="M3 10h18" />
                            </svg>
                            <span>{formatDate(event.start_date)}</span>
                        </div>
                        
                    </div>
                </div>

                {/* Conversation Selection */}
                <div className="mb-8">
                    <Label htmlFor="conversation_id" className="mb-3 block text-xl font-semibold text-slate-800 dark:text-slate-200" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                        Conversation / Session
                    </Label>
                    <p className="mb-3 text-base text-slate-600 dark:text-slate-400" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                        Please select the conversation you are submitting for.
                    </p>
                    <Select
                        value={data.conversation_id}
                        onValueChange={(value) => setData('conversation_id', value)}
                    >
                        <SelectTrigger className="w-full h-14 text-lg" id="conversation_id" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                            <SelectValue placeholder="Select a conversation" />
                        </SelectTrigger>
                        <SelectContent>
                            {conversations.map((conversation) => (
                                <SelectItem key={conversation.id} value={String(conversation.id)} className="text-lg h-12" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                                    {conversation.title}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {errors.conversation_id && (
                        <p className="mt-2 text-lg text-red-700 dark:text-red-400" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>{errors.conversation_id}</p>
                    )}
                </div>

                {/* Consensus Text */}
                <div className="mb-10">
                    <Label htmlFor="consensus_text" className="mb-3 block text-xl font-semibold text-slate-800 dark:text-slate-200" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                        Group Consensus
                    </Label>
                    <p className="mb-3 text-base text-slate-600 dark:text-slate-400" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                        Please type your group's consensus in a single paragraph below, in not more than 200 words OR 10 sentences. This will be recorded as your group's official response.
                    </p>
                    <Textarea
                        id="consensus_text"
                        value={data.consensus_text}
                        onChange={(e) => setData('consensus_text', e.target.value)}
                        rows={12}
                        placeholder="Type your group's consensus here..."
                        className="w-full resize-y rounded-xl border-2 border-blue-200 bg-white text-lg transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-blue-800 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-900"
                        style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
                        autoFocus
                    />
                    {errors.consensus_text && (
                        <p className="mt-2 text-lg text-red-700 dark:text-red-400" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>{errors.consensus_text}</p>
                    )}
                </div>

                {/* Submit Button - larger with clearer label */}
                <Button
                    type="submit"
                    disabled={!data.conversation_id || !data.consensus_text.trim() || processing}
                    className="w-full h-16 text-xl gap-2 bg-blue-700 text-white hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed rounded-xl shadow-lg shadow-blue-700/30 dark:bg-blue-600 dark:hover:bg-blue-700 dark:shadow-blue-600/40"
                    style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}
                >
                    {processing ? (
                        <>
                            <Loader2 className="size-6 animate-spin" />
                            Submitting...
                        </>
                    ) : (
                        <>
                            <Send className="size-6" />
                            Submit Your Group's Response
                        </>
                    )}
                </Button>

                {/* Helper text below button */}
                <p className="mt-4 text-center text-base text-slate-500 dark:text-slate-400" style={{ fontFamily: 'Georgia, "Times New Roman", serif' }}>
                    Make sure to review your response before submitting. You cannot edit it after submission, but you may resubmit another consensus. Only the latest submission will be received.                
                </p>
            </form>
        </>
    );
}
