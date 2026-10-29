# Escenario de operación sin conexión

Fecha: 2026-10-29T19:53:54.195Z · Script: `tests/e2e/sin-conexion.mjs` · Chromium (Playwright)

A = sin red (el teléfono sabe que no hay conexión). B = red que no pasa datos (el teléfono dice «en línea»; se simula apagando el servidor).

| Escenario | Paso | Resultado esperado | Resultado obtenido | Cumple |
|---|---|---|---|---|
| A | Abrir Signos vitales desde el menú, sin red | Se ve el formulario | Se ve el formulario | Sí |
| A | Registrar 3 mediciones sin red | 0 en el servidor; 3 en el dispositivo | 0 en el servidor; 3 en el dispositivo | Sí |
| A | Volver la conexión | 3 registros en el servidor | 3 registros | Sí |
| A | Segundo reenvío forzado | Sin duplicados (3) | 3 registros | Sí |
| A | Primera navegación tras el corte | Muestra el artículo (no pantalla vacía) | Muestra el artículo | Sí |
| A | Leer los 5 artículos marcados sin conexión | 5 legibles | 5 legibles | Sí |
| B | Registrar 1 medición con red que no pasa datos | Queda en el dispositivo (no se pierde) | navigator.onLine=true; 1 en el dispositivo | Sí |
| B | Navegar a Educación con red que no pasa datos | Muestra la pantalla (no queda en blanco) | Muestra la pantalla | Sí |
| B | Vuelve la red | 1 registro sincronizado | 1 registros | Sí |
