/**
 * Cola de envíos pendientes para cuando no hay conexión.
 *
 * Usa IndexedDB y no localStorage porque las mediciones no se pueden perder:
 * IndexedDB es transaccional y asíncrono, así que una pestaña que se cierra a
 * mitad de una escritura no deja la cola corrupta ni bloquea la interfaz.
 */

const DB_NAME = 'nefrochoco-offline';
const DB_VERSION = 1;
const STORE = 'pending-requests';

export interface PendingRequest {
    id: string;
    url: string;
    payload: Record<string, unknown>;
    label: string;
    createdAt: number;
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(STORE)) {
                request.result.createObjectStore(STORE, { keyPath: 'id' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function runTransaction<T>(mode: IDBTransactionMode, operation: (store: IDBObjectStore) => IDBRequest<T>): Promise<T> {
    return openDatabase().then(
        (db) =>
            new Promise<T>((resolve, reject) => {
                const transaction = db.transaction(STORE, mode);
                const request = operation(transaction.objectStore(STORE));

                request.onsuccess = () => resolve(request.result);
                request.onerror = () => reject(request.error);
                transaction.oncomplete = () => db.close();
            }),
    );
}

export function isOfflineQueueSupported(): boolean {
    return typeof indexedDB !== 'undefined';
}

export async function enqueue(entry: Omit<PendingRequest, 'id' | 'createdAt'>): Promise<PendingRequest> {
    const pending: PendingRequest = {
        ...entry,
        id: crypto.randomUUID(),
        createdAt: Date.now(),
    };

    await runTransaction('readwrite', (store) => store.add(pending));

    return pending;
}

export async function listPending(): Promise<PendingRequest[]> {
    if (!isOfflineQueueSupported()) return [];

    try {
        const all = await runTransaction<PendingRequest[]>('readonly', (store) => store.getAll() as IDBRequest<PendingRequest[]>);
        return all.sort((a, b) => a.createdAt - b.createdAt);
    } catch {
        return [];
    }
}

export async function remove(id: string): Promise<void> {
    await runTransaction('readwrite', (store) => store.delete(id) as unknown as IDBRequest<undefined>);
}

export async function clear(): Promise<void> {
    await runTransaction('readwrite', (store) => store.clear() as unknown as IDBRequest<undefined>);
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

/**
 * Reenvía todo lo pendiente. Cada payload lleva su propia clave de idempotencia,
 * así que un reenvío duplicado no crea una medición repetida en la historia.
 *
 * @returns cuántos envíos se sincronizaron con éxito.
 */
export async function flush(): Promise<number> {
    const pending = await listPending();
    let synced = 0;

    for (const item of pending) {
        try {
            const response = await fetch(item.url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify(item.payload),
            });

            // 422 significa que el servidor rechazó el dato: reintentarlo eternamente
            // solo llenaría la cola, así que se descarta junto con los envíos exitosos.
            if (response.ok || response.status === 422) {
                await remove(item.id);
                if (response.ok) synced++;
            }
        } catch {
            // Sigue sin haber red: se conserva para el próximo intento.
            break;
        }
    }

    return synced;
}
