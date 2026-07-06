import { Head } from '@inertiajs/react';
import { useState, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Send, Loader2, Users, CheckCircle } from 'lucide-react';
import AppLayout from '@/layouts/app/app-sidebar-layout';

interface Conversation {
    id: number;
    title: string;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
}

interface SubmissionStats {
    total_groups: number;
    submitted_groups: number;
    responses: Array<{
        id: number;
        group_name: string;
        facilitator: string | null;
        consensus_text: string;
    }>;
}

interface PageProps {
    conversations: Conversation[];
    selectedConversationId: number | null;
}

export default function Chat({ conversations, selectedConversationId }: PageProps) {
    const [selectedConversation, setSelectedConversation] = useState<number | null>(selectedConversationId);
    const [stats, setStats] = useState<SubmissionStats | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [input, setInput] = useState('');
    const [loading, setLoading] = useState(false);
    const [statsLoading, setStatsLoading] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    // Load stats when conversation is selected
    useEffect(() => {
        if (selectedConversation) {
            loadStats();
        }
    }, [selectedConversation]);

    // Auto-scroll to bottom of messages
    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    const loadStats = async () => {
        if (!selectedConversation) return;
        
        setStatsLoading(true);
        try {
            const response = await (window as any).axios.get('/admin/chat/details', {
                params: { conversation_id: selectedConversation }
            });
            setStats(response.data);
        } catch (error) {
            console.error('Failed to load stats:', error);
        } finally {
            setStatsLoading(false);
        }
    };

    const sendMessage = async () => {
        if (!input.trim() || !selectedConversation || loading) return;

        const userMessage: ChatMessage = { role: 'user', content: input };
        setMessages(prev => [...prev, userMessage]);
        setInput('');
        setLoading(true);

        try {
            const response = await (window as any).axios.post('/admin/chat/message', {
                conversation_id: selectedConversation,
                message: input,
            });

            const assistantMessage: ChatMessage = { 
                role: 'assistant', 
                content: response.data.reply 
            };
            setMessages(prev => [...prev, assistantMessage]);
        } catch (error) {
            console.error('Failed to send message:', error);
        } finally {
            setLoading(false);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        sendMessage();
    };

    const progressPercent = stats ? Math.round((stats.submitted_groups / stats.total_groups) * 100) : 0;

    return (
        <AppLayout breadcrumbs={[
            { title: 'Dashboard', href: '/dashboard' },
            { title: 'Admin Chat', href: '/admin/chat' },
        ]}>
            <Head title="Admin Chat - MPC Synthesis" />
            
            <div className="flex h-[calc(100vh-4rem)]">
                {/* Sidebar */}
                <div className="w-80 border-r p-4 flex flex-col gap-4 overflow-y-auto">
                    {/* Session Selector */}
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-sm">Select Session</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <select
                                value={selectedConversation || ''}
                                onChange={(e) => setSelectedConversation(e.target.value ? Number(e.target.value) : null)}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option value="">-- Choose a session --</option>
                                {conversations.map(conv => (
                                    <option key={conv.id} value={conv.id}>{conv.title}</option>
                                ))}
                            </select>
                        </CardContent>
                    </Card>

                    {/* Submission Stats */}
                    {stats && (
                        <Card>
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm flex items-center gap-2">
                                    <Users className="h-4 w-4" />
                                    Submission Progress
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex items-center justify-between text-sm">
                                    <span>{stats.submitted_groups} / {stats.total_groups}</span>
                                    <span className="text-muted-foreground">{progressPercent}%</span>
                                </div>
                                <div className="w-full bg-secondary rounded-full h-2">
                                    <div 
                                        className="bg-primary h-2 rounded-full" 
                                        style={{ width: `${progressPercent}%` }}
                                    />
                                </div>
                                
                                {/* Response List */}
                                <div className="space-y-2 mt-4">
                                    {stats.responses.map(response => (
                                        <div key={response.id} className="flex items-center gap-2 text-sm">
                                            <CheckCircle className="h-4 w-4 text-green-500" />
                                            <span>{response.group_name}</span>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                        </Card>
                    )}

                    {statsLoading && (
                        <div className="flex items-center justify-center py-4">
                            <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
                        </div>
                    )}
                </div>

                {/* Main Chat Area */}
                <div className="flex-1 flex flex-col">
                    {!selectedConversation ? (
                        <div className="flex-1 flex items-center justify-center">
                            <div className="text-center text-muted-foreground">
                                <p className="text-lg">Select a session to begin</p>
                                <p className="text-sm mt-2">Choose a conversation from the sidebar to view submission stats and chat with the LLM.</p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {/* Messages */}
                            <div className="flex-1 overflow-y-auto p-4 space-y-4">
                                {messages.length === 0 ? (
                                    <div className="flex items-center justify-center h-full text-muted-foreground">
                                        <p>Ask a question about the submitted responses</p>
                                    </div>
                                ) : (
                                    messages.map((msg, idx) => (
                                        <div key={idx} className={`flex gap-3 ${msg.role === 'user' ? 'justify-end' : ''}`}>
                                            {msg.role === 'assistant' && (
                                                <Avatar className="h-8 w-8">
                                                    <AvatarFallback className="bg-primary text-primary-foreground">AI</AvatarFallback>
                                                </Avatar>
                                            )}
                                            <div className={`max-w-[70%] rounded-lg p-3 ${
                                                msg.role === 'user' 
                                                    ? 'bg-primary text-primary-foreground' 
                                                    : 'bg-muted'
                                            }`}>
                                                <p className="text-sm whitespace-pre-wrap">{msg.content}</p>
                                            </div>
                                        </div>
                                    ))
                                )}
                                <div ref={messagesEndRef} />
                            </div>

                            {/* Input */}
                            <form onSubmit={handleSubmit} className="border-t p-4 flex gap-2">
                                <Input
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    placeholder="Ask about the submitted responses..."
                                    className="flex-1"
                                    disabled={loading}
                                />
                                <Button type="submit" disabled={loading || !input.trim()}>
                                    {loading ? (
                                        <Loader2 className="h-4 w-4 animate-spin" />
                                    ) : (
                                        <Send className="h-4 w-4" />
                                    )}
                                </Button>
                            </form>
                        </>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}
