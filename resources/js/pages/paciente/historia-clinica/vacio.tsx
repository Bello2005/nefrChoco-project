import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { FileHeart } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mi historia clínica', href: '/paciente/mi-historia-clinica' }];

export default function HistoriaClinicaVacio() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi historia clínica" />

            <div className="space-y-6">
                <PageHeader title="Mi historia clínica" icon={FileHeart} />

                <EmptyState
                    icon={FileHeart}
                    title="Todavía no tienes historia clínica"
                    description="Tu equipo médico la creará durante tu primera consulta. Cuando esté lista la verás aquí."
                    action={
                        <Button variant="outline" asChild>
                            <Link href={route('paciente.mis-citas.index')}>Ver mis citas</Link>
                        </Button>
                    }
                />
            </div>
        </AppLayout>
    );
}
