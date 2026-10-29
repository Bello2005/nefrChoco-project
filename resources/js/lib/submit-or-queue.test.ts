import { describe, expect, it, vi } from 'vitest';
import { csrfHeaders } from './csrf';
import { submitOrQueue } from './submit-or-queue';

(globalThis as unknown as { document: Pick<Document, 'querySelector' | 'cookie'> }).document = {
    querySelector: () => null,
    cookie: '',
};

function base(overrides: Partial<Parameters<typeof submitOrQueue>[0]> = {}) {
    const enqueueImpl = vi.fn(async () => undefined);

    return {
        enqueueImpl,
        options: {
            url: '/paciente/signos-vitales',
            payload: { type: 'glucosa', value: '132' },
            label: 'Glucosa',
            ownerId: 7,
            isOnline: true,
            enqueueImpl,
            uuid: () => 'uuid-fijo',
            ...overrides,
        },
    };
}

describe('submitOrQueue: envío con respaldo en el dispositivo', () => {
    it('sin conexión guarda en la cola sin intentar la red', async () => {
        const fetchImpl = vi.fn();
        const { options, enqueueImpl } = base({ isOnline: false, fetchImpl });

        expect(await submitOrQueue(options)).toEqual({ kind: 'encolada' });
        expect(fetchImpl).not.toHaveBeenCalled();
        expect(enqueueImpl).toHaveBeenCalledWith(
            expect.objectContaining({ ownerId: 7, payload: expect.objectContaining({ client_uuid: 'uuid-fijo' }) }),
        );
    });

    it('«en línea» pero la red no pasa datos: la medición queda en la cola', async () => {
        // Es el caso que se perdía: navigator.onLine es true y el fetch falla.
        const fetchImpl = vi.fn(async () => {
            throw new TypeError('Failed to fetch');
        });
        const { options, enqueueImpl } = base({ fetchImpl: fetchImpl as unknown as typeof fetch });

        expect(await submitOrQueue(options)).toEqual({ kind: 'encolada' });
        expect(enqueueImpl).toHaveBeenCalledTimes(1);
    });

    it('si la señal es tan mala que se vence el tiempo, también queda en la cola', async () => {
        const fetchImpl = vi.fn(
            (_url: string, init: RequestInit) =>
                new Promise<Response>((_resolve, reject) => {
                    init.signal?.addEventListener('abort', () => reject(new DOMException('Aborted', 'AbortError')));
                }),
        );
        const { options, enqueueImpl } = base({ fetchImpl: fetchImpl as unknown as typeof fetch, timeoutMs: 10 });

        expect(await submitOrQueue(options)).toEqual({ kind: 'encolada' });
        expect(enqueueImpl).toHaveBeenCalledTimes(1);
    });

    it('el reintento usa la misma clave que el primer intento (no duplica en el servidor)', async () => {
        const sent: string[] = [];
        const fetchImpl = vi.fn(async (_url: string, init: RequestInit) => {
            sent.push(JSON.parse(init.body as string).client_uuid);
            throw new TypeError('Failed to fetch');
        });
        const { options, enqueueImpl } = base({ fetchImpl: fetchImpl as unknown as typeof fetch });

        await submitOrQueue(options);

        const queued = (enqueueImpl.mock.calls[0] as unknown as [{ payload: { client_uuid: string } }])[0];
        expect(queued.payload.client_uuid).toBe(sent[0]);
    });

    it('con respuesta exitosa no guarda nada en la cola', async () => {
        const fetchImpl = vi.fn(async () => new Response('{"message":"ok"}', { status: 200 }));
        const { options, enqueueImpl } = base({ fetchImpl });

        expect(await submitOrQueue(options)).toEqual({ kind: 'enviada' });
        expect(enqueueImpl).not.toHaveBeenCalled();
    });

    it('un 422 devuelve los errores por campo y no encola un dato que el servidor ya rechazó', async () => {
        const body = { message: 'Dato inválido', errors: { value: ['El valor está fuera de rango.', 'otro'] } };
        const fetchImpl = vi.fn(async () => new Response(JSON.stringify(body), { status: 422 }));
        const { options, enqueueImpl } = base({ fetchImpl });

        expect(await submitOrQueue(options)).toEqual({
            kind: 'rechazada',
            errors: { value: 'El valor está fuera de rango.' },
            message: 'Dato inválido',
        });
        expect(enqueueImpl).not.toHaveBeenCalled();
    });

    it.each([419, 500, 503])('un %i (sesión o servidor) conserva la medición para reintentar', async (status) => {
        const fetchImpl = vi.fn(async () => new Response('', { status }));
        const { options, enqueueImpl } = base({ fetchImpl });

        expect(await submitOrQueue(options)).toEqual({ kind: 'encolada' });
        expect(enqueueImpl).toHaveBeenCalledTimes(1);
    });
});

describe('csrfHeaders', () => {
    it('prefiere la cookie XSRF-TOKEN, que se renueva en cada respuesta', () => {
        expect(csrfHeaders('a=1; XSRF-TOKEN=abc%3D%3D; b=2')).toEqual({ 'X-XSRF-TOKEN': 'abc==' });
    });

    it('sin cookie y sin meta no manda cabecera', () => {
        expect(csrfHeaders('')).toEqual({});
    });
});
