import { Badge } from '@/components/ui/badge';
import { formatRelative } from '@/lib/format';
import { Signal, SignalLow, WifiOff } from 'lucide-react';

export interface ConnectionCheck {
    level: string;
    at: string;
}

/**
 * Último resultado de "Probar mi conexión" del paciente.
 *
 * Lo ve el médico en su agenda y en la sala para decidir, antes de empezar,
 * si conviene arrancar con audio o pasar a una llamada telefónica.
 */
const config: Record<string, { label: string; variant: 'success' | 'warning' | 'destructive'; icon: typeof Signal }> = {
    video: { label: 'Conexión lista para video', variant: 'success', icon: Signal },
    solo_audio: { label: 'Conexión para solo audio', variant: 'warning', icon: SignalLow },
    insuficiente: { label: 'Conexión insuficiente', variant: 'destructive', icon: WifiOff },
};

export function ConnectionCheckBadge({ check }: { check: ConnectionCheck | null }) {
    if (!check) {
        return null;
    }

    const item = config[check.level];

    if (!item) {
        return null;
    }

    const Icon = item.icon;

    return (
        <Badge variant={item.variant} title={`Prueba hecha ${formatRelative(check.at)}`}>
            <Icon />
            {item.label} · {formatRelative(check.at)}
        </Badge>
    );
}
