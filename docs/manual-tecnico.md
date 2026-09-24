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
| `PRIVACY_TELECONSULTATION_CONSENT_VERSION` | Versión del consentimiento de teleconsulta (Res. 1644 de 2026) |
| `PRIVACY_CONTACT_EMAIL` | Correo de habeas data que se muestra al titular |
| `TELECONSULTATION_JOIN_MINUTES_BEFORE` | Minutos antes de la cita en que se abre la sala |
| `TELECONSULTATION_JOIN_MINUTES_AFTER` | Minutos después en que la sala se cierra |
| `CONNECTION_CHECK_VIDEO_MIN_KBPS` / `CONNECTION_CHECK_VIDEO_MAX_LATENCY_MS` | Umbrales de "Probar mi conexión" para video. **[CONFIRMAR]** contra los requisitos de Jitsi y las pruebas de `docs/protocolo-baja-conectividad.md` |
| `CONNECTION_CHECK_AUDIO_MIN_KBPS` / `CONNECTION_CHECK_AUDIO_MAX_LATENCY_MS` | Lo mismo para solo audio. **[CONFIRMAR]** |
| `ALLOWED_EMAIL_DOMAIN` | Dominio institucional exigido al personal (`admin` y `medico`) al crear o editar su cuenta. Los pacientes no tienen restricción: usan su correo personal |
| `EDUCATIONAL_MAX_BODY_CHARACTERS` | Tope del cuerpo de un contenido educativo. No es un límite de base de datos sino de conexión: el material se descarga entero al teléfono del paciente |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | Ambos en `es`. El valor por defecto de `config/app.php` también es `es`, para que un entorno sin `.env` no revierta los mensajes a inglés |
| `ADMIN_INITIAL_PASSWORD` | Contraseña del admin que crea `AdminUserSeeder`. Solo se usa fuera de `local`/`testing`: sin ella, el seeder falla en vez de crear la cuenta con la contraseña de la demo |

**Subir una versión de consentimiento tiene efecto inmediato**: quienes aceptaron la anterior vuelven a ver la pantalla. Es el mecanismo previsto por la ley, no un efecto secundario.

**Ojo: el valor del `.env` manda sobre el de `config/privacy.php`.** Con los textos de la Res. 1644 de 2026 la versión del consentimiento de teleconsulta pasó a `2026-09`. Un servidor cuyo `.env` todavía diga `PRIVACY_TELECONSULTATION_CONSENT_VERSION=2026-01` sigue usando la versión vieja y nadie ve el texto nuevo: hay que cambiarla en el `.env` y correr `php artisan config:cache`.

**Retiro del consentimiento de teleconsulta** (Res. 1644 de 2026, art. 7): el paciente lo retira desde *Mis consentimientos* (`paciente.mis-consentimientos.*`). Se guarda `patients.teleconsultation_consent_revoked_at` sin borrar la aceptación (esa fecha respalda las teleconsultas ya hechas), y la auditoría registra el evento `teleconsulta_revocada` sin propiedades. `Patient::hasCurrentTeleconsultationConsent()` exige que no esté retirado, así que la sala vuelve a pedir la autorización; aceptarla de nuevo limpia el retiro. La autorización de datos (Ley 1581) no se retira con un botón: la historia se conserva por la Res. 839 de 2017, y el titular lo pide por `PRIVACY_CONTACT_EMAIL`.

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
| Un recurso externo nuevo no carga (fuente, script, iframe) | La Content-Security-Policy de `SecurityHeaders::contentSecurityPolicy()` solo deja pasar los orígenes ya listados ahí; hay que agregar el nuevo. En local no pasa: la CSP se salta a propósito porque bloquearía el servidor de Vite |

## Pendiente para producción

