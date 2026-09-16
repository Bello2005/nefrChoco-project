import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    roles: string[];
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Coincidencia por prefijo de URL para marcar activas las rutas anidadas. */
    match?: string;
}

export interface NavSection {
    label: string;
    items: NavItem[];
}

/** Recomendación del apoyo a decisiones clínicas por reglas. */
export interface ClinicalRecommendation {
    rule: string;
    priority: string;
    priorityLabel: string;
    title: string;
    /** Qué dato concreto disparó la regla. Nunca viene vacío. */
    reason: string;
    action: string;
}

export interface AppNotification {
    id: string;
    title: string;
    message: string;
    url: string | null;
    type: string | null;
    createdAt: string;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    flash: { success?: string | null; error?: string | null };
    notifications: AppNotification[];
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
