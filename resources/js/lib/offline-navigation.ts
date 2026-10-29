/**
 * Respaldo de navegación para cuando se cae la señal.
 *
 * Inertia no carga páginas completas al tocar un enlace: pide los datos por
 * detrás y además descarga el código de la pantalla nueva. Sin red, esa petición
 * falla y la pantalla se quedaba en blanco (Tabla 30 del informe: «la primera
 * navegación tras el corte muestra una pantalla vacía»).
 *
 * El service worker sí sabe responder a una carga completa: tiene la última
 * copia de la página o, si no, el aviso de sin conexión. Así que, cuando una
 * visita de lectura falla, se repite como carga completa y el service worker
 * decide qué mostrar.
 */

export interface VisitLike {
    url: URL | string;
    method: string;
    async?: boolean;
    prefetch?: boolean;
}

/**
 * Solo visitas de lectura que pidió la persona. Los envíos (POST, PUT…) los
 * resuelve cada formulario con la cola; las recargas en segundo plano (async) y
 * las precargas no deben sacar a nadie de la pantalla en la que está escribiendo.
 */
export function shouldFallbackToFullLoad(visit: VisitLike | null): visit is VisitLike {
    if (!visit) return false;

    return visit.method.toLowerCase() === 'get' && !visit.async && !visit.prefetch;
}

interface RouterLike {
    on(event: 'start', callback: (event: CustomEvent<{ visit: VisitLike }>) => void): unknown;
    on(event: 'exception', callback: (event: CustomEvent<{ exception: unknown }>) => boolean | void): unknown;
}

export function installOfflineNavigationFallback(router: RouterLike, assign: (href: string) => void = (href) => window.location.assign(href)): void {
    let lastVisit: VisitLike | null = null;

    router.on('start', (event) => {
        lastVisit = event.detail.visit;
    });

    router.on('exception', () => {
        if (!shouldFallbackToFullLoad(lastVisit)) return;

        const href = typeof lastVisit.url === 'string' ? lastVisit.url : lastVisit.url.href;
        lastVisit = null;
        assign(href);

        // Devolver false le dice a Inertia que el error ya se atendió.
        return false;
    });
}
