import { type NavSection } from '@/types';
import {
    Activity,
    BookOpen,
    Building2,
    CalendarDays,
    ClipboardCheck,
    ClipboardList,
    FileHeart,
    HeartPulse,
    LayoutDashboard,
    Library,
    Network,
    ScrollText,
    ShieldCheck,
    UserRound,
    UserRoundSearch,
    Users,
} from 'lucide-react';

// El cuestionario de usabilidad (/usabilidad) va en el menú de médicos y pacientes
// porque son quienes lo responden: sin enlace visible solo llegaba quien conociera
// la dirección. Se llama «Tu opinión» porque «usabilidad» no le dice nada a un paciente.
const opinionSection: NavSection = {
    label: 'Plataforma',
    items: [{ title: 'Tu opinión', url: '/usabilidad', icon: ClipboardCheck }],
};

export const navByRole: Record<string, NavSection[]> = {
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
        opinionSection,
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
        opinionSection,
    ],
};

export const fallbackNav: NavSection[] = [{ label: 'General', items: [{ title: 'Inicio', url: '/dashboard', icon: LayoutDashboard }] }];
