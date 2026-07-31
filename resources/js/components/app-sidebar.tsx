import { Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    Cloud,
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
import { RoleSwitcher, getModeNavigation, DiscernmentMode } from '@/components/role-switcher';

function useNavItems(): { items: NavItem[]; isDiscernment: boolean; mode: DiscernmentMode } {
    const { auth } = usePage().props as any;
    const role = auth?.user?.role;
    const isDiscernment = role === 'discernment';

    // Get the current discernment mode
    const getMode = (): DiscernmentMode => {
        if (typeof window !== 'undefined') {
            const stored = localStorage.getItem('discernment-mode');
            if (stored === 'pastoral' || stored === 'synthesis') {
                return stored;
            }
        }
        return 'pastoral';
    };

    const mode = getMode();

    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
    ];

    // For discernment users, add RoleSwitcher and mode-based navigation
    if (isDiscernment) {
        const baseItems = [items[0]];
        const modeItems = getModeNavigation(mode, baseItems);
        return { items: modeItems, isDiscernment: true, mode };
    }

    // Authenticated users can submit responses
    if (role === 'synthesis' || role === 'drafter' || role === 'admin') {
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

    // Pastoral-specific navigation
    if (role === 'pastoral') {
        items.push({
            title: 'Pastoral Dashboard',
            href: '/pastoral/dashboard',
            icon: Users,
        });
        items.push({
            title: 'Pastoral Reports',
            href: '/pastoral/dashboard',
            icon: FileText,
        });
        items.push({
            title: 'Decisions',
            href: '/pastoral/dashboard',
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
        items.push({
            title: 'Media Upload',
            href: '/admin/media-upload',
            icon: Cloud,
        });
    }

    return { items, isDiscernment: false, mode };
}

const footerNavItems: NavItem[] = [
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { items, isDiscernment } = useNavItems();

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

            {/* RoleSwitcher for discernment users */}
            {isDiscernment && (
                <SidebarContent>
                    <RoleSwitcher />
                    <NavMain items={items} />
                </SidebarContent>
            )}

            {/* Regular sidebar content for non-discernment users */}
            {!isDiscernment && (
                <SidebarContent>
                    <NavMain items={items} />
                </SidebarContent>
            )}

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
