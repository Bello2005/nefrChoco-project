import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { fallbackNav, navByRole } from '@/lib/navigation';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import AppLogo from './app-logo';

export function AppSidebar() {
    const { auth } = usePage<SharedData>().props;
    const role = auth.roles?.[0];
    const sections = (role && navByRole[role]) || fallbackNav;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild className="hover:bg-sidebar-accent/60">
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-4">
                <NavMain sections={sections} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
