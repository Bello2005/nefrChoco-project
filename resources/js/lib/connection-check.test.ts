import { describe, expect, it } from 'vitest';
import { classifyConnection, type ConnectionMeasurement, type ConnectionThresholds, kbpsFrom } from './connection-check';

// Umbrales de prueba, no los de producción: la regla se prueba sola.
const thresholds: ConnectionThresholds = {
    video: { min_download_kbps: 1000, max_latency_ms: 400 },
    audio: { min_download_kbps: 100, max_latency_ms: 1000 },
};

const good: ConnectionMeasurement = { online: true, microphone: true, camera: true, latencyMs: 80, downloadKbps: 5000 };

describe('classifyConnection', () => {
    it('con buena conexión, cámara y micrófono queda lista para video', () => {
        expect(classifyConnection(good, thresholds)).toEqual({ level: 'video', issues: [] });
    });

    it('sin internet no alcanza', () => {
        expect(classifyConnection({ ...good, online: false }, thresholds).level).toBe('insuficiente');
        expect(classifyConnection({ ...good, latencyMs: null }, thresholds).issues).toEqual(['sin_internet']);
    });

    it('sin micrófono no alcanza, aunque la red sea buena', () => {
        expect(classifyConnection({ ...good, microphone: false }, thresholds)).toEqual({ level: 'insuficiente', issues: ['sin_microfono'] });
    });

    it('sin cámara pero con buena red, mejor solo audio', () => {
        expect(classifyConnection({ ...good, camera: false }, thresholds)).toEqual({ level: 'solo_audio', issues: ['sin_camara'] });
    });

    it('lenta para video pero suficiente para audio', () => {
        expect(classifyConnection({ ...good, downloadKbps: 300 }, thresholds)).toEqual({ level: 'solo_audio', issues: ['lenta'] });
    });

    it('con mucha demora pasa a solo audio', () => {
        expect(classifyConnection({ ...good, latencyMs: 700 }, thresholds)).toEqual({ level: 'solo_audio', issues: ['demora'] });
    });

    it('muy lenta no alcanza ni para audio', () => {
        expect(classifyConnection({ ...good, downloadKbps: 50, latencyMs: 1500 }, thresholds)).toEqual({
            level: 'insuficiente',
            issues: ['lenta', 'demora'],
        });
    });

    it('en el límite exacto del umbral cuenta como suficiente', () => {
        expect(classifyConnection({ ...good, downloadKbps: 1000, latencyMs: 400 }, thresholds).level).toBe('video');
    });

    it('si no se pudo preguntar por la cámara no se descarta el video', () => {
        expect(classifyConnection({ ...good, camera: null, microphone: null }, thresholds).level).toBe('video');
    });
});

describe('kbpsFrom', () => {
    it('convierte bytes y milisegundos a kilobits por segundo', () => {
        expect(kbpsFrom(204800, 1000)).toBe(1638);
        expect(kbpsFrom(204800, 0)).toBe(0);
    });
});
