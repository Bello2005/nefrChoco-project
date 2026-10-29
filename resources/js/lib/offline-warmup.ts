/**
 * Deja en el teléfono lo mínimo para usar la plataforma sin señal.
 *
 * Mientras hay red, el service worker solo guarda lo que el paciente ya abrió
 * con una carga completa. Las pantallas a las que se llega tocando el menú no
 * pasan por ahí, y el código de cada pantalla se descarga la primera vez que se
 * abre. Resultado: tras un corte, la primera pantalla «nueva» no tenía con qué
 * dibujarse. Por eso, cuando hay señal, se descargan de antemano:
 *
 * - el código de las pantallas del paciente (sale del manifest de Vite), y
 * - las tres pantallas que se usan sin señal: inicio, signos vitales y educación.
 *
 * Ninguna de esas tres es de las pantallas cuya lectura se audita (historia
 * clínica, formulario clínico, telemonitoreo del médico): descargarlas por
 * adelantado no deja registros de lectura que la persona no hizo.
 */

export const PATIENT_OFFLINE_PAGES = ['/paciente/dashboard', '/paciente/signos-vitales', '/paciente/educativo'];

const PATIENT_PAGE_PREFIX = 'resources/js/pages/paciente/';
const APP_ENTRY = 'resources/js/app.tsx';

export interface ManifestChunk {
    file: string;
    css?: string[];
    imports?: string[];
}

export type ViteManifest = Record<string, ManifestChunk>;

/** Archivos (con su ruta pública) de la entrada y de las pantallas del paciente, con todo lo que importan. */
export function patientAssetUrls(manifest: ViteManifest): string[] {
    const files = new Set<string>();
    const visited = new Set<string>();

    const visit = (key: string) => {
        if (visited.has(key)) return;
        visited.add(key);

        const chunk = manifest[key];
        if (!chunk) return;

        files.add(chunk.file);
        chunk.css?.forEach((css) => files.add(css));
        chunk.imports?.forEach(visit);
    };

    visit(APP_ENTRY);
    Object.keys(manifest)
        .filter((key) => key.startsWith(PATIENT_PAGE_PREFIX))
        .forEach(visit);

    return [...files].map((file) => `/build/${file}`);
}

let warmedUp = false;

export async function warmUpPatientOffline(): Promise<void> {
    const worker = typeof navigator === 'undefined' ? null : navigator.serviceWorker?.controller;
    if (warmedUp || !worker || !navigator.onLine) return;

    warmedUp = true;

    try {
        const response = await fetch('/build/manifest.json', { cache: 'no-cache' });
        if (response.ok) {
            worker.postMessage({ type: 'PRECACHE_ASSETS', urls: patientAssetUrls(await response.json()) });
        }
    } catch {
        // Sin red a mitad de camino: se intenta en la próxima carga.
        warmedUp = false;
    }

    worker.postMessage({ type: 'PRECACHE_PAGES', urls: PATIENT_OFFLINE_PAGES });
}
