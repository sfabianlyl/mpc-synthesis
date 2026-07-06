import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ClipboardList,
    FileText,
    LayoutGrid,
    Send,
    Settings,
    Shield,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

function useNavItems(): NavItem[] {
    const { auth } = usePage().props;
    const role = auth?.user?.role;

    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
    ];

    // Facilitators (and everyone above) can submit responses
    if (role === 'facilitator' || role === 'synthesis' || role === 'drafter' || role === 'admin') {
        items.push({
            title: 'Submit Response',
            href: '/respond',
            icon: Send,
        });
    }

    // Synthesis-specific navigation
    if (role === 'synthesis' || role === 'admin') {
        items.push({
            title: 'Synthesis Dashboard',
            href: '/synthesis',
            icon: ClipboardList,
        });
    }

    // Drafter-specific navigation
    if (role === 'drafter' || role === 'admin') {
        items.push({
            title: 'Drafter Dashboard',
            href: '/drafter',
            icon: FileText,
        });
    }

    // Admin navigation
    if (role === 'admin') {
        items.push({
            title: 'Admin Panel',
            href: '/admin',
            icon: Shield,
        });
    }

    return items;
}

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const mainNavItems = useNavItems();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
