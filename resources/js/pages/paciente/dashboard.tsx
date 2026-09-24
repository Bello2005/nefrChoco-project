import { LineChart } from '@/components/charts';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { AppointmentTypeBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { type VitalSeries } from '@/components/vital-sign-series';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { BookOpen, CalendarDays, FileHeart, HeartPulse, UserRoundX, Video } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Inicio', href: '/paciente/dashboard' }];

interface Appointment {
    id: number;
    scheduled_at: string;
    status: string;
    type: string;
    doctor: { id: number; name: string } | null;
}

interface Content {
    id: number;
    title: string;
    description: string | null;
    type: string;
    url_or_path: string | null;
    hasOwnBody: boolean;
    ecnt_category: string;
}

interface Props {
    patientName: string;
    hasProfile: boolean;
    activeTeleconsultation: Appointment | null;
    nextAppointment: Appointment | null;
    upcomingCount: number;
    series: VitalSeries[];
    clinicalHistoryId: number | null;
    suggestedContents: Content[];
    followUpDue: string[];
}

export default function PacienteDashboard({
    patientName,
    hasProfile,
    activeTeleconsultation,
    nextAppointment,
    upcomingCount,
    series,
    clinicalHistoryId,
    suggestedContents,
    followUpDue,
}: Props) {
    const firstName = patientName.split(' ')[0];
    const highlightedSeries = series.slice(0, 2);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mi salud" />

            <div className="space-y-6">
                <PageHeader title={`Hola, ${firstName}`} description="Este es el resumen de tu salud y tu próximo control." />

                {!hasProfile && (
                    <div className="border-warning/30 bg-warning-soft flex items-start gap-3 rounded-xl border p-4">
                        <UserRoundX className="text-warning mt-0.5 size-5 shrink-0" />
                        <p className="text-sm">
                            Tu cuenta todavía no está vinculada a una ficha de paciente. Comunícate con la IPS para activar tu seguimiento.
                        </p>
                    </div>
                )}

                {activeTeleconsultation && (
                    <div className="border-brand/30 bg-brand-soft flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5">
                        <div className="min-w-0">
                            <p className="font-display text-base font-bold">Tu teleconsulta está lista</p>
                            <p className="text-muted-foreground mt-1 text-sm">
                                {activeTeleconsultation.doctor?.name ?? 'Equipo médico'} · {formatDateTime(activeTeleconsultation.scheduled_at)}
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={route('paciente.mis-citas.teleconsulta', activeTeleconsultation.id)}>
                                <Video />
                                Unirse a la teleconsulta
                            </Link>
                        </Button>
                    </div>
                )}

                {followUpDue.length > 0 && (
                    <div className="border-warning/30 bg-warning-soft flex flex-wrap items-center justify-between gap-4 rounded-xl border p-5">
                        <div className="min-w-0">
                            <p className="font-display text-base font-bold">Te toca medirte</p>
                            <p className="text-muted-foreground mt-1 text-sm">Registra tu {followUpDue.join(', ').toLowerCase()} cuando puedas.</p>
                        </div>
                        <Button asChild>
                            <Link href={route('paciente.signos-vitales.index')}>
                                <HeartPulse />
                                Registrar medición
                            </Link>
                        </Button>
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-3">
                    <Card className="border-primary/25 bg-primary-soft/40 lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Tu próxima cita</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {nextAppointment ? (
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p className="font-display text-xl font-extrabold">{formatDateTime(nextAppointment.scheduled_at)}</p>
                                        <p className="text-muted-foreground mt-1 text-sm">
                                            {nextAppointment.doctor?.name ?? 'Equipo médico'} · {formatRelative(nextAppointment.scheduled_at)}
                                        </p>
                                        <div className="mt-3">
                                            <AppointmentTypeBadge type={nextAppointment.type} />
                                        </div>
                                    </div>
                                    <Button asChild>
                                        <Link href={route('paciente.mis-citas.index')}>
                                            <CalendarDays />
                                            Ver mis citas
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <p className="text-muted-foreground text-sm">No tienes citas programadas por ahora.</p>
                                    <Button variant="outline" asChild>
                                        <Link href={route('paciente.mis-citas.index')}>Ver historial</Link>
                                    </Button>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Accesos rápidos</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            <Button variant="outline" className="w-full justify-start" asChild>
                                <Link href={route('paciente.signos-vitales.index')}>
                                    <HeartPulse />
                                    Registrar medición
                                </Link>
                            </Button>
                            {clinicalHistoryId && (
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href={route('paciente.mi-historia-clinica.show')}>
                                        <FileHeart />
                                        Mi historia clínica
                                    </Link>
                                </Button>
                            )}
                            <Button variant="outline" className="w-full justify-start" asChild>
                                <Link href={route('paciente.educativo.index')}>
                                    <BookOpen />
                                    Material educativo
                                </Link>
                            </Button>
                            <p className="text-muted-foreground pt-1 text-center text-xs">
                                {upcomingCount} {upcomingCount === 1 ? 'cita programada' : 'citas programadas'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {highlightedSeries.length > 0 && (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {highlightedSeries.map((item, index) => (
                            <Card key={item.type}>
                                <CardHeader className="flex-row items-start justify-between space-y-0">
                                    <div>
                                        <CardTitle className="text-sm">{item.label}</CardTitle>
                                        <p className="tabular mt-1.5 text-2xl font-extrabold">
                                            {item.latest ?? '—'}
                                            <span className="text-muted-foreground ml-1 text-sm font-semibold">{item.unit}</span>
                                        </p>
                                    </div>
                                    <Badge variant={item.status === 'normal' ? 'success' : item.status === 'alto' ? 'destructive' : 'warning'}>
                                        {item.status === 'normal' ? 'En rango' : item.status === 'alto' ? 'Alto' : 'Bajo'}
                                    </Badge>
                                </CardHeader>
                                <CardContent>
                                    <LineChart
                                        data={item.points}
                                        unit={item.unit}
                                        color={index === 0 ? 'var(--chart-1)' : 'var(--chart-2)'}
                                        referenceBand={item.range ?? undefined}
                                        height={170}
                                    />
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <CardTitle>Material recomendado</CardTitle>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route('paciente.educativo.index')}>Ver todo</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {suggestedContents.length === 0 ? (
                            <EmptyState icon={BookOpen} title="Sin material disponible" />
                        ) : (
                            <div className="grid gap-3 sm:grid-cols-3">
                                {suggestedContents.map((content) => {
                                    // El material propio se lee dentro de la app; el enlazado
                                    // se abre fuera y solo funciona con señal.
                                    const cardClass =
                                        'border-border/70 hover:border-ring/40 hover:bg-muted/40 rounded-lg border p-4 transition-colors';
                                    const inner = (
                                        <>
                                            <span className="bg-brand-soft text-brand-strong flex size-9 items-center justify-center rounded-lg">
                                                <BookOpen className="size-4" />
                                            </span>
                                            <p className="mt-3 text-sm font-semibold">{content.title}</p>
                                            <p className="text-muted-foreground mt-1 line-clamp-2 text-xs">{content.description}</p>
                                        </>
                                    );

                                    return content.hasOwnBody ? (
                                        <Link key={content.id} href={route('paciente.educativo.show', content.id)} className={cardClass}>
                                            {inner}
                                        </Link>
                                    ) : (
                                        <a
                                            key={content.id}
                                            href={content.url_or_path ?? '#'}
                                            target="_blank"
                                            rel="noreferrer noopener"
                                            className={cardClass}
                                        >
                                            {inner}
                                        </a>
                                    );
                                })}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
