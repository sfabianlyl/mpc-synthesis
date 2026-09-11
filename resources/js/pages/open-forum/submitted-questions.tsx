import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MessageSquareText } from 'lucide-react';
import { Button } from '@/components/ui/button';
import PublicLayout from '@/layouts/public-layout';

interface QuestionData {
    id: number;
    question: string;
    created_at: string;
}

interface SubmittedQuestionsProps {
    questions: QuestionData[];
}

export default function SubmittedQuestions({ questions }: SubmittedQuestionsProps) {
    const formatDate = (dateStr: string): string => {
        return new Date(dateStr).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <>
            <Head title="Submitted Questions" />
                <div className="mb-8 flex items-center justify-between">
                    <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100 sm:text-3xl">
                        Submitted Questions
                    </h1>
                    <Link href="/open-forum">
                        <Button variant="outline" size="sm" className="gap-2">
                            <ArrowLeft className="size-4" />
                            Back to Open Forum
                        </Button>
                    </Link>
                </div>

                {questions.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-blue-200 bg-blue-50/40 py-16 text-center dark:border-blue-800 dark:bg-blue-950/30">
                        <MessageSquareText className="mb-4 size-12 text-blue-300 dark:text-blue-700" />
                        <p className="text-lg font-medium text-slate-700 dark:text-slate-300">No questions submitted yet</p>
                        <p className="mt-1 text-base text-slate-500 dark:text-slate-400">
                            Be the first to share your questions with the community.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {questions.map((question, index) => (
                            <div
                                key={question.id}
                                className="rounded-xl border-2 border-blue-200 bg-white p-5 shadow-sm transition-shadow hover:shadow-md dark:border-blue-800 dark:bg-slate-800"
                            >
                                <div className="mb-3 flex items-center justify-between">
                                    <span className="flex size-8 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                        {index + 1}
                                    </span>
                                    <time className="text-xs text-slate-500 dark:text-slate-400" dateTime={question.created_at}>
                                        {formatDate(question.created_at)}
                                    </time>
                                </div>
                                <p className="whitespace-pre-wrap text-base leading-relaxed text-slate-800 dark:text-slate-200">
                                    {question.question}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
        </>
    );
}
