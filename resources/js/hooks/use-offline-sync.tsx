import { flush, listPending } from '@/lib/offline-queue';
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

const OfflineSyncContext = createContext<OfflineSyncValue | null>(null);

export function OfflineSyncProvider({ children }: { children: ReactNode }) {
    // Cada cuenta ve y sincroniza solo su propia cola: lo de otra cuenta en el
    // mismo teléfono se queda intacto hasta que su dueño vuelva a entrar.
    const ownerId = usePage<SharedData>().props.auth.user.id;

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
