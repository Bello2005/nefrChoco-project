/**
 * Service worker de IPS NefroChocó.
 *
 * Escrito a mano en lugar de generarlo con un plugin: la estrategia de caché
 * aquí es una decisión clínica, no solo de rendimiento. En zonas del Chocó con
 * conexión intermitente vale más mostrar una historia clínica de hace cinco
 * minutos que una pantalla de error, pero nunca vale mostrar datos de paciente
 * cacheados a quien ya cerró sesión.
 */

const VERSION = 'v2';
const SHELL_CACHE = `nefrochoco-shell-${VERSION}`;
const ASSET_CACHE = `nefrochoco-assets-${VERSION}`;
const CONTENT_CACHE = `nefrochoco-content-${VERSION}`;
const MATERIAL_CACHE = `nefrochoco-material-${VERSION}`;

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
    const allowed = [SHELL_CACHE, ASSET_CACHE, CONTENT_CACHE, MATERIAL_CACHE];

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

/**
 * Una pieza concreta de material, no la lista: /paciente/educativo/12.
 *
 * Se separa de la lista porque son cosas distintas. La lista cambia con los
 * filtros y conviene pedirla a la red; el material casi nunca cambia y es lo
 * que el paciente necesita tener encima cuando se queda sin señal.
 */
function isEducationalMaterial(url) {
    return /^\/paciente\/educativo\/\d+$/.test(url.pathname);
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

/**
 * Devuelve lo cacheado de inmediato y revalida por detrás.
 *
 * El paciente lee sin esperar aunque la señal esté mala, y si la IPS corrigió el
 * texto, la próxima apertura ya trae la versión nueva.
 */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const network = fetch(request)
        .then((response) => {
            if (response.ok) {
                cache.put(request, response.clone());
            }
            return response;
        })
        .catch(() => cached);

    return cached ?? network;
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

    if (isEducationalMaterial(url)) {
        event.respondWith(staleWhileRevalidate(request, MATERIAL_CACHE));
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
        // MATERIAL_CACHE entra aquí aunque el material no sea dato clínico: cada
        // página de Inertia lleva las props compartidas, y entre ellas el nombre
        // de quien la vio. Mientras el material viaje dentro de una página
        // completa, guardarlo más allá del cierre de sesión dejaría ese nombre
        // accesible en un teléfono compartido.
        event.waitUntil(Promise.all([caches.delete(SHELL_CACHE), caches.delete(CONTENT_CACHE), caches.delete(MATERIAL_CACHE)]));
    }

    // La pantalla de Educación pide que se baje el material marcado para sin
    // conexión, aprovechando que en ese momento hay señal.
    if (event.data?.type === 'PRECACHE_MATERIAL' && Array.isArray(event.data.urls)) {
        event.waitUntil(
            caches.open(MATERIAL_CACHE).then((cache) =>
                Promise.all(
                    event.data.urls
                        .filter((url) => typeof url === 'string' && /^\/paciente\/educativo\/\d+$/.test(url))
                        .map((url) =>
                            fetch(url)
                                .then((response) => (response.ok ? cache.put(url, response) : null))
                                .catch(() => null),
                        ),
                ),
            ),
        );
    }
});
