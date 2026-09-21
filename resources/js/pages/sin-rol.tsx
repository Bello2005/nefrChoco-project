import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { LayoutDashboard, ShieldQuestion } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/dashboard' }];

export default function SinRol() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inicio" />

            <div className="space-y-6">
                <PageHeader title="Inicio" icon={LayoutDashboard} />

                <EmptyState
                    icon={ShieldQuestion}
                    title="Tu cuenta aún no tiene un rol asignado"
                    description="Contacta al administrador de la IPS para que te asigne uno. Mientras tanto no hay información que mostrarte aquí."
                />
            </div>
        </AppLayout>
    );
}
