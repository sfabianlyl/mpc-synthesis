import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, ClipboardList, FileText, LayoutGrid, Users, ArrowRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { NavItem } from '@/types';
import { RoleSwitcher, DiscernmentMode, getPastoralNavigation, getSynthesisNavigation } from '@/components/role-switcher';

interface PageProps {
    currentMode: DiscernmentMode;
}

export default function Dashboard({ currentMode }: PageProps) {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    // Determine which sections to show based on mode
    const showPastoral = currentMode === 'pastoral' || currentMode === 'synthesis';
    const showSynthesis = currentMode === 'synthesis' || currentMode === 'pastoral';

    return (
        <>
            <Head title="Discernment Dashboard" />
            <div className="p-8 flex flex-col gap-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Discernment Dashboard</h1>
                        <p className="text-muted-foreground mt-1">
                            Welcome, {user?.name}. As a discernment member, you have full access to both pastoral tools and synthesis member capabilities.
                        </p>
                    </div>

                    {/* Current mode badge */}
                    <Badge
                        variant="outline"
                        className={`px-3 py-1 text-sm ${
                            currentMode === 'pastoral'
                                ? 'bg-indigo-50 dark:bg-indigo-950/20 text-indigo-600 border-indigo-200 dark:border-indigo-800'
                                : 'bg-emerald-50 dark:bg-emerald-950/20 text-emerald-600 border-emerald-200 dark:border-emerald-800'
                        }`}
                    >
                        <span className="inline-block size-2 rounded-full bg-current mr-2 animate-pulse" />
                        Currently in {currentMode === 'pastoral' ? 'Pastoral' : 'Synthesis'} Mode
                    </Badge>
                </div>

                {/* RoleSwitcher for easy mode toggling */}
                <Card className="border-primary/20 bg-primary/5">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-lg flex items-center gap-2">
                            <Users className="size-5" />
                            Quick Mode Switch
                        </CardTitle>
                        <CardDescription>
                            Toggle between Pastoral and Synthesis modes to access different sections.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col sm:flex-row gap-3">
                            <Link href="/pastoral/dashboard" className="flex-1">
                                <Button
                                    variant={currentMode === 'pastoral' ? 'default' : 'outline'}
                                    className={`w-full justify-start gap-2 ${
                                        currentMode === 'pastoral'
                                            ? 'bg-indigo-600 hover:bg-indigo-700'
                                            : ''
                                    }`}
                                >
                                    <BookOpen className="size-4" />
                                    Switch to Pastoral Mode
                                </Button>
                            </Link>
                            <Link href="/synthesis" className="flex-1">
                                <Button
                                    variant={currentMode === 'synthesis' ? 'default' : 'outline'}
                                    className={`w-full justify-start gap-2 ${
                                        currentMode === 'synthesis'
                                            ? 'bg-indigo-600 hover:bg-indigo-700'
                                            : ''
                                    }`}
                                >
                                    <ClipboardList className="size-4" />
                                    Switch to Synthesis Mode
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>

                {/* Section Cards */}
                <div className="grid gap-6 md:grid-cols-2">
                    {/* Pastoral Section */}
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30">
                            <CardTitle className="text-lg flex items-center gap-2">
                                <BookOpen className="size-5 text-indigo-600" />
                                Pastoral Section
                            </CardTitle>
                            <CardDescription>
                                Access pastoral tools, reports, decisions overview, and decision drafting.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6 space-y-4">
                            <p className="text-sm text-muted-foreground">
                                The Pastoral section provides tools for pastoral care, group responses, decisions overview, and decision drafting.
                            </p>

                            {/* Pastoral Navigation Links */}
                            <div className="space-y-2">
                                <Link href="/pastoral/dashboard" className="block">
                                    <div className="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <LayoutGrid className="size-4 text-muted-foreground" />
                                            <span className="text-sm font-medium">Pastoral Dashboard</span>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </div>
                                </Link>
                                <Link href="/pastoral/dashboard" className="block">
                                    <div className="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <FileText className="size-4 text-muted-foreground" />
                                            <span className="text-sm font-medium">Pastoral Reports</span>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </div>
                                </Link>
                                <Link href="/pastoral/dashboard" className="block">
                                    <div className="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <ClipboardList className="size-4 text-muted-foreground" />
                                            <span className="text-sm font-medium">Decisions Overview</span>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Synthesis Section */}
                    <Card className="border-sidebar-border/50">
                        <CardHeader className="bg-muted/15 border-b border-sidebar-border/30">
                            <CardTitle className="text-lg flex items-center gap-2">
                                <ClipboardList className="size-5 text-emerald-600" />
                                Synthesis Section
                            </CardTitle>
                            <CardDescription>
                                Synthesize consensus responses from conversations into consolidated reports.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="pt-6 space-y-4">
                            <p className="text-sm text-muted-foreground">
                                The Synthesis section allows you to review, synthesize, and consolidate group responses into master reports.
                            </p>

                            {/* Synthesis Navigation Links */}
                            <div className="space-y-2">
                                <Link href="/synthesis" className="block">
                                    <div className="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <LayoutGrid className="size-4 text-muted-foreground" />
                                            <span className="text-sm font-medium">Synthesis Dashboard</span>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </div>
                                </Link>
                                <Link href="/synthesis/collaborate" className="block">
                                    <div className="flex items-center justify-between p-3 rounded-lg border hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center gap-3">
                                            <Users className="size-4 text-muted-foreground" />
                                            <span className="text-sm font-medium">Collaborative Review</span>
                                        </div>
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </div>
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-indigo-50 dark:bg-indigo-950/20 rounded-lg">
                                <BookOpen className="h-5 w-5 text-indigo-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Pastoral Tools</p>
                                <p className="text-xs text-muted-foreground">3 sections available</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-emerald-50 dark:bg-emerald-950/20 rounded-lg">
                                <ClipboardList className="h-5 w-5 text-emerald-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Synthesis Tools</p>
                                <p className="text-xs text-muted-foreground">2 sections available</p>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardContent className="pt-6 flex items-center gap-3">
                            <div className="p-2 bg-violet-50 dark:bg-violet-950/20 rounded-lg">
                                <Users className="h-5 w-5 text-violet-600" />
                            </div>
                            <div>
                                <p className="text-sm font-medium">Discernment Role</p>
                                <p className="text-xs text-muted-foreground">Full access to both modes</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout breadcrumbs={[{ title: 'Discernment Dashboard', href: '/discernment' }]}>{page}</AppLayout>;
