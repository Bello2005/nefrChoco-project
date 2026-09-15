import { Badge } from '@/components/ui/badge';
import { CalendarCheck, CalendarClock, CalendarX, MonitorSmartphone, Stethoscope, UserX } from 'lucide-react';

type BadgeVariant = 'default' | 'secondary' | 'outline' | 'success' | 'warning' | 'info' | 'destructive' | 'brand' | 'accent';

const appointmentStatus: Record<string, { label: string; variant: BadgeVariant; icon: typeof CalendarCheck }> = {
    programada: { label: 'Programada', variant: 'info', icon: CalendarClock },
    completada: { label: 'Completada', variant: 'success', icon: CalendarCheck },
    cancelada: { label: 'Cancelada', variant: 'destructive', icon: CalendarX },
    no_asistio: { label: 'No asistió', variant: 'warning', icon: UserX },
};

export function AppointmentStatusBadge({ status }: { status: string }) {
    const config = appointmentStatus[status] ?? { label: status, variant: 'secondary' as BadgeVariant, icon: CalendarClock };
    const Icon = config.icon;

    return (
        <Badge variant={config.variant}>
            <Icon />
            {config.label}
        </Badge>
    );
}

export function AppointmentTypeBadge({ type }: { type: string }) {
    const isRemote = type === 'teleconsulta';

    return (
        <Badge variant={isRemote ? 'brand' : 'outline'}>
            {isRemote ? <MonitorSmartphone /> : <Stethoscope />}
            {isRemote ? 'Teleconsulta' : 'Presencial'}
        </Badge>
    );
}
