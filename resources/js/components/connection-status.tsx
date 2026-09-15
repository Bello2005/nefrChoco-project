import { Button } from '@/components/ui/button';
import { useOfflineSync } from '@/hooks/use-offline-sync';
import { cn } from '@/lib/utils';
import { CloudUpload, LoaderCircle, WifiOff } from 'lucide-react';

export function ConnectionStatus({ className }: { className?: string }) {
    const { isOnline, pendingCount, isSyncing, syncNow } = useOfflineSync();

    // Con conexión y sin nada pendiente no hay nada que comunicar: mostrar un
    // "en línea" permanente sería ruido en una interfaz clínica.
    if (isOnline && pendingCount === 0 && !isSyncing) {
        return null;
    }

    if (!isOnline) {
        return (
            <span
                className={cn('bg-warning-soft text-warning flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', className)}
                role="status"
            >
                <WifiOff className="size-3.5" />
                <span className="hidden sm:inline">Sin conexión</span>
                {pendingCount > 0 && <span className="tabular">· {pendingCount}</span>}
            </span>
        );
    }

    if (isSyncing) {
        return (
            <span className={cn('text-muted-foreground flex items-center gap-1.5 text-xs font-medium', className)} role="status">
                <LoaderCircle className="size-3.5 animate-spin" />
                <span className="hidden sm:inline">Sincronizando…</span>
            </span>
        );
    }

    return (
        <Button variant="ghost" size="sm" onClick={() => void syncNow()} className={cn('text-info gap-1.5', className)}>
            <CloudUpload className="size-3.5" />
            <span className="tabular">{pendingCount}</span>
            <span className="hidden sm:inline">por enviar</span>
        </Button>
    );
}
