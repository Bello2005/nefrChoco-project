import { AppointmentCalendar } from '@/components/appointment-calendar';
import { type ConnectionCheck, ConnectionCheckBadge } from '@/components/connection-check-badge';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { AppointmentStatusBadge, AppointmentTypeBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { CalendarDays, CalendarPlus, Video, X } from 'lucide-react';
import { useMemo, useState } from 'react';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/medico/dashboard' },
    { title: 'Citas', href: '/medico/citas' },
];

interface AppointmentRow {
    id: number;
    scheduled_at: string;
    status: string;
    type: string;
    patient: { id: number; full_name: string } | null;
    // Lo decide el servidor con la misma política que protege la ruta: una
    // cita atendida ya es parte del registro y no se edita.
    // TODO doc: guía de usuario — las citas ya no se eliminan (se cancelan) y las atendidas no se editan.
    can_edit: boolean;
    connection_check: ConnectionCheck | null;
}

export default function CitasIndex({ appointments }: { appointments: AppointmentRow[] }) {
    const [selectedDate, setSelectedDate] = useState<string | null>(null);

    const visible = useMemo(() => {
        if (!selectedDate) return appointments;

        return appointments.filter((appointment) => {
            const date = new Date(appointment.scheduled_at);
            const iso = new Date(date.getFullYear(), date.getMonth(), date.getDate()).toISOString().slice(0, 10);
            return iso === selectedDate;
        });
    }, [appointments, selectedDate]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Citas" />

            <div className="space-y-6">
                <PageHeader
                    title="Agenda de citas"
                    description="Consulta y organiza tus atenciones presenciales y teleconsultas."
                    icon={CalendarDays}
                    actions={
                        <Button asChild>
                            <Link href={route('medico.citas.create')}>
                                <CalendarPlus />
                                Agendar cita
                            </Link>
                        </Button>
                    }
                />

                {/* Lado a lado solo en pantallas anchas: por debajo, la lista necesita
                    todo el ancho para que no se aplasten nombre, estado y acciones. */}
                <div className="grid gap-5 xl:grid-cols-5">
                    <div className="xl:col-span-2">
                        <AppointmentCalendar appointments={appointments} selectedDate={selectedDate} onSelectDate={setSelectedDate} />
                    </div>

                    <div className="space-y-3 xl:col-span-3">
                        <div className="flex items-center justify-between">
                            <h2 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">
                                {selectedDate ? formatDate(selectedDate) : 'Todas las citas'}
                            </h2>
                            {selectedDate && (
                                <Button size="sm" variant="ghost" onClick={() => setSelectedDate(null)}>
                                    <X />
                                    Quitar filtro
                                </Button>
                            )}
                        </div>

                        {visible.length === 0 ? (
                            <EmptyState
                                icon={CalendarDays}
                                title={selectedDate ? 'Sin citas ese día' : 'Aún no hay citas agendadas'}
                                description={
                                    selectedDate
                                        ? 'Selecciona otro día en el calendario o agenda una nueva cita.'
                                        : 'Agenda la primera cita para comenzar a organizar tu agenda clínica.'
                                }
                                action={
                                    !selectedDate ? (
                                        <Button asChild>
                                            <Link href={route('medico.citas.create')}>Agendar cita</Link>
                                        </Button>
                                    ) : undefined
                                }
                            />
                        ) : (
                            <ul className="space-y-2.5">
                                {visible.map((appointment) => (
                                    <li
                                        key={appointment.id}
                                        className="bg-card border-border/70 flex flex-col gap-3 rounded-xl border p-4 shadow-sm transition-shadow duration-200 hover:shadow-md sm:flex-row sm:items-center"
                                    >
                                        <div className="min-w-0 sm:flex-1">
                                            <p className="truncate text-sm font-semibold">{appointment.patient?.full_name ?? '—'}</p>
                                            <p className="text-muted-foreground text-xs">{formatDateTime(appointment.scheduled_at)}</p>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            <AppointmentTypeBadge type={appointment.type} />
                                            <AppointmentStatusBadge status={appointment.status} />
                                            {appointment.status === 'programada' && <ConnectionCheckBadge check={appointment.connection_check} />}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-2">
                                            {appointment.type === 'teleconsulta' && appointment.status === 'programada' && (
                                                <Button size="sm" asChild>
                                                    <Link href={route('medico.citas.teleconsulta', appointment.id)}>
                                                        <Video />
                                                        Entrar
                                                    </Link>
                                                </Button>
                                            )}
                                            {appointment.can_edit && (
                                                <Button size="sm" variant="outline" asChild>
                                                    <Link href={route('medico.citas.edit', appointment.id)}>Editar</Link>
                                                </Button>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
