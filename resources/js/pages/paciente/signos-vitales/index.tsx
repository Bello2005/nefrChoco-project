import { EmptyState } from '@/components/empty-state';
import { VitalSignForm, type VitalSignTypeOption } from '@/components/forms/vital-sign-form';
import { PageHeader } from '@/components/page-header';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { VitalSignSeries, type VitalSeries } from '@/components/vital-sign-series';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import { HeartPulse, UserRoundX } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Signos vitales', href: '/paciente/signos-vitales' }];

interface Props {
    series: VitalSeries[];
    types: VitalSignTypeOption[];
    hasProfile: boolean;
}

export default function SignosVitalesIndex({ series, types, hasProfile }: Props) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis signos vitales" />

            <div className="space-y-6">
                <PageHeader
                    title="Mis signos vitales"
                    description="Registra tus mediciones en casa. Tu equipo médico las revisa para hacerte seguimiento a distancia."
                    icon={HeartPulse}
                />

                {!hasProfile ? (
                    <EmptyState
                        icon={UserRoundX}
                        title="Tu cuenta aún no está vinculada"
                        description="Pídele a tu IPS que vincule tu usuario con tu ficha de paciente para comenzar a registrar mediciones."
                    />
                ) : (
                    <div className="grid gap-5 lg:grid-cols-3">
                        <div className="lg:col-span-2">
                            {series.length === 0 ? (
                                <EmptyState
                                    icon={HeartPulse}
                                    title="Todavía no tienes mediciones"
                                    description="Registra tu primera medición con el formulario de al lado y verás aquí tu evolución."
                                />
                            ) : (
                                <VitalSignSeries series={series} />
                            )}
                        </div>

                        <Card className="lg:sticky lg:top-24 lg:self-start">
                            <CardHeader>
                                <CardTitle>Nueva medición</CardTitle>
                                <p className="text-muted-foreground text-sm">Toma la medición con calma y en reposo.</p>
                            </CardHeader>
                            <CardContent>
                                <VitalSignForm action={route('paciente.signos-vitales.store')} types={types} />
                            </CardContent>
                        </Card>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
