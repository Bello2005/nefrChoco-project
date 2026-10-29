import { describe, expect, it, vi } from 'vitest';
import { installOfflineNavigationFallback, shouldFallbackToFullLoad } from './offline-navigation';
import { patientAssetUrls, type ViteManifest } from './offline-warmup';

type Handler = (event: CustomEvent) => boolean | void;

function fakeRouter() {
    const handlers: Record<string, Handler> = {};

    return {
        router: {
            on: (event: string, callback: Handler) => {
                handlers[event] = callback;
            },
        },
        emit: (event: string, detail: unknown) => handlers[event]?.(new CustomEvent(event, { detail })),
    };
}

describe('respaldo de navegación sin señal', () => {
    it('una visita de lectura que falla se repite como carga completa', () => {
        const { router, emit } = fakeRouter();
        const assign = vi.fn();
        installOfflineNavigationFallback(router as never, assign);

        emit('start', { visit: { url: new URL('https://nefrochoco.test/paciente/educativo/3'), method: 'get' } });
        const result = emit('exception', { exception: new Error('Network Error') });

        expect(assign).toHaveBeenCalledWith('https://nefrochoco.test/paciente/educativo/3');
        expect(result).toBe(false);
    });

    it('un envío de formulario que falla no recarga la página (lo atiende la cola)', () => {
        const { router, emit } = fakeRouter();
        const assign = vi.fn();
        installOfflineNavigationFallback(router as never, assign);

        emit('start', { visit: { url: '/paciente/signos-vitales', method: 'post' } });
        emit('exception', { exception: new Error('Network Error') });

        expect(assign).not.toHaveBeenCalled();
    });

    it('las recargas en segundo plano y las precargas no sacan a nadie de la pantalla', () => {
        expect(shouldFallbackToFullLoad({ url: '/x', method: 'get', async: true })).toBe(false);
        expect(shouldFallbackToFullLoad({ url: '/x', method: 'get', prefetch: true })).toBe(false);
        expect(shouldFallbackToFullLoad(null)).toBe(false);
    });
});

describe('precarga del código de las pantallas del paciente', () => {
    const manifest: ViteManifest = {
        'resources/js/app.tsx': { file: 'assets/app-1.js', css: ['assets/app-1.css'] },
        'resources/js/pages/paciente/signos-vitales/index.tsx': { file: 'assets/index-2.js', imports: ['_app-layout.js'] },
        '_app-layout.js': { file: 'assets/app-layout-3.js', imports: ['_button.js'] },
        '_button.js': { file: 'assets/button-4.js' },
        'resources/js/pages/admin/dashboard.tsx': { file: 'assets/dashboard-5.js', imports: ['_solo-admin.js'] },
        '_solo-admin.js': { file: 'assets/solo-admin-6.js' },
    };

    it('incluye la entrada, las pantallas del paciente y todo lo que importan', () => {
        expect(patientAssetUrls(manifest).sort()).toEqual(
            [
                '/build/assets/app-1.js',
                '/build/assets/app-1.css',
                '/build/assets/index-2.js',
                '/build/assets/app-layout-3.js',
                '/build/assets/button-4.js',
            ].sort(),
        );
    });

    it('no descarga pantallas de otros roles', () => {
        const urls = patientAssetUrls(manifest);
        expect(urls).not.toContain('/build/assets/dashboard-5.js');
        expect(urls).not.toContain('/build/assets/solo-admin-6.js');
    });
});
