import { csrfHeaders } from '@/lib/csrf';
import { enqueue, type PendingRequest } from '@/lib/offline-queue';

/**
 * Envía una medición y, si la red falla, la deja en el dispositivo.
 *
 * Antes la decisión se tomaba solo con navigator.onLine. Ese indicador dice si
 * el teléfono está conectado a una red, no si pasa internet: con una antena sin
 * datos, un Wi-Fi caído o una señal de una rayita, marca «en línea», el envío
 * fallaba y la medición se perdía. Es el caso más común en zona rural, así que
 * ahora se intenta enviar y, ante cualquier falla que no sea culpa del dato, se
 * guarda en la cola.
 *
 * El client_uuid se genera antes del primer intento y se conserva en la cola.
 * Si el servidor alcanzó a guardar pero la respuesta se perdió en el camino, el
 * reenvío trae la misma clave y no duplica la medición ni vuelve a alertar.
 */

export type SubmitOutcome =
    | { kind: 'enviada' }
    | { kind: 'encolada' }
    | { kind: 'rechazada'; errors: Record<string, string>; message: string | null };

export interface SubmitOrQueueOptions {
    url: string;
    payload: Record<string, unknown>;
    label: string;
    ownerId: number;
    isOnline: boolean;
    /**
     * Cuánto esperar antes de darse por vencido. En la prueba de 3G lento una
     * pantalla completa tardó ~20 s; un envío de una medición pesa mucho menos,
     * así que 20 s cubre la señal mala sin dejar al paciente mirando un botón.
     */
    timeoutMs?: number;
    fetchImpl?: typeof fetch;
    enqueueImpl?: (entry: Omit<PendingRequest, 'id' | 'createdAt'>) => Promise<unknown>;
    uuid?: () => string;
}

export const SUBMIT_TIMEOUT_MS = 20_000;

/** Laravel devuelve cada campo con una lista de mensajes; el formulario muestra el primero. */
function firstErrors(body: unknown): Record<string, string> {
    const errors = (body as { errors?: Record<string, string[] | string> } | null)?.errors ?? {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, Array.isArray(messages) ? messages[0] : messages]));
}

export async function submitOrQueue(options: SubmitOrQueueOptions): Promise<SubmitOutcome> {
    const {
        url,
        label,
        ownerId,
        isOnline,
        timeoutMs = SUBMIT_TIMEOUT_MS,
        fetchImpl = fetch,
        enqueueImpl = enqueue,
        uuid = () => crypto.randomUUID(),
    } = options;

    const payload = { ...options.payload, client_uuid: uuid() };

    const queue = async (): Promise<SubmitOutcome> => {
        await enqueueImpl({ url, label, payload, ownerId });
        return { kind: 'encolada' };
    };

    // Si el navegador ya sabe que no hay red, ni se intenta: la respuesta es
    // inmediata y no hay que esperar a que venza el tiempo.
    if (!isOnline) {
        return queue();
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);

    try {
        const response = await fetchImpl(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...csrfHeaders(),
            },
            body: JSON.stringify(payload),
            signal: controller.signal,
            credentials: 'same-origin',
        });

        if (response.ok) {
            return { kind: 'enviada' };
        }

        // 422 es el único caso en que el problema es el dato: guardarlo para
        // después solo serviría para que el servidor lo rechace otra vez.
        if (response.status === 422) {
            const body = await response.json().catch(() => null);
            const message = (body as { message?: string } | null)?.message ?? null;

            return { kind: 'rechazada', errors: firstErrors(body), message };
        }

        // 419/401/403 (sesión vencida) y 5xx (servidor caído) no dicen nada malo
        // del dato: se conserva y la cola lo reintenta cuando se pueda.
        return queue();
    } catch {
        // Sin red, red sin datos o tiempo vencido.
        return queue();
    } finally {
        clearTimeout(timer);
    }
}
