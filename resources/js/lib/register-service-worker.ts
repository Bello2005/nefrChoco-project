/**
 * El service worker solo se registra en producción: en desarrollo interceptaría
 * los módulos que Vite sirve en caliente y rompería el hot reload.
 */
export function registerServiceWorker(): void {
    if (!('serviceWorker' in navigator) || import.meta.env.DEV) {
        return;
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // Sin service worker la app sigue funcionando, solo pierde el modo offline.
        });
    });
}

/** Al cerrar sesión se descartan las páginas cacheadas con datos de paciente. */
export function clearPrivateCache(): void {
    navigator.serviceWorker?.controller?.postMessage({ type: 'CLEAR_PRIVATE_CACHE' });
}
