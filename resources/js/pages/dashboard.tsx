import { Head, usePage } from '@inertiajs/react';
import { BarChart3, ClipboardList, FileText, Shield } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';

export default function Dashboard() {
    const { auth } = usePage().props;
    const role = auth.user?.role;

    const roleCards: Record<string, { title: string; description: string; icon: React.ReactNode; href: string; gradient: string }[]> = {
        synthesis: [
            {
                title: 'Synthesis Dashboard',
                description: 'Review assigned groups, trigger AI analysis, and edit synthesis reports.',
                icon: <ClipboardList className="size-6" />,
                href: '/synthesis',
                gradient: 'from-blue-500/20 to-indigo-500/20',
            },
        ],
        drafter: [
            {
                title: 'Drafter Dashboard',
                description: 'View aggregated reports, generate global synthesis, and export documents.',
                icon: <FileText className="size-6" />,
                href: '/drafter',
                gradient: 'from-violet-500/20 to-purple-500/20',
            },
        ],
        pastoral: [
            {
                title: 'Pastoral Dashboard',
                description: 'View conversation syntheses, and begin decision drafts.',
                icon: <ClipboardList className="size-6" />,
                href: '/pastoral/dashboard',
                gradient: 'from-blue-500/20 to-indigo-500/20',
            }
        ],
        admin: [
            {
                title: 'Synthesis Dashboard',
                description: 'Review assigned groups, trigger AI analysis, and edit synthesis reports.',
                icon: <ClipboardList className="size-6" />,
                href: '/synthesis',
                gradient: 'from-blue-500/20 to-indigo-500/20',
            },
            {
                title: 'Drafter Dashboard',
                description: 'View aggregated reports, generate global synthesis, and export documents.',
                icon: <FileText className="size-6" />,
                href: '/drafter',
                gradient: 'from-violet-500/20 to-purple-500/20',
            },
            {
                title: 'Admin Panel',
                description: 'Manage users, groups, and synthesis assignments.',
                icon: <Shield className="size-6" />,
                href: '/admin',
                gradient: 'from-amber-500/20 to-orange-500/20',
            },
        ],
        discernment: [
            {
                title: 'Synthesis Dashboard',
                description: 'Review assigned groups, trigger AI analysis, and edit synthesis reports.',
                icon: <ClipboardList className="size-6" />,
                href: '/synthesis',
                gradient: 'from-blue-500/20 to-indigo-500/20',
            },
            {
                title: 'Pastoral Dashboard',
                description: 'View conversation syntheses, and begin decision drafts.',
                icon: <ClipboardList className="size-6" />,
                href: '/pastoral',
                gradient: 'from-blue-500/20 to-indigo-500/20',
            },
            {
                title: 'Admin Panel',
                description: 'Manage users, groups, and synthesis assignments.',
                icon: <Shield className="size-6" />,
                href: '/admin',
                gradient: 'from-amber-500/20 to-orange-500/20',
            },
        ],
    };

    const cards = roleCards[role || 'admin'] || roleCards.admin;

    const roleLabels: Record<string, { label: string; color: string }> = {
        synthesis: { label: 'Synthesis Team', color: 'text-blue-400' },
        drafter: { label: 'Drafting Team', color: 'text-violet-400' },
        pastoral: { label: 'PDSW', color: 'text-violet-400' },
        admin: { label: 'Administrator', color: 'text-amber-400' },
    };

    const roleInfo = roleLabels[role || 'admin'] || roleLabels.admin;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                {/* Welcome Header */}
                <div className="relative overflow-hidden rounded-2xl border border-sidebar-border/50 bg-gradient-to-br from-primary/5 via-transparent to-accent/5 p-8">
                    <div className="absolute -top-20 -right-20 size-64 rounded-full bg-primary/5 blur-3xl" />
                    <div className="absolute -bottom-20 -left-20 size-64 rounded-full bg-accent/5 blur-3xl" />
                    <div className="relative">
                        <h1 className="text-3xl font-bold tracking-tight">
                            Welcome back, {auth.user?.name}
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            You are signed in as{' '}
                            <span className={`font-semibold ${roleInfo.color}`}>
                                {roleInfo.label}
                            </span>
                        </p>
                    </div>
                </div>

                {/* Quick Actions */}
                <div>
                    <h2 className="mb-4 text-lg font-semibold text-foreground/80">Quick Actions</h2>
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {cards.map((card) => (
                            <a
                                key={card.title}
                                href={card.href}
                                className="group relative overflow-hidden rounded-xl border border-sidebar-border/50 p-6 transition-all duration-300 hover:border-primary/30 hover:shadow-lg hover:shadow-primary/5"
                            >
                                <div className={`absolute inset-0 bg-gradient-to-br ${card.gradient} opacity-0 transition-opacity duration-300 group-hover:opacity-100`} />
                                <div className="relative">
                                    <div className="mb-4 flex size-12 items-center justify-center rounded-lg bg-primary/10 text-primary transition-transform duration-300 group-hover:scale-110">
                                        {card.icon}
                                    </div>
                                    <h3 className="font-semibold">{card.title}</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {card.description}
                                    </p>
                                </div>
                            </a>
                        ))}
                    </div>
                </div>

                {/* Info Bar */}
                <div className="mt-auto rounded-xl border border-sidebar-border/30 bg-muted/30 p-4">
                    <div className="flex items-center gap-3 text-sm text-muted-foreground">
                        <BarChart3 className="size-4" />
                        <span>
                            The synthesis process flows through three phases: Response Collection → Synthesis Team Analysis → Drafter Review & Export.
                        </span>
                    </div>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = (page: React.ReactNode) => <AppLayout breadcrumb="Dashboard">{page}</AppLayout>;
