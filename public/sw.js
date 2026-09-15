/**
 * Service worker de IPS NefroChocó.
 *
 * Escrito a mano en lugar de generarlo con un plugin: la estrategia de caché
 * aquí es una decisión clínica, no solo de rendimiento. En zonas del Chocó con
 * conexión intermitente vale más mostrar una historia clínica de hace cinco
 * minutos que una pantalla de error, pero nunca vale mostrar datos de paciente
 * cacheados a quien ya cerró sesión.
 */

const VERSION = 'v1';
const SHELL_CACHE = `nefrochoco-shell-${VERSION}`;
const ASSET_CACHE = `nefrochoco-assets-${VERSION}`;
const CONTENT_CACHE = `nefrochoco-content-${VERSION}`;

const OFFLINE_URL = '/offline.html';

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(SHELL_CACHE)
            .then((cache) => cache.addAll([OFFLINE_URL]))
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    const allowed = [SHELL_CACHE, ASSET_CACHE, CONTENT_CACHE];

    event.waitUntil(
        caches
            .keys()
            .then((keys) => Promise.all(keys.filter((key) => !allowed.includes(key)).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

/** Los assets de Vite llevan hash en el nombre: si están en caché, nunca cambiaron. */
function isBuildAsset(url) {
    return url.pathname.startsWith('/build/');
}

function isEducationalContent(url) {
    return url.pathname.startsWith('/paciente/educativo');
}

async function cacheFirst(request, cacheName) {
    const cached = await caches.match(request);
    if (cached) return cached;

    const response = await fetch(request);
    if (response.ok) {
        const cache = await caches.open(cacheName);
        cache.put(request, response.clone());
    }
    return response;
}

async function networkFirst(request, cacheName) {
    try {
        const response = await fetch(request);
        if (response.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await caches.match(request);
        if (cached) return cached;
        throw error;
    }
}

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Nunca cachear la sesión: si expiró, el usuario debe ver el login real.
    if (url.pathname.startsWith('/login') || url.pathname.startsWith('/logout')) {
        return;
    }

    if (isBuildAsset(url)) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));
        return;
    }

    if (isEducationalContent(url)) {
        event.respondWith(networkFirst(request, CONTENT_CACHE));
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirst(request, SHELL_CACHE).catch(() => caches.match(OFFLINE_URL)));
    }
});

// La app avisa cuando el usuario cierra sesión: se borran las páginas cacheadas
// con datos de paciente para que no queden accesibles en el dispositivo.
self.addEventListener('message', (event) => {
    if (event.data?.type === 'CLEAR_PRIVATE_CACHE') {
        event.waitUntil(Promise.all([caches.delete(SHELL_CACHE), caches.delete(CONTENT_CACHE)]));
    }
});
