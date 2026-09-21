# Manual técnico — IPS NefroChocó

Guía para instalar, operar y mantener la plataforma. Para entender *por qué* está construida así, ver [arquitectura.md](arquitectura.md).

- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Variables de entorno](#variables-de-entorno)
- [Comandos del día a día](#comandos-del-día-a-día)
- [Estructura del código](#estructura-del-código)
- [Pruebas](#pruebas)
- [Operación](#operación)
- [Problemas frecuentes](#problemas-frecuentes)
- [Pendiente para producción](#pendiente-para-producción)

---

## Requisitos

- PHP ^8.2 con las extensiones `xmlwriter` (genera el QR del segundo factor) y `pdo_pgsql`
- Composer
- Node 20+
- PostgreSQL 14+

PostgreSQL es el **único** motor soportado: `config/database.php` solo declara `pgsql` y `sqlite` (este último para las pruebas). Los conectores de MySQL y SQL Server se retiraron a propósito.

## Instalación

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

createdb teleproject
php artisan migrate --seed
```

`--seed` crea los roles, el administrador y —solo en entorno `local`— los datos de demostración del Chocó.

Levantar el entorno:

```bash
php artisan serve
```

```bash
npm run dev
```

## Variables de entorno

Además de las estándar de Laravel:

| Variable | Para qué sirve |
|---|---|
| `DB_CONNECTION=pgsql` | Único motor soportado |
| `JITSI_DOMAIN` | Dominio de la videollamada. El servidor autoalojado ya existe (`jitsi.bello.works`, versión `stable-11248`), pero producción (Render) todavía apunta a `meet.jit.si` por defecto; al cambiar esta variable la política de permisos del navegador se ajusta sola |
| `PRIVACY_CONSENT_VERSION` | Versión del consentimiento de datos (Ley 1581) |
| `PRIVACY_TELECONSULTATION_CONSENT_VERSION` | Versión del consentimiento de teleconsulta (Res. 2654) |
| `PRIVACY_CONTACT_EMAIL` | Correo de habeas data que se muestra al titular |
| `TELECONSULTATION_JOIN_MINUTES_BEFORE` | Minutos antes de la cita en que se abre la sala |
| `TELECONSULTATION_JOIN_MINUTES_AFTER` | Minutos después en que la sala se cierra |
| `ALLOWED_EMAIL_DOMAIN` | Dominio institucional exigido al personal (`admin` y `medico`) al crear o editar su cuenta. Los pacientes no tienen restricción: usan su correo personal |
| `EDUCATIONAL_MAX_BODY_CHARACTERS` | Tope del cuerpo de un contenido educativo. No es un límite de base de datos sino de conexión: el material se descarga entero al teléfono del paciente |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Ambos en `es`. El valor por defecto de `config/app.php` también es `es`, para que un entorno sin `.env` no revierta los mensajes a inglés |
| `ADMIN_INITIAL_PASSWORD` | Contraseña del admin que crea `AdminUserSeeder`. Solo se usa fuera de `local`/`testing`: sin ella, el seeder falla en vez de crear la cuenta con la contraseña de la demo |

**Subir una versión de consentimiento tiene efecto inmediato**: quienes aceptaron la anterior vuelven a ver la pantalla. Es el mecanismo previsto por la ley, no un efecto secundario.

`APP_KEY` cifra los campos sensibles. **Perderla vuelve ilegibles las historias clínicas, los teléfonos y los secretos del segundo factor.** Debe respaldarse aparte de la base de datos.

## Comandos del día a día

```bash
php artisan test              # suite completa
./vendor/bin/pint --dirty     # formato PHP de lo modificado
npm run lint                  # ESLint con corrección automática
npm run format                # Prettier
npx tsc --noEmit              # verificación de tipos
npm run build                 # compilar para producción
```

Las páginas nuevas de Inertia **deben compilarse** antes de que las pruebas que las renderizan pasen: si falta el archivo en el manifiesto de Vite, el test falla con `Unable to locate file in Vite manifest`.

## Estructura del código

```
app/
├── Enums/            Role, VitalSignType, EcntCategory, BiologicalSex
├── Http/
│   ├── Controllers/  Delgados: reciben, autorizan, delegan
│   ├── Requests/     Toda la validación de entrada
│   └── Middleware/   EnsureDataConsent, SecurityHeaders
├── Models/
│   └── Concerns/     LogsChangedFields (rastro de auditoría propio)
├── Notifications/    Solo por base de datos
├── Policies/         AppointmentPolicy, PatientPolicy, ClinicalHistoryPolicy
├── Services/         Lógica de negocio
└── Support/
    ├── ClinicalFormCatalog.php   Instrumentos clínicos versionados
    ├── SusInstrument.php         Enunciados y fórmula del SUS
    └── ClinicalRules/            Motor de apoyo a decisiones

config/
├── privacy.php             Versiones de consentimiento
├── teleconsultation.php    Ventana de entrada a la sala
├── clinical_support.php    Umbrales operativos del motor de reglas
├── vital_signs.php         Rangos de referencia de los signos vitales
└── nefrochoco.php          Dominio institucional y tope del material educativo

resources/js/
├── components/   ui/ (primitivas), forms/, charts.tsx
├── hooks/        use-offline-sync.tsx
├── lib/          offline-queue.ts
└── pages/        Un componente por ruta
```

**`config/vital_signs.php`** guarda los rangos de referencia que alimentan el panel de alertas de telemonitoreo. No se leen de variables de entorno a propósito: son criterios clínicos, no configuración de despliegue, y versionarlos en git deja trazabilidad de qué rango se aplicó y cuándo, igual que con los instrumentos del catálogo. La presión arterial tiene dos entradas, `presion_arterial` (sistólica) y `presion_diastolica`, y ambas están marcadas como pendientes de validación con la médica de la IPS.

## Pruebas

```bash
php artisan test                                   # todo
php artisan test tests/Feature/Autorizacion        # una carpeta
php artisan test --filter="contraste"              # por nombre
npm run test                                        # Vitest: lógica de frontend (cola sin conexión)
```

Las pruebas de backend corren sobre SQLite en memoria con `RefreshDatabase`. Son **320** y cubren, entre otras cosas, la matriz de autorización entre profesionales, el segundo factor completo, la idempotencia de la cola sin conexión, el cifrado de las respuestas de los formularios clínicos, el motor de reglas clínicas caso por caso, la TFGe contrastada contra la calculadora oficial, el puntaje SUS sobre sets de respuestas calculados a mano, y el contraste de color de ambos temas leyendo los tokens del CSS.

**5 pruebas con Vitest** cubren `resources/js/lib/offline-queue.ts` con `fake-indexeddb`: aislamiento de la cola por `ownerId`, que 401/403/419 se conservan para reintentar en vez de descartarse como un 422, y que las entradas de antes de este cambio (sin `ownerId`) se migran conservándolas en vez de perderlas o de atribuírselas a quien inicie sesión primero. Config en `vitest.config.ts`.

Al agregar una prueba que renderiza una página nueva, compilar antes con `npm run build`.

## Operación

**Cuentas.** Las crea el administrador desde `/admin/usuarios`; no hay autoservicio para el personal. Cada usuario activa su segundo factor de forma opcional desde `/settings/two-factor`.

**Si alguien pierde el teléfono del segundo factor**, entra con uno de sus ocho códigos de recuperación. Si tampoco los tiene, un administrador debe limpiar las columnas `two_factor_*` de ese usuario en la base de datos: no hay forma de recuperarlo desde la interfaz, y es deliberado.

**Cuentas del personal.** El correo debe pertenecer al dominio de `ALLOWED_EMAIL_DOMAIN` y escribirse en minúsculas; la regla se aplica al crear y al editar, porque exigirla solo al crear dejaría la edición como vía de escape. Los pacientes quedan libres a propósito: usan el correo personal, que es el único que revisan y por el que pueden recuperar la contraseña.

**Vincular un paciente a su cuenta.** La ficha clínica (`patients`) y la cuenta (`users`) son cosas distintas: muchas fichas del programa corresponden a personas sin acceso a la plataforma. Se vincula desde `/admin/usuarios` al crear o editar la cuenta: con el rol `paciente` seleccionado aparece el selector **Ficha del paciente**, que ofrece las fichas sin cuenta más la del usuario que se esté editando. Una ficha que ya pertenece a otra cuenta no se ofrece ni se acepta aunque se fuerce el envío. Si la cuenta cambia a un rol que no es `paciente`, suelta la ficha: una historia clínica colgando de una cuenta de médico sería un dato falso en la tabla.

Sin vincular, el paciente entra pero ve "tu cuenta no está vinculada": sin citas, sin historia y sin sala de teleconsulta.

**Material educativo.** Se publica desde `/admin/educativo`. Un contenido tiene **cuerpo propio o enlace externo**, y solo el cuerpo propio puede marcarse como disponible sin conexión —un enlace vive en otro dominio y el service worker no lo intercepta, así que marcarlo prometería algo que no ocurre—. El cuerpo se escribe en Markdown y se convierte descartando el HTML crudo.

**Usabilidad.** `/admin/usabilidad` reporta el puntaje SUS con su desglose por rol y el aporte medio de cada afirmación. El cuestionario lo responde cualquier rol desde `/usabilidad`, una sola vez por persona. El reporte no muestra nombres, tampoco en los comentarios.

**Auditoría.** `/admin/auditoria` permite filtrar entre accesos y cambios. Los cambios guardan qué campos se tocaron, nunca sus valores.

Se registra la lectura de cuatro pantallas: ficha del paciente, historia clínica (incluida su versión imprimible), detalle de formulario clínico y telemonitoreo. Cada fila indica el tipo de recurso. Las recargas parciales de Inertia también se registran y aparecen marcadas como **(refresco)**, para distinguirlas de una consulta nueva sin perder la constancia.

## Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `Unable to locate file in Vite manifest` | Falta compilar. `npm run build` |
| El QR del segundo factor no aparece | Falta la extensión `xmlwriter` de PHP |
| La videollamada carga en negro | Jitsi tarda; si falla del todo, el componente muestra un aviso con opción de reintentar |
| La sala dice "no moderators have yet arrived" y no entra | `meet.jit.si` exige que un usuario **autenticado** inicie la reunión, y el profesional entra como anónimo. No se puede evitar desde el iframe: es política del servidor. Apuntar `JITSI_DOMAIN` a una instancia que permita creación anónima, o autoalojar |
| Tras un `git pull` la interfaz se comporta como antes | `public/build` está en `.gitignore`: el pull trae el código pero no los assets compilados. `npm run build` y recargar forzando caché |
| La sala dice que ya se cerró | La cita quedó fuera de la ventana. Ajustable con `TELECONSULTATION_JOIN_MINUTES_*` |
| Historias clínicas ilegibles | Se cambió la `APP_KEY`. Restaurar la original |
| Un médico recibe 403 sobre una cita | Correcto: solo gestiona las suyas. El padrón sí es compartido |
| No se puede guardar un usuario del personal | El correo no está en el dominio de `ALLOWED_EMAIL_DOMAIN`, o tiene mayúsculas |
| Un paciente no ve citas ni historia | Su cuenta no está vinculada a una ficha. Se vincula desde `/admin/usuarios` |
| El material educativo no queda disponible sin conexión | Solo se precarga el que tiene cuerpo propio y la marca de disponible sin conexión, y se descarga al abrir la pantalla de Educación con señal |
| Los errores de formulario salen como `validation.algo` | Falta la línea en `lang/es/validation.php`, o `APP_LOCALE` no es `es` |

## Pendiente para producción

- **Desplegar en el VPS** (Ubuntu, Nginx + PHP-FPM + PostgreSQL).
- **Autoalojar Jitsi** y apuntar `JITSI_DOMAIN` al servidor propio. Es parte del alcance del proyecto, no algo descartado. Hoy las salas viven en `meet.jit.si`, que es público: el nombre de sala es un UUID no adivinable, pero la conversación pasa por infraestructura de terceros. Además, el Jitsi autoalojado debe quedar con **autenticación JWT**: sin ella, cualquiera que averigüe el nombre de la sala puede entrar, autoalojado o no. Producción no debe salir con `JITSI_DOMAIN=meet.jit.si`.
- **Definir una Content-Security-Policy** una vez que el video sea de origen propio. No se puso antes porque una CSP mal ajustada rompe la videollamada sin mostrar ningún error.
- **Validar el contenido clínico** con la médica de la IPS. Está marcado en el código con `TODO: validar con la médica de la IPS`: los umbrales de `config/clinical_support.php`, los rangos de `config/vital_signs.php` y los textos de `EducationalContentSeeder` y de la guía de signos vitales.
- **Respaldo de la `APP_KEY`** separado del respaldo de la base de datos.
- `APP_DEBUG=false` y `APP_ENV=production`.
- **Configurar un `MAIL_MAILER` real.** Hoy es `log`: "olvidé mi contraseña" genera el enlace pero no envía ningún correo, solo lo escribe en `storage/logs/laravel.log`.
- **Definir `ADMIN_INITIAL_PASSWORD`.** Sin ella, `AdminUserSeeder` falla en vez de crear el admin con la contraseña de la demo — ver [Variables de entorno](#variables-de-entorno).
- **Revisar con la médica el contenido de `EducationalContentSeeder` antes del primer `--seed` en producción.** A diferencia de `DemoDataSeeder`, este corre en **todos** los entornos: lo que tenga cargado el día del primer `--seed` es lo que verán los pacientes.
