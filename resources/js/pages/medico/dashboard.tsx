import { BarChart, DonutChart } from '@/components/charts';
import { DecisionSupportNotice, RecommendationList } from '@/components/clinical-recommendations';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { StatCard } from '@/components/stat-card';
import { AppointmentStatusBadge, AppointmentTypeBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative, formatTime, initialsFrom } from '@/lib/format';
import { type BreadcrumbItem, type ClinicalRecommendation } from '@/types';
import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    ListChecks,
    MonitorSmartphone,
    TriangleAlert,
    UserPlus,
    Users,
    Video,
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/medico/dashboard' }];

const chartPalette = ['var(--chart-1)', 'var(--chart-2)', 'var(--chart-3)', 'var(--chart-4)', 'var(--chart-5)'];

interface UpcomingAppointment {
    id: number;
    scheduled_at: string;
    status: string;
    type: string;
    patient: { id: number; full_name: string; municipality: string } | null;
}

interface TodayTeleconsultation {
    id: number;
    scheduledAt: string;
    status: string;
    patientName: string | null;
    municipality: string | null;
    /** Null cuando la sala está abierta; si no, por qué no se puede entrar todavía. */
    blockedReason: string | null;
}

interface Alert {
    id: number;
    patient: string | null;
    patientId: number;
    label: string;
    value: number;
    unit: string;
    status: string;
    recordedAt: string;
}

interface PriorityPatient {
    patientId: number;
    patientName: string;
    municipality: string;
    recommendations: ClinicalRecommendation[];
}

interface Props {
    doctorName: string;
    stats: { patients: number; appointmentsToday: number; appointmentsWeek: number; pendingTeleconsultations: number };
    priorityPatients: PriorityPatient[];
    upcomingAppointments: UpcomingAppointment[];
    todayTeleconsultations: TodayTeleconsultation[];
    ecntDistribution: { label: string; value: number }[];
    appointmentsTrend: { label: string; value: number }[];
    alerts: Alert[];
    followUp: { isActive: boolean; overduePatients: { id: number; name: string; overdue: string[] }[] };
}

