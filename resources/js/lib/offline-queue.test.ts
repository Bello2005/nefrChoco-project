import { IDBFactory } from 'fake-indexeddb';
import 'fake-indexeddb/auto';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { enqueue, flush, listPending } from './offline-queue';

const DB_NAME = 'nefrochoco-offline';
const STORE = 'pending-requests';

beforeEach(() => {
    // Base de datos nueva y vacía en cada prueba: nada de estado cruzado entre tests.
    globalThis.indexedDB = new IDBFactory();
    (globalThis as unknown as { document: Pick<Document, 'querySelector'> }).document = {
        querySelector: () => null,
    };
    vi.restoreAllMocks();
});

/** Inserta directamente una fila "vieja" (esquema v1, sin ownerId) para probar la migración. */
function seedLegacyEntryWithoutOwnerId(): Promise<void> {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(DB_NAME, 1);

        request.onupgradeneeded = () => {
            request.result.createObjectStore(STORE, { keyPath: 'id' });
        };

        request.onsuccess = () => {
            const db = request.result;
            const tx = db.transaction(STORE, 'readwrite');
            tx.objectStore(STORE).add({
                id: 'legacy-1',
                url: '/paciente/signos-vitales',
                payload: { value: '120' },
                label: 'Presión arterial',
                createdAt: 1,
                // Sin ownerId a propósito: así se guardaban antes de este cambio.
            });
            tx.oncomplete = () => {
                db.close();
                resolve();
            };
            tx.onerror = () => reject(tx.error);
        };

        request.onerror = () => reject(request.error);
    });
}

describe('offline-queue: aislamiento por cuenta', () => {
    it('listPending solo devuelve lo que encoló ese ownerId', async () => {
        await enqueue({ url: '/a', label: 'A', payload: { v: 1 }, ownerId: 1 });
        await enqueue({ url: '/b', label: 'B', payload: { v: 2 }, ownerId: 2 });

        const dePaciente1 = await listPending(1);
        const dePaciente2 = await listPending(2);

        expect(dePaciente1).toHaveLength(1);
        expect(dePaciente1[0].label).toBe('A');
        expect(dePaciente2).toHaveLength(1);
        expect(dePaciente2[0].label).toBe('B');
    });

    it('flush solo envía y borra lo del dueño; lo de otra cuenta en el mismo teléfono queda intacto', async () => {
        await enqueue({ url: '/a', label: 'A', payload: { v: 1 }, ownerId: 1 });
        await enqueue({ url: '/b', label: 'B', payload: { v: 2 }, ownerId: 2 });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200 } as Response));

        const synced = await flush(1);

        expect(synced).toBe(1);
        expect(fetch).toHaveBeenCalledTimes(1);
        expect(await listPending(1)).toHaveLength(0);
        // La entrada de la cuenta 2 nunca se tocó: ni se envió ni se borró.
        expect(await listPending(2)).toHaveLength(1);
    });

    it('401, 403 y 419 se conservan para reintentar, no se descartan como los 422', async () => {
        for (const status of [401, 403, 419]) {
            globalThis.indexedDB = new IDBFactory();

            await enqueue({ url: '/a', label: 'A', payload: { v: 1 }, ownerId: 1 });

            vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status } as Response));

            const synced = await flush(1);

            expect(synced).toBe(0);
            expect(await listPending(1)).toHaveLength(1);
        }
    });

    it('422 sí se descarta: el servidor ya rechazó el dato de forma definitiva', async () => {
        await enqueue({ url: '/a', label: 'A', payload: { v: 1 }, ownerId: 1 });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 422 } as Response));

        const synced = await flush(1);

        expect(synced).toBe(0);
        expect(await listPending(1)).toHaveLength(0);
    });
});

describe('offline-queue: migración de entradas sin ownerId (v1 -> v2)', () => {
    it('las conserva con ownerId null en vez de borrarlas, y nunca las sincroniza con nadie', async () => {
        await seedLegacyEntryWithoutOwnerId();

        // Cualquier lectura con la versión nueva dispara el upgrade v1 -> v2.
        expect(await listPending(1)).toHaveLength(0);
        expect(await listPending(2)).toHaveLength(0);

        // Pero la fila sigue ahí, no se perdió: solo quedó con ownerId null.
        const raw = await new Promise<{ id: string; ownerId: number | null }[]>((resolve, reject) => {
            const request = indexedDB.open(DB_NAME);
            request.onsuccess = () => {
                const db = request.result;
                const tx = db.transaction(STORE, 'readonly');
                const getAll = tx.objectStore(STORE).getAll();
                getAll.onsuccess = () => resolve(getAll.result);
                getAll.onerror = () => reject(getAll.error);
            };
            request.onerror = () => reject(request.error);
        });

        expect(raw).toHaveLength(1);
        expect(raw[0].id).toBe('legacy-1');
        expect(raw[0].ownerId).toBeNull();
    });
});