- **Desplegar en el VPS** (Ubuntu, Nginx + PHP-FPM + PostgreSQL).
- **Apuntar `JITSI_DOMAIN` en Render** al Jitsi ya autoalojado (`jitsi.bello.works`, versión `stable-11248`). Hoy producción sigue en `meet.jit.si`, que es público y de terceros.
- **Sumar autenticación JWT a la sala autoalojada.** Hoy lo único que impide entrar a quien no sea médico o paciente es que el nombre de sala es un UUID no adivinable; con JWT, solo el médico y el paciente de la cita podrían hacerlo de verdad.
- **Validar el contenido clínico** con la médica de la IPS. Está marcado en el código con `TODO: validar con la médica de la IPS`: los umbrales de `config/clinical_support.php`, los rangos de `config/vital_signs.php` y los textos de `EducationalContentSeeder` y de la guía de signos vitales.
- **Respaldo de la `APP_KEY`** separado del respaldo de la base de datos.
- `APP_DEBUG=false` y `APP_ENV=production`.
- **Configurar un `MAIL_MAILER` real.** Hoy es `log`: "olvidé mi contraseña" genera el enlace pero no envía ningún correo, solo lo escribe en `storage/logs/laravel.log`.
- **Definir `ADMIN_INITIAL_PASSWORD`.** Sin ella, `AdminUserSeeder` falla en vez de crear el admin con la contraseña de la demo — ver [Variables de entorno](#variables-de-entorno).
- **Revisar con la médica el contenido de `EducationalContentSeeder` antes del primer `--seed` en producción.** A diferencia de `DemoDataSeeder`, este corre en **todos** los entornos: lo que tenga cargado el día del primer `--seed` es lo que verán los pacientes.

## Frecuencia de seguimiento por riesgo (Res. 1644 de 2026, art. 19 par. 1)

- `patients.follow_up_risk_level` (cifrado, `App\Enums\FollowUpRiskLevel`: `bajo`, `medio`, `alto`). Lo asigna solo el médico (`PATCH medico/pacientes/{patient}/riesgo-seguimiento`); el cambio queda en la auditoría con el nombre del campo.
- `config/vital_signs.php` → `max_days_without_reading[nivel][signo]`: días máximos sin medición. **Todos en `null` hasta que la médica los valide**; mientras tanto `FollowUpScheduleService::isActive()` es falso y no hay vencidos ni recordatorios.
- `php artisan seguimiento:recordatorios` avisa a los pacientes con el control vencido (notificación por base de datos, una por día como máximo). Corre a diario a las 7:00 (America/Bogota) desde `routes/console.php`; en el VPS lo dispara `nefrochoco-scheduler.timer`, que instala `deploy/deploy.sh`.

## Catálogos oficiales

Tablas `code_systems` (un catálogo, con versión, fuente, SHA-256 del archivo, fecha y quién lo importó) y `codes` (código, nombre, código padre, `active` y `extra` en JSON). Son datos públicos de referencia: **no se cifran**. Un código **nunca se borra**: si desaparece en una versión nueva queda `active = false`, porque hay registros históricos que lo usan.

### De dónde sale cada catálogo

**Nunca se escriben códigos a mano ni de memoria.** Los archivos los descarga una persona de la fuente oficial y **no se suben al repositorio**: se guardan en el servidor en `storage/app/catalogos/` (ignorada por git).

| Clave | Catálogo | Fuente oficial | Cada cuánto revisar |
|---|---|---|---|
| `cie10` | CIE-10 | Tablas de referencia de SISPRO (MinSalud) | Cuando MinSalud publique una actualización [CONFIRMAR periodicidad con la fuente] |
| `cie11` | CIE-11 (Res. 1442 de 2024; transición y codificación dual según la Res. 1657 de 2025) | Tablas de referencia de SISPRO (MinSalud) | Ídem |
| `cups` | CUPS | Tablas de referencia de SISPRO (MinSalud) | Ídem (la clasificación se actualiza por resolución) |
| `divipola` | Municipios (DIVIPOLA) | DANE | Cuando el DANE publique cambios |
| `eapb` | EAPB | Tablas de referencia de SISPRO (MinSalud) | Ídem |
| `tipo_documento` | Tipos de documento | Tablas de referencia de SISPRO o CodeSystem del paquete FHIR del IHCE | Con cada versión de la guía |
| (otras) | CodeSystem y ValueSet del RDA | Paquete FHIR `package.tgz` de la guía de implementación del IHCE | Con cada versión de la guía |

Las URLs exactas de descarga quedan en **[CONFIRMAR]**: no se escribieron de memoria. Anótalas en `--fuente` al importar, para que queden registradas.

Los recursos del paquete FHIR del IHCE se publican bajo licencia CC BY-NC-SA 4.0 y exigen esta atribución: *"Este es un bien público digital producido por HL7 Colombia, para el Ministerio de Salud y Protección Social"*.

### Cómo se importa

```bash
cd /var/www/nefrochoco-project
sudo -u www-data php artisan catalogos:importar cie10 storage/app/catalogos/cie10.csv \
  --version-catalogo="AAAA-MM" --fuente="SISPRO, tabla de referencia CIE-10, descargada el AAAA-MM-DD" \
  --por=admin@nefrochoco.co
```

- **CSV:** detecta el separador (`;`, `,`, tabulador o `|`) y convierte Windows-1252 a UTF-8. Busca las columnas del código y del nombre por su nombre (`codigo`/`code`, `nombre`/`descripcion`/`display`). Si el archivo oficial usa otros nombres, indícalos con `--columna-codigo="..."` y `--columna-nombre="..."`, o déjalos fijos en `config/catalogs.php` (`csv_columns`, hoy en [CONFIRMAR]). Las demás columnas se guardan en `extra`.
- **Excel (XLSX):** no se lee directamente, porque no hay librería de Excel en el proyecto. Ábrelo y guárdalo como *CSV UTF-8*.
- **FHIR (JSON):** `CodeSystem` (con la jerarquía de `concept`) y `ValueSet` (`compose.include[].concept` o `expansion.contains`). Si no se pasa `--version-catalogo`, se toma la `version` del recurso.
- **Idempotente:** importar el mismo archivo dos veces no cambia nada. Una versión nueva actualiza los nombres, agrega los códigos nuevos y desactiva los que ya no vienen.
- La opción se llama `--version-catalogo` y no `--version`, porque Artisan reserva `--version` para mostrar la versión de Laravel.

### Búsqueda

`GET /catalogos/{sistema}/buscar?q=` (médico y admin, límite `catalogos` de 90 por minuto) devuelve los 20 primeros códigos **activos** por código o por nombre. En PostgreSQL la migración intenta crear la extensión `pg_trgm` y un índice GIN sobre `codes.display`; si el usuario de la base no tiene permiso, deja un índice normal y la búsqueda usa `ILIKE`. **Admin → Catálogos** muestra cuál quedó. En PostgreSQL 13 o superior `pg_trgm` es una extensión confiable, así que normalmente se crea sin ser superusuario.

## Identidad del paciente (Res. 866 de 2021)

- Campos nuevos en `patients`: `first_name`, `middle_name`, `first_surname`, `second_surname` y `municipality_code` (sin cifrar); `gender_identity`, `ethnicity`, `disability`, `occupation`, `residence_zone`, `eapb_code` y `affiliation_type` (cifrados, con `LogsChangedFields`); `identity_review_pending` e `identity_review_reasons`.
- `full_name` lo calcula `PatientService` a partir de los nombres. `municipality` se toma del catálogo DIVIPOLA cuando se elige un código.
- Validación: `config/catalogs.php` → `patient_fields` dice contra qué catálogo se valida cada campo. Si el catálogo no está importado, o la clave es `null` (hoy: identidad de género, etnia, discapacidad, ocupación, zona y tipo de afiliación, pendientes de confirmar contra el paquete FHIR del IHCE), el campo se acepta como texto.
- Después de importar `tipo_documento` o `divipola`, corre `php artisan pacientes:revisar-identidad` para mapear las fichas viejas. Nunca pisa lo que ya completó una persona, y el marcador de nombres solo se quita guardando la ficha.
- Sexo biológico → FHIR: `config/catalogs.php` → `biological_sex_fhir`, tomado del ValueSet `IHCE-SexoBiologico-VS` del paquete oficial.
