import { ReactNode } from 'react';

export default function PublicLayout({
    children,
}: {
    title: string;
    description: string;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-screen w-full items-center justify-center bg-gradient-to-br from-blue-50 via-indigo-50 to-slate-100 dark:from-blue-950 dark:via-indigo-950 dark:to-slate-950">
            <div className="w-full max-w-3xl px-6 py-12 sm:px-8 sm:py-16">
                {/* Logo */}
                <div className="mb-10 text-center">
                    <div className="mx-auto flex size-16 items-center justify-center rounded-2xl bg-blue-700 text-white shadow-lg shadow-blue-700/30 dark:bg-blue-600 dark:shadow-blue-600/40">
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            className="size-8"
                        >
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z" />
                        </svg>
                    </div>
                </div>

                {/* Main Card */}
                <div className="rounded-2xl border-2 border-blue-200 bg-white shadow-xl shadow-blue-200/40 dark:border-blue-800 dark:bg-slate-900 dark:shadow-blue-900/30">
                    <div className="px-8 py-10 sm:px-10 sm:py-12">{children}</div>
                </div>

                {/* Footer */}
                <p className="mt-8 text-center text-base text-blue-700 dark:text-blue-300">
                    Anonymous Facilitator Submission Portal
                </p>
            </div>
        </div>
    );
}
