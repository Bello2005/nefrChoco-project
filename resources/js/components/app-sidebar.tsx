import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavSection, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    BookOpen,
    Building2,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    Network,
    FileHeart,
    HeartPulse,
    LayoutDashboard,
    Library,
    ScrollText,
    ShieldCheck,
    UserRound,
    UserRoundSearch,
    Users,
} from 'lucide-react';
import AppLogo from './app-logo';

const navByRole: Record<string, NavSection[]> = {
    admin: [
        {
            label: 'General',
            items: [{ title: 'Panel', url: '/admin', icon: LayoutDashboard }],
        },
        {
            label: 'Gestión',
            items: [
                { title: 'Usuarios', url: '/admin/usuarios', icon: Users },
                { title: 'Pacientes', url: '/admin/pacientes', icon: UserRound },
                { title: 'Fichas por revisar', url: '/fichas-por-revisar', icon: UserRoundSearch },
                { title: 'Contenido educativo', url: '/admin/educativo', icon: BookOpen },
            ],
        },
        {
            label: 'Cumplimiento',
            items: [
                { title: 'Auditoría', url: '/admin/auditoria', icon: ScrollText },
                { title: 'Catálogos', url: '/admin/catalogos', icon: Library },
                { title: 'Datos de la institución', url: '/admin/institucion', icon: Building2 },
                { title: 'Preparación para interoperar', url: '/admin/interoperabilidad', icon: Network },
                { title: 'Usabilidad', url: '/admin/usabilidad', icon: ClipboardCheck },
            ],
        },
    ],
    medico: [
        {
            label: 'Clínico',
            items: [
                { title: 'Dashboard', url: '/medico/dashboard', icon: LayoutDashboard },
                { title: 'Pacientes', url: '/medico/pacientes', icon: Users },
                { title: 'Fichas por revisar', url: '/fichas-por-revisar', icon: UserRoundSearch },
                { title: 'Citas', url: '/medico/citas', icon: CalendarDays },
            ],
        },
        {
            label: 'Seguimiento',
            items: [
                { title: 'Formularios', url: '/medico/formularios-clinicos', icon: ClipboardList },
                { title: 'Telemonitoreo', url: '/medico/telemonitoreo', icon: Activity },
            ],
        },
    ],
    paciente: [
        {
            label: 'Mi salud',
            items: [
                { title: 'Inicio', url: '/paciente/dashboard', icon: LayoutDashboard },
                { title: 'Mis citas', url: '/paciente/mis-citas', icon: CalendarDays },
                { title: 'Mi historia', url: '/paciente/mi-historia-clinica', icon: FileHeart },
                { title: 'Mis consentimientos', url: '/paciente/mis-consentimientos', icon: ShieldCheck },
            ],
        },
        {
            label: 'Seguimiento',
            items: [{ title: 'Signos vitales', url: '/paciente/signos-vitales', icon: HeartPulse }],
        },
        {
            label: 'Aprender',
            items: [{ title: 'Educación', url: '/paciente/educativo', icon: BookOpen }],
        },
    ],
};

const fallbackNav: NavSection[] = [{ label: 'General', items: [{ title: 'Inicio', url: '/dashboard', icon: LayoutDashboard }] }];

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
