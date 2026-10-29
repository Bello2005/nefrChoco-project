/**
 * Cabecera CSRF para los envíos que no pasan por Inertia (la cola sin conexión
 * y el registro de mediciones).
 *
 * Se prefiere la cookie XSRF-TOKEN sobre la meta del HTML porque la meta se
 * escribe una sola vez, cuando el navegador carga la página completa. Si después
 * el paciente inicia sesión por Inertia (sin recargar), Laravel regenera la
 * sesión y la meta queda vieja: el envío recibía 419 y la cola se quedaba
 * esperando para siempre. La cookie, en cambio, se renueva en cada respuesta.
 */
export function csrfHeaders(cookieSource?: string): Record<string, string> {
    const cookies = cookieSource ?? (typeof document === 'undefined' ? '' : (document.cookie ?? ''));
    const match = cookies.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    if (match) {
        return { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) };
    }

    const meta = typeof document === 'undefined' ? null : document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]');

    return meta?.content ? { 'X-CSRF-TOKEN': meta.content } : {};
}
