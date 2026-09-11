import { Head, useForm } from '@inertiajs/react';
import { CheckCircle2, Loader2, Send } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import PublicLayout from '@/layouts/public-layout';

export default function OpenForumSubmit() {
    const { data, setData, post, processing, errors } = useForm<{ question: string }>({
        question: '',
    });

    const [showSubmitted, setShowSubmitted] = useState(false);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!data.question.trim() || processing || showSubmitted) {
            return;
        }

        post('/open-forum', {
            preserveScroll: true,
            onSuccess: () => {
                setShowSubmitted(true);

                // Reset the form after a few seconds so the user can submit again
                timerRef.current = setTimeout(() => {
                    setData('question', '');
                    setShowSubmitted(false);
                }, 3000);
            },
        });
    };

    return (
        <>
            <Head title="Open Forum" />
            <PublicLayout title="Open Forum" description="Submit your questions">
                <h1 className="mb-2 text-center text-2xl font-bold text-slate-900 dark:text-slate-100 sm:text-3xl">
                    Open Forum
                </h1>
                <p className="mb-8 text-center text-base text-slate-600 dark:text-slate-400">
                    Share your questions with the community. Your submission is anonymous and will be listed for everyone to see.
                </p>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div>
                        <Label htmlFor="question" className="mb-2 block text-base font-semibold text-slate-800 dark:text-slate-200">
                            Please enter your questions here
                        </Label>
                        <Textarea
                            id="question"
                            value={data.question}
                            onChange={(e) => setData('question', e.target.value)}
                            rows={8}
                            placeholder="Type your questions here..."
                            className="w-full resize-y rounded-xl border-2 border-blue-200 bg-white text-base transition-colors focus:border-blue-500 focus:ring-2 focus:ring-blue-200 dark:border-blue-800 dark:bg-slate-800 dark:text-slate-100 dark:focus:border-blue-400 dark:focus:ring-blue-900"
                            disabled={showSubmitted}
                            autoFocus
                        />
                        {errors.question && (
                            <p className="mt-2 text-sm text-red-700 dark:text-red-400">{errors.question}</p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        disabled={!data.question.trim() || processing || showSubmitted}
                        className="w-full h-12 gap-2 rounded-xl text-base bg-blue-700 text-white shadow-lg shadow-blue-700/30 hover:bg-blue-800 dark:bg-blue-600 dark:hover:bg-blue-700"
                    >
                        {showSubmitted ? (
                            <>
                                <CheckCircle2 className="size-5" />
                                Submitted
                            </>
                        ) : processing ? (
                            <>
                                <Loader2 className="size-5 animate-spin" />
                                Submitting...
                            </>
                        ) : (
                            <>
                                <Send className="size-5" />
                                Submit Your Questions
                            </>
                        )}
                    </Button>
                </form>
            </PublicLayout>
        </>
    );
}
