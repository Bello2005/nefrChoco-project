/**
 * Clasifica el resultado de "Probar mi conexión" (Res. 1644 de 2026, art. 24.8).
 *
 * Es una función pura, sin navegador ni red, para que la regla se pueda probar
 * con Vitest y sea la misma que ve el paciente. Los umbrales llegan del
 * servidor (config/teleconsultation.php) y están en [CONFIRMAR].
 */

export type ConnectionLevel = 'video' | 'solo_audio' | 'insuficiente';

export type ConnectionIssue = 'sin_internet' | 'sin_microfono' | 'sin_camara' | 'lenta' | 'demora';

export interface ConnectionMeasurement {
    online: boolean;
    /** null si no se pudo pedir el permiso (por ejemplo, un navegador sin soporte). */
    microphone: boolean | null;
    camera: boolean | null;
    /** null si la medición falló. */
    latencyMs: number | null;
    downloadKbps: number | null;
}

export interface LevelThreshold {
    min_download_kbps: number;
    max_latency_ms: number;
}

export interface ConnectionThresholds {
    video: LevelThreshold;
    audio: LevelThreshold;
}

export interface ConnectionResult {
    level: ConnectionLevel;
    issues: ConnectionIssue[];
}

function meets(measurement: ConnectionMeasurement, threshold: LevelThreshold): boolean {
    return (
        measurement.latencyMs !== null &&
        measurement.downloadKbps !== null &&
        measurement.downloadKbps >= threshold.min_download_kbps &&
        measurement.latencyMs <= threshold.max_latency_ms
    );
}

export function classifyConnection(measurement: ConnectionMeasurement, thresholds: ConnectionThresholds): ConnectionResult {
    const issues: ConnectionIssue[] = [];

    if (!measurement.online || measurement.latencyMs === null || measurement.downloadKbps === null) {
        return { level: 'insuficiente', issues: ['sin_internet'] };
    }

    // Sin micrófono no hay consulta posible, ni siquiera por audio.
    if (measurement.microphone === false) {
        return { level: 'insuficiente', issues: ['sin_microfono'] };
    }

    if (meets(measurement, thresholds.video) && measurement.camera !== false) {
        return { level: 'video', issues };
    }

    if (measurement.camera === false) issues.push('sin_camara');
    if (measurement.downloadKbps < thresholds.video.min_download_kbps) issues.push('lenta');
    if (measurement.latencyMs > thresholds.video.max_latency_ms) issues.push('demora');

    if (meets(measurement, thresholds.audio)) {
        return { level: 'solo_audio', issues };
    }

    return { level: 'insuficiente', issues };
}

/** Velocidad aproximada a partir de lo que tardó en bajar un archivo. */
export function kbpsFrom(bytes: number, milliseconds: number): number {
    if (milliseconds <= 0) return 0;

    return Math.round((bytes * 8) / milliseconds);
}
