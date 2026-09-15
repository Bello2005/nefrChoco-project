import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { AppointmentStatusBadge, AppointmentTypeBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatRelative } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, Video } from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Mis citas', href: '/paciente/mis-citas' }];

interface AppointmentRow {
    id: number;
    scheduled_at: string;
    status: string;
    type: string;
    doctor: { id: number; name: string } | null;
    can_join: boolean;
}

export default function MisCitasIndex({ appointments }: { appointments: AppointmentRow[] }) {
    // Una teleconsulta que empezó hace un rato sigue estando "en curso": no
    // puede caer en el historial mientras el paciente todavía pueda entrar.
    const upcoming = appointments.filter((appointment) => appointment.can_join || new Date(appointment.scheduled_at) >= new Date());
    const past = appointments.filter((appointment) => !appointment.can_join && new Date(appointment.scheduled_at) < new Date());

    const renderCard = (appointment: AppointmentRow, isPast: boolean) => (
        <li
            key={appointment.id}
            className={`bg-card border-border/70 flex flex-wrap items-center gap-4 rounded-xl border p-4 shadow-sm ${isPast ? 'opacity-75' : ''}`}
        >
            <div className="bg-primary-soft text-accent-foreground flex size-12 shrink-0 flex-col items-center justify-center rounded-xl">
                <span className="tabular text-base leading-none font-extrabold">{new Date(appointment.scheduled_at).getDate()}</span>
                <span className="text-[10px] font-semibold uppercase">
                    {new Date(appointment.scheduled_at).toLocaleDateString('es-CO', { month: 'short' }).replace('.', '')}
                </span>
            </div>

            <div className="min-w-0 flex-1">
                <p className="text-sm font-semibold">{formatDateTime(appointment.scheduled_at)}</p>
                <p className="text-muted-foreground text-xs">
                    {appointment.doctor?.name ?? 'Equipo médico'} · {formatRelative(appointment.scheduled_at)}
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                <AppointmentTypeBadge type={appointment.type} />
                <AppointmentStatusBadge status={appointment.status} />
                {appointment.can_join && (
                    <Button size="sm" asChild>
                        <Link href={route('paciente.mis-citas.teleconsulta', appointment.id)}>
                            <Video />
                            Unirse a la teleconsulta
                        </Link>
                    </Button>
                )}
            </div>
        </li>
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Mis citas" />

            <div className="space-y-6">
                <PageHeader title="Mis citas" description="Consulta tus próximas atenciones y el historial de tus controles." icon={CalendarDays} />

                {appointments.length === 0 ? (
                    <EmptyState
                        icon={CalendarDays}
                        title="No tienes citas registradas"
                        description="Cuando tu IPS agende un control aparecerá aquí con la fecha y la modalidad."
                    />
                ) : (
                    <div className="space-y-8">
                        <section className="space-y-3">
                            <h2 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Próximas</h2>
                            {upcoming.length === 0 ? (
                                <p className="text-muted-foreground text-sm">No tienes citas programadas.</p>
                            ) : (
                                <ul className="space-y-2.5">{upcoming.map((appointment) => renderCard(appointment, false))}</ul>
                            )}
                        </section>

                        {past.length > 0 && (
                            <section className="space-y-3">
                                <h2 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Historial</h2>
                                <ul className="space-y-2.5">{past.map((appointment) => renderCard(appointment, true))}</ul>
                            </section>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
