/**
 * Cola de envíos pendientes para cuando no hay conexión.
 *
 * Usa IndexedDB y no localStorage porque las mediciones no se pueden perder:
 * IndexedDB es transaccional y asíncrono, así que una pestaña que se cierra a
 * mitad de una escritura no deja la cola corrupta ni bloquea la interfaz.
 */

const DB_NAME = 'nefrochoco-offline';
const DB_VERSION = 2;
const STORE = 'pending-requests';

export interface PendingRequest {
    id: string;
    url: string;
    payload: Record<string, unknown>;
    label: string;
    createdAt: number;
    /**
     * Quién encoló esta entrada (id del usuario autenticado al momento de
     * guardarla). Es lo que evita que, en un teléfono compartido, lo que
     * registró el paciente A sin señal termine sincronizado en la historia
     * del paciente B que inicia sesión después.
     */
    ownerId: number | null;
}

function migrateToV2(store: IDBObjectStore): void {
    // v1 no tenía ownerId. Esas entradas no se pueden atribuir con certeza a
    // nadie: adivinar el dueño (p. ej. "el usuario que abra sesión primero")
    // es exactamente el bug que esta versión corrige. Descartarlas perdería
    // mediciones clínicas sin forma de recuperarlas; se conservan con
    // ownerId null en su lugar, y ese null las deja fuera del conteo y del
    // flush de cualquier usuario para siempre — quedan inertes pero intactas,
    // por si alguna vez hace falta revisarlas a mano.
    store.openCursor().onsuccess = (event) => {
        const cursor = (event.target as IDBRequest<IDBCursorWithValue | null>).result;
        if (!cursor) return;

        const value = cursor.value as PendingRequest;
        if (value.ownerId === undefined) {
            cursor.update({ ...value, ownerId: null });
        }
        cursor.continue();
    };
}

function openDatabase(): Promise<IDBDatabase> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, DB_VERSION);

        request.onupgradeneeded = (event) => {
            const db = request.result;
            const store = db.objectStoreNames.contains(STORE)
                ? request.transaction!.objectStore(STORE)
                : db.createObjectStore(STORE, { keyPath: 'id' });

            if (event.oldVersion < 2) {
                migrateToV2(store);
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

/** Solo lo que encoló `ownerId`: lo de otras cuentas en el mismo dispositivo se ignora, no se borra. */
export async function listPending(ownerId: number): Promise<PendingRequest[]> {
    if (!isOfflineQueueSupported()) return [];

    try {
        const all = await runTransaction<PendingRequest[]>('readonly', (store) => store.getAll() as IDBRequest<PendingRequest[]>);
        return all.filter((item) => item.ownerId === ownerId).sort((a, b) => a.createdAt - b.createdAt);
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
 * Reenvía lo pendiente de `ownerId`. Cada payload lleva su propia clave de
 * idempotencia, así que un reenvío duplicado no crea una medición repetida
 * en la historia.
 *
 * @returns cuántos envíos se sincronizaron con éxito.
 */
export async function flush(ownerId: number): Promise<number> {
    const pending = await listPending(ownerId);
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
                continue;
            }

            // 401/403/419: la sesión no sirve para enviar ahora mismo (token
            // vencido, CSRF caducado, etc.), no que el dato esté mal. Se
            // conserva igual que si no hubiera red, y se corta el intento:
            // si falló por sesión, los siguientes envíos fallarán igual.
            if (response.status === 401 || response.status === 403 || response.status === 419) {
                break;
            }
        } catch {
            // Sigue sin haber red: se conserva para el próximo intento.
            break;
        }
    }

    return synced;
}
