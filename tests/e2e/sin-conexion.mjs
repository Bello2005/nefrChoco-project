#!/usr/bin/env node
/**
 * Escenario de extremo a extremo del modo sin conexión.
 *
 * Es la evidencia de la Tabla 30 del informe, ahora versionada en el repositorio
 * para que cualquiera la pueda repetir. Levanta la plataforma con datos de
 * demostración en una base SQLite aparte (no toca la base de desarrollo), abre
 * Chromium como lo haría el teléfono del paciente y prueba dos tipos de corte:
 *
 *   A. Sin red: el teléfono sabe que no hay conexión (modo avión, sin señal).
 *   B. Red que no pasa datos: el teléfono dice «en línea», pero nada llega al
 *      servidor (antena sin datos, Wi-Fi caído). Se simula apagando el servidor.
 *
 * Requisitos: dependencias instaladas (composer, npm), `npm run build` hecho y
 * Chromium de Playwright (`npx playwright install chromium`).
 *
 * Uso:  npm run e2e:sin-conexion
 *
 * Deja los resultados en docs/evidencias/sin-conexion/ (resultado.md,
 * resultado.json y capturas). Sale con código 1 si algún paso falla.
 */

/* global process */
import { execFileSync, spawn } from 'node:child_process';
import { existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
// E2E_OUT permite guardar una corrida aparte (p. ej. contra una versión anterior).
const OUT = resolve(ROOT, process.env.E2E_OUT ?? 'docs/evidencias/sin-conexion');
const DB = join(ROOT, 'database/e2e-sin-conexion.sqlite');
const PORT = 8123;
const BASE = `http://127.0.0.1:${PORT}`;

// Paciente de DemoDataSeeder (datos ficticios).
const PACIENTE = { email: 'juan.perea@gmail.com', password: 'password' };

// La base y los drivers se fijan por variables de entorno: Laravel no las
// sobrescribe con el .env, así que la base de desarrollo queda intacta.
const ENV = {
    ...process.env,
    APP_ENV: 'local',
    APP_DEBUG: 'false',
    APP_URL: BASE,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: DB,
    SESSION_DRIVER: 'file',
    CACHE_STORE: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
};

const results = [];
let server = null;

function step(scenario, paso, esperado, obtenido, ok) {
    results.push({ scenario, paso, esperado, obtenido, ok });
    console.log(`${ok ? '✔' : '✘'} [${scenario}] ${paso}: ${obtenido}`);
}

function artisan(...args) {
    execFileSync('php', ['artisan', ...args], { cwd: ROOT, env: ENV, stdio: 'pipe' });
}

/** Cuenta directa en la base: lo que de verdad llegó al servidor. */
function countVitalSigns() {
    const code = `$p = new PDO('sqlite:${DB}'); echo $p->query('select count(*) from vital_signs')->fetchColumn();`;
    return Number(execFileSync('php', ['-r', code], { cwd: ROOT }).toString().trim());
}

function startServer() {
    // El router de Laravel busca index.php en el directorio actual: se arranca desde public/.
    server = spawn('php', ['-S', `127.0.0.1:${PORT}`, join(ROOT, 'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], {
        cwd: join(ROOT, 'public'),
        env: ENV,
        stdio: 'ignore',
    });
}

function stopServer() {
    server?.kill('SIGTERM');
    server = null;
}

async function waitFor(check, timeoutMs, intervalMs = 500) {
    const end = Date.now() + timeoutMs;
    while (Date.now() < end) {
        if (await check()) return true;
        await new Promise((r) => setTimeout(r, intervalMs));
    }
    return false;
}

async function serverUp() {
    try {
        return (await fetch(`${BASE}/login`)).ok;
    } catch {
        return false;
    }
}

async function shot(page, name) {
    await page.screenshot({ path: join(OUT, `${name}.png`), fullPage: true });
}

/**
 * Una pantalla «en blanco» es la que no dibujó encabezado dentro de la app: es lo
 * que reportó la Tabla 30. El aviso de sin conexión (offline.html) no cuenta como
 * pantalla con contenido, porque vive fuera de la app (#app).
 */
async function hasContent(page) {
    return (await page.locator('#app h1').count()) > 0;
}

/** Devuelve false si la plataforma no confirmó que la medición quedó guardada. */
async function registrar(page, valor) {
    try {
        await page.selectOption('#type', 'glucemia', { timeout: 10_000 });
        await page.fill('#value', String(valor));
        await page.getByRole('button', { name: /Registrar medición|Guardar sin conexión/ }).click();
        await page.getByText('Guardada en este dispositivo', { exact: false }).last().waitFor({ timeout: 30_000 });
        return true;
    } catch {
        return false;
    }
}

async function pendientes(page) {
    return page.evaluate(
        () =>
            new Promise((resolve) => {
                const open = indexedDB.open('nefrochoco-offline');
                open.onsuccess = () => {
                    const req = open.result.transaction('pending-requests').objectStore('pending-requests').getAll();
                    req.onsuccess = () => resolve(req.result);
                };
                open.onerror = () => resolve([]);
            }),
    );
}

function writeReport() {
    writeFileSync(join(OUT, 'resultado.json'), JSON.stringify({ fecha: new Date().toISOString(), results }, null, 2));

    const rows = results.map((r) => `| ${r.scenario} | ${r.paso} | ${r.esperado} | ${r.obtenido} | ${r.ok ? 'Sí' : '**No**'} |`);
    writeFileSync(
        join(OUT, 'resultado.md'),
        [
            '# Escenario de operación sin conexión',
            '',
            `Fecha: ${new Date().toISOString()} · Script: \`tests/e2e/sin-conexion.mjs\` · Chromium (Playwright)`,
            '',
            'A = sin red (el teléfono sabe que no hay conexión). B = red que no pasa datos (el teléfono dice «en línea»; se simula apagando el servidor).',
            '',
            '| Escenario | Paso | Resultado esperado | Resultado obtenido | Cumple |',
            '|---|---|---|---|---|',
            ...rows,
            '',
        ].join('\n'),
    );
}

async function main() {
    if (!existsSync(join(ROOT, 'public/build/manifest.json'))) {
        throw new Error('Falta el build: corre `npm run build` antes del escenario.');
    }

    rmSync(OUT, { recursive: true, force: true });
    mkdirSync(OUT, { recursive: true });
    rmSync(DB, { force: true });
    writeFileSync(DB, '');
    artisan('migrate:fresh', '--seed', '--force');

    startServer();
    if (!(await waitFor(serverUp, 20_000))) throw new Error('El servidor de prueba no arrancó.');

    const browser = await chromium.launch();
    // Vista de escritorio para que el menú lateral esté siempre a la vista; el
    // comportamiento sin conexión es el mismo en el teléfono.
    const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
    const page = await context.newPage();

    try {
        // --- Preparación: entrar con señal, aceptar el consentimiento de datos ---
        await page.goto(`${BASE}/login`);
        await page.fill('#email', PACIENTE.email);
        await page.fill('#password', PACIENTE.password);
        await page.getByRole('button', { name: 'Entrar' }).click();
        await page.waitForLoadState('networkidle');

        if (page.url().includes('consentimiento')) {
            await page.locator('#accepted').click();
            await page.getByRole('button', { name: 'Autorizar y continuar' }).click();
            await page.waitForLoadState('networkidle');
        }

        // El service worker se instala en la primera carga y controla desde la siguiente.
        await page.evaluate(() => navigator.serviceWorker.ready);
        await page.reload();
        await waitFor(() => page.evaluate(() => Boolean(navigator.serviceWorker.controller)), 10_000);

        // Con señal: abrir Educación descarga el material marcado para sin conexión,
        // y cualquier pantalla del paciente descarga el código para usar sin red.
        await page
            .getByRole('link', { name: /Educación/ })
            .first()
            .click();
        await page.waitForLoadState('networkidle');
        await page.getByRole('link', { name: 'Inicio' }).first().click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3000);

        const base = countVitalSigns();

        // --- A. Sin red ---
        await context.setOffline(true);

        await page
            .getByRole('link', { name: /Signos vitales/ })
            .first()
            .click();
        await page
            .locator('#value')
            .waitFor({ timeout: 20_000 })
            .catch(() => null);
        const formSinRed = await page.locator('#value').count();
        await shot(page, 'A1-signos-vitales-sin-red');
        step(
            'A',
            'Abrir Signos vitales desde el menú, sin red',
            'Se ve el formulario',
            formSinRed ? 'Se ve el formulario' : 'Pantalla sin formulario',
            formSinRed > 0,
        );

        // Si no abrió, se carga con señal para poder medir los pasos siguientes.
        if (!formSinRed) {
            await context.setOffline(false);
            await page.goto(`${BASE}/paciente/signos-vitales`);
            await page.waitForLoadState('networkidle');
            await context.setOffline(true);
        }

        for (const valor of [110, 125, 140]) {
            await registrar(page, valor);
        }
        await shot(page, 'A2-tres-mediciones-guardadas');
        const enServidorA = countVitalSigns() - base;
        const enColaA = (await pendientes(page)).length;
        step(
            'A',
            'Registrar 3 mediciones sin red',
            '0 en el servidor; 3 en el dispositivo',
            `${enServidorA} en el servidor; ${enColaA} en el dispositivo`,
            enServidorA === 0 && enColaA === 3,
        );

        const copiaCola = await pendientes(page);

        await context.setOffline(false);
        const sincronizadoA = await waitFor(() => countVitalSigns() - base === 3, 30_000);
        await shot(page, 'A3-sincronizadas');
        step('A', 'Volver la conexión', '3 registros en el servidor', `${countVitalSigns() - base} registros`, sincronizadoA);

        // Reenvío forzado de los mismos envíos, como si la respuesta se hubiera
        // perdido en el camino: el servidor no debe duplicar.
        await page.evaluate(async (items) => {
            const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
            for (const item of items) {
                await fetch(item.url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
                    body: JSON.stringify(item.payload),
                });
            }
        }, copiaCola);
        step('A', 'Segundo reenvío forzado', 'Sin duplicados (3)', `${countVitalSigns() - base} registros`, countVitalSigns() - base === 3);

        // --- Navegación tras el corte (el defecto de la Tabla 30) ---
        await page
            .getByRole('link', { name: /Educación/ })
            .first()
            .click();
        await page.waitForURL('**/paciente/educativo');
        await page.locator('a[href*="/paciente/educativo/"]').first().waitFor({ timeout: 20_000 });
        // Tiempo para que el material marcado termine de bajar al teléfono.
        await page.waitForTimeout(3000);
        await context.setOffline(true);

        // route() de Ziggy arma URLs absolutas: se comparan por ruta.
        const articulos = await page
            .locator('a[href*="/paciente/educativo/"]')
            .evaluateAll((links) => [
                ...new Set(links.map((a) => new URL(a.href).pathname).filter((path) => /^\/paciente\/educativo\/\d+$/.test(path))),
            ]);
        let legibles = 0;
        let primeraConContenido = null;
        for (const [i, href] of articulos.entries()) {
            await page.goto(`${BASE}/paciente/educativo`).catch(() => null);
            await page
                .locator(`a[href$="${href}"]`)
                .first()
                .click()
                .catch(() => null);
            await page.waitForTimeout(2500);
            const ok = await hasContent(page);
            if (i === 0) {
                primeraConContenido = ok;
                await shot(page, 'A4-primer-articulo-tras-el-corte');
            }
            if (ok && new URL(page.url()).pathname === href) legibles++;
        }
        step(
            'A',
            'Primera navegación tras el corte',
            'Muestra el artículo (no pantalla vacía)',
            primeraConContenido ? 'Muestra el artículo' : 'Pantalla vacía',
            primeraConContenido === true,
        );
        step(
            'A',
            `Leer los ${articulos.length} artículos marcados sin conexión`,
            `${articulos.length} legibles`,
            `${legibles} legibles`,
            articulos.length > 0 && legibles === articulos.length,
        );

        await context.setOffline(false);
        await page.goto(`${BASE}/paciente/signos-vitales`);
        await page.waitForLoadState('networkidle');

        // --- B. Red que no pasa datos: el teléfono dice «en línea» ---
        const baseB = countVitalSigns();
        stopServer();
        await waitFor(async () => !(await serverUp()), 10_000);

        const enLinea = await page.evaluate(() => navigator.onLine);
        const confirmadaB = await registrar(page, 150);
        await shot(page, 'B1-medicion-con-red-sin-datos');
        const enColaB = (await pendientes(page)).length;
        step(
            'B',
            'Registrar 1 medición con red que no pasa datos',
            'Queda en el dispositivo (no se pierde)',
            `navigator.onLine=${enLinea}; ${enColaB} en el dispositivo${confirmadaB ? '' : '; sin aviso de guardado'}`,
            enLinea === true && enColaB === 1 && confirmadaB,
        );

        await page
            .getByRole('link', { name: /Educación/ })
            .first()
            .click();
        await page.waitForTimeout(3000);
        // Que haya encabezado no basta: si la visita falló, la pantalla anterior
        // sigue ahí. Tiene que haber llegado a Educación.
        const navB = (await hasContent(page)) && new URL(page.url()).pathname === '/paciente/educativo';
        await shot(page, 'B2-navegar-con-red-sin-datos');
        step(
            'B',
            'Navegar a Educación con red que no pasa datos',
            'Muestra la pantalla (no queda en blanco)',
            navB ? 'Muestra la pantalla' : 'Pantalla vacía',
            navB,
        );

        // Vuelve la red de verdad. El navegador nunca dejó de decir «en línea», así
        // que no hay evento online: la cola debe reintentar sola.
        startServer();
        await waitFor(serverUp, 20_000);
        await page.goto(`${BASE}/paciente/signos-vitales`);
        const sincronizadoB = await waitFor(() => countVitalSigns() - baseB === 1, 45_000);
        step('B', 'Vuelve la red', '1 registro sincronizado', `${countVitalSigns() - baseB} registros`, sincronizadoB);
    } finally {
        writeReport();
        await browser.close();
        stopServer();
        rmSync(DB, { force: true });
    }

    const failed = results.filter((r) => !r.ok).length;
    console.log(`\n${results.length - failed}/${results.length} pasos cumplen. Resultados en ${OUT}`);
    process.exit(failed ? 1 : 0);
}

main().catch((error) => {
    console.error(error);
    stopServer();
    process.exit(1);
});
