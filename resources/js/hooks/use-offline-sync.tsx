import { flush, listPending } from '@/lib/offline-queue';
import { warmUpPatientOffline } from '@/lib/offline-warmup';
import { type SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

interface OfflineSyncValue {
    isOnline: boolean;
    pendingCount: number;
    isSyncing: boolean;
    refreshPending: () => Promise<void>;
    syncNow: () => Promise<void>;
}

/** Cada cuánto reintentar la cola cuando hay pendientes y el navegador dice «en línea». */
export const RETRY_INTERVAL_MS = 30_000;

const OfflineSyncContext = createContext<OfflineSyncValue | null>(null);

export function OfflineSyncProvider({ children }: { children: ReactNode }) {
    // Cada cuenta ve y sincroniza solo su propia cola: lo de otra cuenta en el
    // mismo teléfono se queda intacto hasta que su dueño vuelva a entrar.
    const { user, roles } = usePage<SharedData>().props.auth;
    const ownerId = user.id;
    const isPatient = roles.includes('paciente');

    const [isOnline, setIsOnline] = useState(() => (typeof navigator === 'undefined' ? true : navigator.onLine));
    const [pendingCount, setPendingCount] = useState(0);
    const [isSyncing, setIsSyncing] = useState(false);

    const refreshPending = useCallback(async () => {
        setPendingCount((await listPending(ownerId)).length);
    }, [ownerId]);

    const syncNow = useCallback(async () => {
        setIsSyncing(true);

        try {
            const synced = await flush(ownerId);
            await refreshPending();

            // Solo se recarga si algo llegó al servidor: así el paciente ve sus
            // mediciones ya sincronizadas sin perder lo que estuviera escribiendo.
            if (synced > 0) {
                router.reload();
            }
        } finally {
            setIsSyncing(false);
        }
    }, [ownerId, refreshPending]);

    // Con señal, se deja en el teléfono lo necesario para seguir sin ella.
    useEffect(() => {
        if (isPatient) {
            void warmUpPatientOffline();
        }
    }, [isPatient, isOnline]);

    useEffect(() => {
        void refreshPending();

        const handleOnline = () => {
            setIsOnline(true);
            void syncNow();
        };
        const handleOffline = () => setIsOnline(false);

        window.addEventListener('online', handleOnline);
        window.addEventListener('offline', handleOffline);

        if (navigator.onLine) {
            void syncNow();
        }

        return () => {
            window.removeEventListener('online', handleOnline);
            window.removeEventListener('offline', handleOffline);
        };
    }, [refreshPending, syncNow]);

    // Con una red que no pasa datos el navegador nunca deja de estar «en línea»,
    // así que el evento online no llega cuando la señal vuelve de verdad. Mientras
    // haya pendientes se reintenta cada cierto tiempo y al volver a la pestaña.
    useEffect(() => {
        if (pendingCount === 0) return;

        const retry = () => {
            if (navigator.onLine && !isSyncing) void syncNow();
        };
        const onVisible = () => document.visibilityState === 'visible' && retry();

        const timer = window.setInterval(retry, RETRY_INTERVAL_MS);
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            window.clearInterval(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [pendingCount, isSyncing, syncNow]);

    const value = useMemo(
        () => ({ isOnline, pendingCount, isSyncing, refreshPending, syncNow }),
        [isOnline, pendingCount, isSyncing, refreshPending, syncNow],
    );

    return <OfflineSyncContext.Provider value={value}>{children}</OfflineSyncContext.Provider>;
}

export function useOfflineSync(): OfflineSyncValue {
    const context = useContext(OfflineSyncContext);

    if (!context) {
        throw new Error('useOfflineSync debe usarse dentro de OfflineSyncProvider');
    }

    return context;
}
