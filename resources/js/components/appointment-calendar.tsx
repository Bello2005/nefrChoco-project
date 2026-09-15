import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useMemo, useState } from 'react';

export interface CalendarAppointment {
    id: number;
    scheduled_at: string;
    type: string;
    status: string;
}

const weekDays = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];

export function AppointmentCalendar({
    appointments,
    selectedDate,
    onSelectDate,
}: {
    appointments: CalendarAppointment[];
    selectedDate: string | null;
    onSelectDate: (date: string | null) => void;
}) {
    const today = new Date();
    const [viewMonth, setViewMonth] = useState(() => new Date(today.getFullYear(), today.getMonth(), 1));

    const byDate = useMemo(() => {
        const map = new Map<string, CalendarAppointment[]>();

        appointments.forEach((appointment) => {
            const date = new Date(appointment.scheduled_at);
            const key = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
            map.set(key, [...(map.get(key) ?? []), appointment]);
        });

        return map;
    }, [appointments]);

    const year = viewMonth.getFullYear();
    const month = viewMonth.getMonth();
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const cells = [...Array<null>(firstWeekday).fill(null), ...Array.from({ length: daysInMonth }, (_, index) => index + 1)];

    const shiftMonth = (delta: number) => setViewMonth(new Date(year, month + delta, 1));

    const isoFor = (day: number) => new Date(year, month, day).toISOString().slice(0, 10);

    return (
        <div className="bg-card border-border/70 rounded-xl border p-5 shadow-sm">
            <div className="mb-4 flex items-center justify-between">
                <p className="font-display text-sm font-bold capitalize">
                    {viewMonth.toLocaleDateString('es-CO', { month: 'long', year: 'numeric' })}
                </p>
                <div className="flex gap-1">
                    <Button variant="ghost" size="icon-sm" onClick={() => shiftMonth(-1)} aria-label="Mes anterior">
                        <ChevronLeft />
                    </Button>
                    <Button variant="ghost" size="icon-sm" onClick={() => shiftMonth(1)} aria-label="Mes siguiente">
                        <ChevronRight />
                    </Button>
                </div>
            </div>

            <div className="grid grid-cols-7 gap-1 text-center">
                {weekDays.map((day) => (
                    <div key={day} className="text-muted-foreground pb-1.5 text-[11px] font-semibold">
                        {day}
                    </div>
                ))}

                {cells.map((day, index) => {
                    if (day === null) {
                        return <div key={`empty-${index}`} />;
                    }

                    const key = `${year}-${month}-${day}`;
                    const dayAppointments = byDate.get(key) ?? [];
                    const iso = isoFor(day);
                    const isToday = today.getFullYear() === year && today.getMonth() === month && today.getDate() === day;
                    const isSelected = selectedDate === iso;

                    return (
                        <button
                            key={key}
                            type="button"
                            onClick={() => onSelectDate(isSelected ? null : iso)}
                            className={cn(
                                'focus-ring relative flex h-11 flex-col items-center justify-center rounded-lg text-sm transition-colors duration-150',
                                isSelected
                                    ? 'bg-primary text-primary-foreground font-bold'
                                    : isToday
                                      ? 'bg-primary-soft text-accent-foreground font-bold'
                                      : dayAppointments.length > 0
                                        ? 'hover:bg-muted font-semibold'
                                        : 'text-muted-foreground hover:bg-muted/60',
                            )}
                        >
                            <span className="tabular">{day}</span>
                            {dayAppointments.length > 0 && (
                                <span className="absolute bottom-1.5 flex gap-0.5">
                                    {dayAppointments.slice(0, 3).map((appointment) => (
                                        <span
                                            key={appointment.id}
                                            className={cn(
                                                'size-1 rounded-full',
                                                isSelected
                                                    ? 'bg-primary-foreground'
                                                    : appointment.type === 'teleconsulta'
                                                      ? 'bg-brand'
                                                      : 'bg-primary',
                                            )}
                                        />
                                    ))}
                                </span>
                            )}
                        </button>
                    );
                })}
            </div>

            <div className="text-muted-foreground mt-4 flex items-center justify-center gap-4 border-t pt-3 text-xs">
                <span className="flex items-center gap-1.5">
                    <span className="bg-primary size-1.5 rounded-full" />
                    Presencial
                </span>
                <span className="flex items-center gap-1.5">
                    <span className="bg-brand size-1.5 rounded-full" />
                    Teleconsulta
                </span>
            </div>
        </div>
    );
}