export default function MedicoDashboard({
    doctorName,
    stats,
    priorityPatients,
    upcomingAppointments,
    todayTeleconsultations,
    ecntDistribution,
    appointmentsTrend,
    alerts,
    followUp,
}: Props) {
    const firstName = doctorName.split(' ').slice(0, 2).join(' ');
    const totalDiagnoses = ecntDistribution.reduce((sum, item) => sum + item.value, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard médico" />

            <div className="space-y-6">
                <PageHeader
                    title={`Hola, ${firstName}`}
                    description="Resumen de tu actividad clínica y alertas de telemonitoreo del programa de ECNT."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={route('medico.pacientes.create')}>
                                    <UserPlus />
                                    Registrar paciente
                                </Link>
                            </Button>
                            <Button asChild>
                                <Link href={route('medico.citas.create')}>
                                    <CalendarDays />
                                    Agendar cita
                                </Link>
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard label="Pacientes activos" value={stats.patients} icon={Users} tone="primary" />
                    <StatCard label="Citas hoy" value={stats.appointmentsToday} icon={CalendarCheck} tone="info" />
                    <StatCard label="Citas esta semana" value={stats.appointmentsWeek} icon={CalendarDays} tone="success" />
                    <StatCard label="Teleconsultas pendientes" value={stats.pendingTeleconsultations} icon={MonitorSmartphone} tone="brand" />
                </div>

                {/* Res. 1644 de 2026, art. 19 par. 1: frecuencia de seguimiento por riesgo. */}
                <Card>
                    <CardHeader className="flex-row items-center gap-2.5 space-y-0">
                        <span className="bg-warning-soft text-warning flex size-9 items-center justify-center rounded-lg">
                            <CalendarClock className="size-4.5" aria-hidden="true" />
                        </span>
                        <div>
                            <CardTitle>Pacientes con control vencido</CardTitle>
                            <p className="text-muted-foreground mt-1 text-sm">Sin medición en el plazo que corresponde a su nivel de riesgo</p>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {!followUp.isActive ? (
                            <p className="text-muted-foreground text-sm">
                                Esta función está apagada: la frecuencia de seguimiento por nivel de riesgo todavía no está definida. La define la
                                médica de la IPS en la configuración.
                            </p>
                        ) : followUp.overduePatients.length === 0 ? (
                            <p className="text-muted-foreground text-sm">Ningún paciente tiene el control vencido.</p>
                        ) : (
                            <ul className="divide-border/70 divide-y">
                                {followUp.overduePatients.map((row) => (
                                    <li key={row.id} className="flex flex-wrap items-center justify-between gap-2 py-2.5 first:pt-0 last:pb-0">
                                        <Link href={route('medico.pacientes.show', row.id)} className="text-sm font-semibold hover:underline">
                                            {row.name}
                                        </Link>
                                        <span className="text-muted-foreground text-xs">Falta: {row.overdue.join(', ')}</span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader className="flex-row items-center justify-between space-y-0">
                        <div className="flex items-center gap-2.5">
                            <span className="bg-primary-soft text-accent-foreground flex size-9 items-center justify-center rounded-lg">
                                <Video className="size-4.5" aria-hidden="true" />
                            </span>
                            <div>
                                <CardTitle>Teleconsultas de hoy</CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">La sala se abre en la ventana previa a cada cita</p>
                            </div>
                        </div>
                        <Button variant="ghost" size="sm" asChild>
                            <Link href={route('medico.citas.index')}>Ver agenda</Link>
                        </Button>
                    </CardHeader>
                    <CardContent>
                        {todayTeleconsultations.length === 0 ? (
                            <EmptyState
                                icon={Video}
                                title="Sin teleconsultas hoy"
                                description="Las citas de teleconsulta del día aparecerán aquí con el acceso a su sala."
                            />
                        ) : (
                            <ul className="divide-border/70 divide-y">
                                {todayTeleconsultations.map((teleconsultation) => (
                                    <li key={teleconsultation.id} className="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                                        <span className="tabular w-24 shrink-0 text-sm font-bold whitespace-nowrap">
                                            {formatTime(teleconsultation.scheduledAt)}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-semibold">{teleconsultation.patientName}</p>
                                            <p className="text-muted-foreground text-xs">{teleconsultation.municipality}</p>
                                        </div>
                                        <AppointmentStatusBadge status={teleconsultation.status} />
                                        {teleconsultation.blockedReason === null ? (
                                            <Button size="sm" asChild>
                                                <Link href={route('medico.citas.teleconsulta', teleconsultation.id)}>
                                                    <Video />
                                                    Entrar
                                                </Link>
                                            </Button>
                                        ) : (
                                            <span className="text-muted-foreground text-xs">{teleconsultation.blockedReason}</span>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {priorityPatients.length > 0 && (
                    <Card className="border-warning/30">
                        <CardHeader>
                            <div className="flex items-center gap-2.5">
                                <span className="bg-warning-soft text-warning flex size-9 items-center justify-center rounded-lg">
                                    <ListChecks className="size-4.5" aria-hidden="true" />
                                </span>
                                <CardTitle>Pacientes para revisar primero</CardTitle>
                            </div>
                            <DecisionSupportNotice className="mt-1.5" />
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {priorityPatients.map((entry) => (
                                <div key={entry.patientId} className="space-y-2.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link
                                            href={route('medico.pacientes.show', entry.patientId)}
                                            className="font-display text-base font-bold hover:underline"
                                        >
                                            {entry.patientName}
                                        </Link>
                                        <span className="text-muted-foreground text-xs">{entry.municipality}</span>
                                    </div>
                                    <RecommendationList recommendations={entry.recommendations} />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}

                <div className="grid gap-4 lg:grid-cols-5">
                    <Card className="lg:col-span-3">
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Próximas citas</CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">Tus consultas programadas más cercanas</p>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('medico.citas.index')}>Ver todas</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {upcomingAppointments.length === 0 ? (
                                <EmptyState
                                    icon={CalendarDays}
                                    title="Sin citas próximas"
                                    description="Cuando agendes una cita aparecerá aquí con su hora y modalidad."
                                    action={
                                        <Button asChild size="sm">
                                            <Link href={route('medico.citas.create')}>Agendar la primera</Link>
                                        </Button>
                                    }
                                />
                            ) : (
                                <ul className="divide-border/70 divide-y">
                                    {upcomingAppointments.map((appointment) => (
                                        <li key={appointment.id} className="flex flex-wrap items-center gap-3 py-3 first:pt-0 last:pb-0">
                                            <span className="bg-primary-soft text-accent-foreground flex size-10 shrink-0 items-center justify-center rounded-full text-xs font-bold">
                                                {initialsFrom(appointment.patient?.full_name ?? '?')}
                                            </span>
                                            <div className="min-w-0 flex-1">
                                                <p className="truncate text-sm font-semibold">{appointment.patient?.full_name}</p>
                                                <p className="text-muted-foreground text-xs">
                                                    {formatDateTime(appointment.scheduled_at)} · {appointment.patient?.municipality}
                                                </p>
                                            </div>
                                            <AppointmentTypeBadge type={appointment.type} />
                                            {appointment.type === 'teleconsulta' && (
                                                <Button size="sm" variant="outline" asChild>
                                                    <Link href={route('medico.citas.teleconsulta', appointment.id)}>
                                                        <Video />
                                                        Entrar
                                                    </Link>
                                                </Button>
                                            )}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Diagnósticos ECNT</CardTitle>
                            <p className="text-muted-foreground text-sm">Distribución en la población atendida</p>
                        </CardHeader>
                        <CardContent>
                            {totalDiagnoses === 0 ? (
                                <EmptyState icon={Activity} title="Sin diagnósticos registrados" />
                            ) : (
                                <DonutChart
                                    total={totalDiagnoses}
                                    caption="pacientes"
                                    segments={ecntDistribution.map((item, index) => ({
                                        label: item.label,
                                        value: item.value,
                                        color: chartPalette[index % chartPalette.length],
                                    }))}
                                />
                            )}
                        </CardContent>
                    </Card>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Citas de los últimos 7 días</CardTitle>
                            <p className="text-muted-foreground text-sm">Volumen diario de atención</p>
                        </CardHeader>
                        <CardContent>
                            <BarChart data={appointmentsTrend} color="var(--chart-1)" />
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <div>
                                <CardTitle>Alertas de telemonitoreo</CardTitle>
                                <p className="text-muted-foreground mt-1 text-sm">Mediciones fuera del rango de referencia</p>
                            </div>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('medico.telemonitoreo.index')}>Ver módulo</Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            {alerts.length === 0 ? (
                                <EmptyState
                                    icon={Activity}
                                    title="Todo dentro de rango"
                                    description="No hay mediciones que requieran atención inmediata."
                                />
                            ) : (
                                <ul className="space-y-2.5">
                                    {alerts.map((alert) => (
                                        <li
                                            key={alert.id}
                                            className={`flex items-center gap-3 rounded-lg border p-3 ${
                                                alert.status === 'alto'
                                                    ? 'border-destructive/25 bg-destructive-soft/60'
                                                    : 'border-warning/25 bg-warning-soft/60'
                                            }`}
                                        >
                                            <TriangleAlert
                                                className={`size-4 shrink-0 ${alert.status === 'alto' ? 'text-destructive' : 'text-warning'}`}
                                            />
                                            <div className="min-w-0 flex-1">
                                                <Link
                                                    href={route('medico.telemonitoreo.show', alert.patientId)}
                                                    className="truncate text-sm font-semibold hover:underline"
                                                >
                                                    {alert.patient}
                                                </Link>
                                                <p className="text-muted-foreground text-xs">
                                                    {alert.label} · {formatRelative(alert.recordedAt)}
                                                </p>
                                            </div>
                                            <span className="tabular shrink-0 text-sm font-bold">
                                                {alert.value}
                                                <span className="text-muted-foreground ml-0.5 text-xs font-medium">{alert.unit}</span>
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
