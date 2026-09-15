import { EmptyState } from '@/components/empty-state';
import { VitalSignForm, type VitalSignTypeOption } from '@/components/forms/vital-sign-form';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { VitalSignSeries, type VitalSeries } from '@/components/vital-sign-series';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Activity, HeartPulse, UserRound } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Telemonitoreo', href: '/medico/telemonitoreo' },
    { title: 'Evolución', href: '#' },
];

interface Props {
    patient: { id: number; full_name: string; municipality: string; document_number: string };
    series: VitalSeries[];
    types: VitalSignTypeOption[];
}

export default function TelemonitoreoShow({ patient, series, types }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Telemonitoreo · ${patient.full_name}`} />

            <div className="space-y-6">
                <PageHeader
                    title={patient.full_name}
                    description={`${patient.municipality} · CC ${patient.document_number}`}
                    icon={Activity}
                    actions={
                        <Button variant="outline" asChild>
                            <Link href={route('medico.pacientes.show', patient.id)}>
                                <UserRound />
                                Ficha del paciente
                            </Link>
                        </Button>
                    }
                />

                <div className="grid gap-5 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        {series.length === 0 ? (
                            <EmptyState
                                icon={HeartPulse}
                                title="Sin mediciones registradas"
                                description="Registra la primera medición con el formulario de la derecha para comenzar el seguimiento."
                            />
                        ) : (
                            <div className="space-y-4">
                                <VitalSignSeries series={series} />
                            </div>
                        )}
                    </div>

                    <Card className="lg:sticky lg:top-24 lg:self-start">
                        <CardHeader>
                            <CardTitle>Registrar medición</CardTitle>
                            <p className="text-muted-foreground text-sm">Queda asociada a tu usuario en la auditoría.</p>
                        </CardHeader>
                        <CardContent>
                            <VitalSignForm action={route('medico.telemonitoreo.store', patient.id)} types={types} />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
