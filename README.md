# IPS NefroChocó · Plataforma de Telemedicina

Plataforma de telemedicina construida para **IPS NefroChocó**, orientada a la promoción, prevención y seguimiento de **enfermedades crónicas no transmisibles** (ECNT: diabetes, hipertensión arterial, enfermedad renal crónica) en zonas rurales del departamento del Chocó, Colombia — un contexto de **conectividad intermitente y dispersión poblacional alta**.

No es un producto multi-tenant: es un desarrollo a medida para un solo cliente institucional.

---

## Documentación

| Documento | Para qué |
|---|---|
| [docs/arquitectura.md](docs/arquitectura.md) | Diagramas, modelo de datos y el porqué de cada decisión |
| [docs/manual-tecnico.md](docs/manual-tecnico.md) | Instalar, operar y mantener |
| [docs/guia-usuario.md](docs/guia-usuario.md) | Cómo se usa, por rol |

## Tabla de contenidos

- [Qué resuelve](#qué-resuelve)
- [Stack técnico](#stack-técnico)
- [Roles del sistema](#roles-del-sistema)
- [Módulos](#módulos)
- [Decisiones de diseño que importan](#decisiones-de-diseño-que-importan)
- [Cumplimiento normativo](#cumplimiento-normativo)
- [Instalación](#instalación)
- [Usuarios de demostración](#usuarios-de-demostración)
- [Tests](#tests)
- [Pendiente para producción](#pendiente-para-producción)

---

## Qué resuelve

| Necesidad | Cómo se resuelve |
|---|---|
| El paciente vive lejos y la señal falla | La app funciona sin conexión: registra signos vitales y los sincroniza sola cuando vuelve la señal, sin duplicarlos |
| La atención no siempre puede ser presencial | Teleconsulta por videollamada, con sala privada por cita, a la que entran tanto el profesional como el paciente |
| El médico necesita saber a quién mirar primero | Apoyo a decisiones por reglas explicables: cruza señales ya registradas y dice **por qué** sugiere revisar a alguien |
| Hay que tamizar riesgo, no solo registrar datos | Formularios clínicos con motor de puntuación (FINDRISC, Morisky-Green) e interpretación automática |
| La enfermedad renal avanza sin síntomas | Cada control calcula la TFGe con CKD-EPI 2021 y la clasifica en KDIGO, y una regla avisa cuando la función cae entre controles |
| El material educativo no sirve si exige señal | Los artículos se escriben dentro de la plataforma y se descargan al teléfono: se leen después sin conexión |
| Los datos de salud están protegidos por ley | Cifrado en reposo, consentimiento versionado, segundo factor opcional y auditoría de cada acceso y cada cambio |

## Stack técnico

- **Backend:** Laravel 12 (PHP ^8.2), PostgreSQL
- **Frontend:** React 19 + TypeScript vía [Inertia.js](https://inertiajs.com/) — sin API REST separada
- **Estilos:** Tailwind CSS 4, sistema de diseño propio (Plus Jakarta Sans)
- **Roles:** [spatie/laravel-permission](https://spatie.be/docs/laravel-permission)
- **Auditoría:** [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog) + trait propio para el rastro de cambios
- **Segundo factor:** TOTP con [pragmarx/google2fa](https://github.com/antonioribeiro/google2fa) y [bacon/bacon-qr-code](https://github.com/Bacon/BaconQrCode)
- **Videollamada:** Jitsi Meet External API
- **Sin conexión:** service worker propio + cola en IndexedDB, aislada por cuenta
- **Tests:** [Pest](https://pestphp.com/) para el backend, [Vitest](https://vitest.dev/) para la lógica de la cola sin conexión

## Roles del sistema

- **`admin`** — usuarios, custodia del padrón, contenido educativo, auditoría, reporte de usabilidad
- **`medico`** — pacientes, historia clínica, agenda, teleconsulta, formularios, telemonitoreo, apoyo a decisiones
- **`paciente`** — sus citas, su historia, autorreporte de signos vitales, material educativo, su sala de teleconsulta

## Módulos

| Módulo | Estado |
|---|---|
| Autenticación, roles y gestión de usuarios | ✅ |
| Segundo factor por TOTP con códigos de recuperación | ✅ |
| Pacientes e historia clínica | ✅ |
| Agenda de citas con calendario | ✅ |
| Teleconsulta: sala del profesional y sala del paciente | ✅ |
| Consentimiento informado de teleconsulta (Res. 2654) | ✅ |
| Formularios clínicos con motor de puntuación | ✅ |
| Telemonitoreo de signos vitales con alertas | ✅ |
| Función renal: TFGe (CKD-EPI 2021) y clasificación KDIGO | ✅ |
| Apoyo a decisiones clínicas por reglas explicables | ✅ |
| Modo sin conexión (PWA + cola idempotente) | ✅ |
| Material educativo propio, legible sin conexión | ✅ |
| Cuestionario de usabilidad (SUS) y su reporte | ✅ |
| Notificaciones en plataforma | ✅ |
| Cifrado en reposo y consentimiento de datos (Ley 1581) | ✅ |
| Auditoría de accesos y de cambios | ✅ |
| Cabeceras de seguridad y límite de peticiones | ✅ |
| Jitsi autoalojado en el VPS (jitsi.bello.works, stable-11248) | ✅ |
| Pagos, IA predictiva | ❌ Fuera de alcance |

## Decisiones de diseño que importan

**El padrón es institucional; la agenda es personal.** Cualquier médico de la IPS atiende a cualquier persona inscrita, porque el programa rota profesionales y cubre municipios dispersos. Pero una cita es el compromiso de un profesional concreto: solo él la reprograma, la cancela, abre su sala y firma sus notas.

**Qué se cifró y qué no.** `full_name`, `document_number` y `municipality` quedaron **sin cifrar a propósito**: cada cifrado usa un vector distinto, así que cifrarlos habría roto el buscador, el índice único del documento y el reporte por municipio. Todo el contenido clínico y los datos de contacto sí van cifrados.

**La auditoría guarda nombres de campos, no valores.** `activity_log.properties` es JSON sin cifrar: copiar ahí un diagnóstico anularía el cifrado de la tabla de origen. Con saber quién tocó qué campo y cuándo alcanza, y el contenido vigente se consulta en la fila, cuya lectura también se audita.

**Idempotencia en la cola sin conexión.** Cada medición lleva un `client_uuid` generado en el dispositivo. Si el servidor guardó pero la respuesta se perdió, el reintento no duplica la medición ni vuelve a alertar al médico.

**El apoyo a decisiones no es IA.** Son reglas escritas que leen señales ya calculadas y explican, con el dato a la vista, por qué sugieren revisar a alguien. Los umbrales clínicos salen de los instrumentos validados; ninguna regla inventa medicina.

**TOTP en vez de SMS.** Una aplicación de autenticación genera el código sin red; un SMS depende de la misma cobertura que falla.

## Cumplimiento normativo

**Ley 1581 de 2012** — cifrado en reposo, consentimiento versionado del titular, y auditoría de lecturas *y* de cambios sobre datos clínicos. La lectura se registra en las cuatro pantallas que exponen contenido clínico: ficha del paciente, historia clínica, formulario clínico y telemonitoreo. El control de acceso se refuerza con policies donde el rol no basta.

**Resolución 2654 de 2019** — consentimiento informado específico de teleconsulta, distinto del de datos: se pide al entrar a la sala, explica en lenguaje sencillo que no hay examen físico, que la conexión puede cortarse, que la videollamada **no se graba** y que puede pedirse atención presencial. La Etapa 3 de la norma respalda además el segundo factor.

## Instalación

Requisitos: PHP ^8.2 (con `xmlwriter` y `pdo_pgsql`), Composer, Node 20+, PostgreSQL.

```bash
composer install && npm install
cp .env.example .env
php artisan key:generate
createdb teleproject
php artisan migrate --seed
```

```bash
php artisan serve
```

```bash
npm run dev
```

Detalle completo en el [manual técnico](docs/manual-tecnico.md).

## Usuarios de demostración

El seeder de demostración solo corre en entorno `local`. Todos con contraseña `password`:

| Correo | Rol |
|---|---|
| `admin@nefrochoco.co` | Administrador |
| `ana.mosquera@nefrochoco.co` | Médica |
| `juan.perea@gmail.com` | Paciente (recorre ambos consentimientos) |

El personal usa el dominio institucional porque la validación se lo exige; el paciente usa correo personal, que es justo lo que la regla deja libre.

El paciente de demostración arranca con una teleconsulta en curso, para poder entrar a la sala sin esperar.

## Tests

```bash
php artisan test
npm run test
```

**320 pruebas con Pest** (1.221 aserciones). Incluyen la matriz de autorización entre profesionales, el flujo completo del segundo factor, la idempotencia de la cola sin conexión, el cifrado de las respuestas de los formularios clínicos, el motor de reglas clínicas caso por caso, la TFGe contrastada contra la calculadora oficial, los dos consentimientos, la auditoría de lecturas sobre las cuatro pantallas clínicas, el puntaje SUS contra sets de respuestas calculados a mano, y el contraste de color de ambos temas calculado sobre los tokens del CSS.

**5 pruebas con Vitest** sobre `resources/js/lib/offline-queue.ts`: que la cola aísla lo pendiente por cuenta, que un 401/403/419 se conserva en vez de descartarse, y que las entradas guardadas antes de este aislamiento se migran sin perderse ni sincronizarse con la cuenta equivocada.

## Pendiente para producción

- Desplegar en el VPS (Ubuntu, Nginx + PHP-FPM + PostgreSQL)
- Apuntar `JITSI_DOMAIN` en Render al Jitsi ya autoalojado (`jitsi.bello.works`) y sumarle autenticación JWT a la sala
- Definir una Content-Security-Policy una vez que el video sea de origen propio
- Validar los umbrales de `config/clinical_support.php` con la médica de la IPS
- Respaldar la `APP_KEY` aparte de la base de datos: sin ella los datos cifrados son irrecuperables
- `APP_DEBUG=false` y `APP_ENV=production`
- Configurar un `MAIL_MAILER` real: hoy es `log`, así que "olvidé mi contraseña" no envía ningún correo, solo lo escribe en el log
- Definir `ADMIN_INITIAL_PASSWORD`: sin ella, `AdminUserSeeder` falla en vez de crear el admin con la contraseña de la demo
- Revisar con la médica el contenido de `EducationalContentSeeder` **antes** del primer `--seed` en producción: ese seeder corre en todos los entornos, no solo en `local`
