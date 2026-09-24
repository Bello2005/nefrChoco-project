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
- **Respaldo de la `APP_KEY`** separado del respaldo de la base de datos (ver `docs/despliegue.md`, "Respaldos cifrados").
- **Llave `age` de los respaldos**, importación de los **catálogos oficiales**, `PRIVACY_TELECONSULTATION_CONSENT_VERSION=2026-09` e `INSTITUTION_*` en el `.env`. La lista completa de lo que depende de la IPS está en `docs/cumplimiento-normativo.md`.
- `APP_DEBUG=false` y `APP_ENV=production`.
- **Configurar un `MAIL_MAILER` real.** Hoy es `log`: "olvidé mi contraseña" genera el enlace pero no envía ningún correo, solo lo escribe en `storage/logs/laravel.log`.
- **Definir `ADMIN_INITIAL_PASSWORD`.** Sin ella, `AdminUserSeeder` falla en vez de crear el admin con la contraseña de la demo — ver [Variables de entorno](#variables-de-entorno).
- **Render no tiene disco persistente** (plan gratis): lo que la app guarde en `storage/app` desaparece en cada reinicio o despliegue. Hoy no se suben archivos de usuario, pero los catálogos oficiales (`storage/app/catalogos`) no pueden vivir allí: en Render hay que importarlos después de cada despliegue, o usar almacenamiento externo. En el VPS no pasa.
- **Revisar con la médica el contenido de `EducationalContentSeeder` antes del primer `--seed` en producción.** A diferencia de `DemoDataSeeder`, este corre en **todos** los entornos: lo que tenga cargado el día del primer `--seed` es lo que verán los pacientes.

## Frecuencia de seguimiento por riesgo (Res. 1644 de 2026, art. 19 par. 1)

- `patients.follow_up_risk_level` (cifrado, `App\Enums\FollowUpRiskLevel`: `bajo`, `medio`, `alto`). Lo asigna solo el médico (`PATCH medico/pacientes/{patient}/riesgo-seguimiento`); el cambio queda en la auditoría con el nombre del campo.
- `config/vital_signs.php` → `max_days_without_reading[nivel][signo]`: días máximos sin medición. **Todos en `null` hasta que la médica los valide**; mientras tanto `FollowUpScheduleService::isActive()` es falso y no hay vencidos ni recordatorios.
- `php artisan seguimiento:recordatorios` avisa a los pacientes con el control vencido (notificación por base de datos, una por día como máximo). Corre a diario a las 7:00 (America/Bogota) desde `routes/console.php`; en el VPS lo dispara `nefrochoco-scheduler.timer`, que instala `deploy/deploy.sh`.

## Catálogos oficiales

Tablas `code_systems` (un catálogo, con versión, fuente, SHA-256 del archivo, fecha y quién lo importó) y `codes` (código, nombre, texto de búsqueda, código padre, `active` y `extra` en JSON). Son datos públicos de referencia: **no se cifran**. Un código **nunca se borra**: si desaparece en una versión nueva queda `active = false`, porque hay registros históricos que lo usan.

### De dónde sale cada catálogo

**Nunca se escriben códigos a mano ni de memoria.** Los archivos los descarga una persona de la fuente oficial y **no se suben al repositorio**: se guardan en el servidor en `storage/app/catalogos/` (ignorada por git).

| Clave | Catálogo | Fuente oficial | Cada cuánto revisar |
|---|---|---|---|
| `cie10` | CIE-10 | [Tabla de referencia CIE10 de SISPRO](https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=CIE10) (se exporta a Excel) | Cuando MinSalud publique una actualización [CONFIRMAR periodicidad con la fuente] |
| `cie11` | CIE-11 (Res. 1442 de 2024; transición y codificación dual según la Res. 1657 de 2025) | Tablas de referencia de SISPRO (MinSalud) | Ídem |
| `cups` | CUPS | [Tabla de referencia CUPS de SISPRO](https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=CUPS) (se exporta a Excel) | Ídem (la clasificación se actualiza por resolución) |
| `divipola` | Municipios (DIVIPOLA) | [Excel de municipios del DANE](https://geoportal.dane.gov.co/descargas/divipola/DIVIPOLA_Municipios.xlsx) (hay que aplanarlo, ver abajo) | Cuando el DANE publique cambios |
| `eapb` | EAPB | [Tabla de referencia CodigoEAPByNit de SISPRO](https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=CodigoEAPByNit) (se exporta a Excel) | Ídem |
| `tipo_documento` | Tipos de documento | [ColombianPersonIdentifier](https://vulcano.ihcecol.gov.co/CodeSystem-ColombianPersonIdentifier.json), guía RDA 1.0.0 | Con cada versión de la guía |
| `identidad_genero` | Identidad de género | [ColombianGenderIdentity](https://vulcano.ihcecol.gov.co/CodeSystem-ColombianGenderIdentity.json) | Ídem |
| `etnia` | Pertenencia étnica | [ColombianEthnicGroup](https://vulcano.ihcecol.gov.co/CodeSystem-ColombianEthnicGroup.json) | Ídem |
| `discapacidad` | Discapacidad | [ColombianDisabilityClassification](https://vulcano.ihcecol.gov.co/CodeSystem-ColombianDisabilityClassification.json) | Ídem |
| `zona_residencia` | Zona de residencia | [ColombianResidenceZone](https://vulcano.ihcecol.gov.co/CodeSystem-ColombianResidenceZone.json) | Ídem |
| `ocupacion` | Ocupación (CIUO-88 A.C.) | [CIUO88AC](https://vulcano.ihcecol.gov.co/CodeSystem-CIUO88AC.json) | Ídem |
| `finalidad_consulta` | Finalidad de la consulta | [Tabla RIPSFinalidadConsultaVersion2 de SISPRO](https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=RIPSFinalidadConsultaVersion2) (CSV, no el JSON del IHCE: ver "Solo lo que aplica a consultas") | Cuando MinSalud la actualice |
| `causa_externa` | Causa externa | [Tabla RIPSCausaExternaVersion2 de SISPRO](https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=RIPSCausaExternaVersion2) (CSV) | Ídem |
| `tipo_diagnostico` | Tipo de diagnóstico principal | [RIPSTipoDiagnosticoPrincipalVersion2](https://vulcano.ihcecol.gov.co/CodeSystem-RIPSTipoDiagnosticoPrincipalVersion2.json) | Ídem |
| `tipo_usuario` | Tipo de usuario del RIPS (se usa como tipo de afiliación) | [Tabla de referencia RIPSTipoUsuarioVersion2 de SISPRO](https://web.sispro.gov.co/WebPublico/Consultas/ConsultarDetalleReferenciaBasica.aspx?Code=RIPSTipoUsuarioVersion2) (se exporta a Excel) | Ídem; confirmado en el anexo de la Res. 948 (campo `tipoUsuario`) |
| (otras) | CodeSystem y ValueSet del RDA | Página *Terminologías* de la guía (`vulcano.ihcecol.gov.co/terminologias.html`); cada uno se descarga como `CodeSystem-<id>.json` | Ídem |

Las URLs de CIE-10, CUPS, EAPB, DIVIPOLA y de los CodeSystem de la guía RDA se cotejaron con los archivos descargados el 24-sep-2026. Los CodeSystem del IHCE se importan como JSON, tal cual: `php artisan catalogos:importar etnia storage/app/catalogos/CodeSystem-ColombianEthnicGroup.json`. Antes de importarlos, compara el campo `count` con los códigos que trae el archivo: si no coinciden, la descarga quedó incompleta. Los publica la guía con el canonical `https://fhir.minsalud.gov.co/rda/CodeSystem/...` (queda en `extra.system` de cada código). Las demás quedan en **[CONFIRMAR]**: no se escribieron de memoria. Anota siempre la fuente en `--fuente` al importar, para que quede registrada.

**Códigos que agrupan:** en tipos de documento, `RNEC`, `CANCILLERIA`, `DIAN` y `OTROS` son la entidad que expide y no un tipo de documento. `leaves_only` en `config/catalogs.php` los deja inactivos: se guardan, pero no se pueden elegir. Ocupación (CIUO-88) también es jerárquica, pero ahí **cualquier nivel es válido**: el ValueSet `CIUO88ACCodes` de la guía RDA 1.0.0 (binding *required*) incluye los 562 códigos del CodeSystem. En el RDA la ocupación no va en el Patient: va como Observation por atención (perfil `PatientOccupationAtEncounterRDA`), dato que se usa en el prompt 10.

**Lista o buscador:** un catálogo con hasta `select_max` códigos activos (40) se envía completo con la página y se muestra como lista desplegable (`CodeCatalog::options()`): zona, etnia, identidad de género, discapacidad, tipo de documento, tipo de afiliación, finalidad, causa externa y tipo de diagnóstico. Los grandes (CIE-10, CUPS, DIVIPOLA, EAPB, ocupación) usan el buscador.

**Formato de las tablas de SISPRO** (cotejado con CIE-10, CUPS y EAPB): columnas `Tabla`, `Codigo`, `Nombre`, `Descripcion`, `Habilitado` (SI/NO) y varias `Extra_*`. `config/catalogs.php` fija `Codigo` y `Nombre` como código y nombre, y `Habilitado` como la columna que dice si el código se puede usar. Un código con `Habilitado=NO` se guarda **inactivo**: se sigue viendo en los registros viejos, pero no se puede elegir. Las demás columnas quedan en `extra`. En `RIPSTipoUsuarioVersion2` (julio de 2026) el código `14` dice "tr nsito", con un espacio en lugar de la "á": se importa tal como lo publica SISPRO. La tabla de EAPB trae correos de contacto de funcionarios (`Extra_VI:Email`): no se necesitan, así que se quita esa columna antes de importar.

**DIVIPOLA del DANE:** el Excel trae un título, el encabezado en dos filas y notas al final. Se deja una tabla plana con las columnas `codigo,nombre,codigo_departamento,departamento,tipo` (código del municipio de 5 dígitos, con el cero inicial). Revisa que ningún código haya quedado como número: en la versión de junio de 2026, `27493` (Nuevo Belén de Bajirá) venía así. `pacientes:revisar-identidad` usa la columna del departamento para preferir el municipio del Chocó cuando dos se llaman igual. Los nombres quedan como los publica el DANE, en mayúsculas (por ejemplo, `QUIBDÓ`).

Los recursos del paquete FHIR del IHCE se publican bajo licencia CC BY-NC-SA 4.0 y exigen esta atribución: *"Este es un bien público digital producido por HL7 Colombia, para el Ministerio de Salud y Protección Social"*.

### Cómo se importa

```bash
cd /var/www/nefrochoco-project
sudo -u www-data php artisan catalogos:importar cie10 storage/app/catalogos/cie10.csv \
  --version-catalogo="AAAA-MM" --fuente="SISPRO, tabla de referencia CIE-10, descargada el AAAA-MM-DD" \
  --por=admin@nefrochoco.co
```

- **CSV:** detecta el separador (`;`, `,`, tabulador o `|`) y convierte Windows-1252 a UTF-8. Busca las columnas del código y del nombre por su nombre (`codigo`/`code`, `nombre`/`descripcion`/`display`). Si el archivo oficial usa otros nombres, indícalos con `--columna-codigo="..."` y `--columna-nombre="..."`, o déjalos fijos en `config/catalogs.php` (`csv_columns`; confirmados para CIE-10 y CUPS, el resto en [CONFIRMAR]). Las demás columnas se guardan en `extra`.
- **Excel (XLSX):** no se lee directamente, porque no hay librería de Excel en el proyecto. Ábrelo y guárdalo como *CSV UTF-8 (delimitado por comas)*. Las tablas de SISPRO tienen una sola hoja, así que se exporta completa.
- **FHIR (JSON):** `CodeSystem` (con la jerarquía de `concept`) y `ValueSet` (`compose.include[].concept` o `expansion.contains`). Si no se pasa `--version-catalogo`, se toma la `version` del recurso.
- **Idempotente:** importar el mismo archivo dos veces no cambia nada. Una versión nueva actualiza los nombres, agrega los códigos nuevos y desactiva los que ya no vienen.
- La opción se llama `--version-catalogo` y no `--version`, porque Artisan reserva `--version` para mostrar la versión de Laravel.

### Búsqueda

`GET /catalogos/{sistema}/buscar?q=` (médico y admin, límite `catalogos` de 90 por minuto) devuelve los 20 primeros códigos **activos** por código o por nombre, sin importar tildes, mayúsculas ni el orden de las palabras ("crónica renal" encuentra "ENFERMEDAD RENAL CRONICA"). Hace falta porque los archivos oficiales no son parejos: la CIE-10 de SISPRO viene sin tildes y los CUPS con tildes. Para eso cada código guarda `search_text` (código y nombre sin tildes y en minúsculas, `Code::searchText()`), que se calcula al importar. En PostgreSQL la migración intenta crear la extensión `pg_trgm` y un índice GIN sobre `codes.search_text`; si el usuario de la base no tiene permiso, deja un índice normal. **Admin → Catálogos** muestra cuál quedó. En PostgreSQL 13 o superior `pg_trgm` es una extensión confiable, así que normalmente se crea sin ser superusuario.

## Identidad del paciente (Res. 866 de 2021)

- Campos nuevos en `patients`: `first_name`, `middle_name`, `first_surname`, `second_surname` y `municipality_code` (sin cifrar); `gender_identity`, `ethnicity`, `disability`, `occupation`, `residence_zone`, `eapb_code` y `affiliation_type` (cifrados, con `LogsChangedFields`); `identity_review_pending` e `identity_review_reasons`.
- `full_name` lo calcula `PatientService` a partir de los nombres. `municipality` se toma del catálogo DIVIPOLA cuando se elige un código.
- Validación: `config/catalogs.php` → `patient_fields` dice contra qué catálogo se valida cada campo. Si el catálogo no está importado, o la clave es `null`, el campo se acepta como texto. El tipo de afiliación usa la tabla `RIPSTipoUsuarioVersion2` de SISPRO, porque la guía RDA 1.0.0 no trae ese CodeSystem Es la tabla que pide el campo `tipoUsuario` del anexo de la Res. 948.
- Después de importar `tipo_documento` o `divipola`, corre `php artisan pacientes:revisar-identidad` para mapear las fichas viejas. Nunca pisa lo que ya completó una persona, y el marcador de nombres solo se quita guardando la ficha.
- Sexo biológico → FHIR: `config/catalogs.php` → `biological_sex_fhir`, tomado del ValueSet `IHCE-SexoBiologico-VS` del paquete oficial.

## Profesionales (RETHUS) e institución (REPS)

- `practitioner_profiles` (1:1 con usuarios de rol `medico`): `document_type`, `document_number` (sin cifrar, único), `profession`, `professional_registration`, `specialty` y `rethus_note` (cifrados, con `LogsChangedFields`); `rethus_verified_at` y `rethus_verified_by`. Se edita en Admin → Usuarios. La verificación RETHUS la hace una persona en la consulta pública de ReTHUS: **no hay consultas automáticas ni scraping**. Si cambia el documento o el registro, la verificación se borra.
- Institución: `config/nefrochoco.php` → `institution`, leído de `INSTITUTION_NAME`, `INSTITUTION_NIT`, `INSTITUTION_REPS_CODE`, `INSTITUTION_SITE_CODE` e `INSTITUTION_MUNICIPALITY_CODE` (DIVIPOLA). **Todos vacíos por defecto, [CONFIRMAR] con la IPS**: nunca se inventan. Un valor vacío cuenta como faltante. Después de cambiarlos en el `.env`, corre `php artisan config:cache`.

## Registro estructurado de la atención

- La atención es la cita. Tablas nuevas: `appointment_diagnoses` (CIE-10, CIE-11 opcional, `role` principal/relacionado, tipo de diagnóstico, `replaces_id` para correcciones), `appointment_procedures` (CUPS y cantidad), `appointment_medications` (texto; el código queda nullable hasta tener el catálogo oficial), `patient_allergies` (con la marca `no_known_allergies`) y `clinical_history_diagnoses` (CIE-10 opcional por entrada de historia). En `appointments`: `consultation_reason`, `purpose` y `external_cause`. Todo lo clínico va cifrado, con autor, `LogsChangedFields` y llaves restrict con índice. `role` va en claro porque lo cuentan los reportes.
- Los códigos se validan contra el catálogo activo **antes** de cifrarse (regla `ActiveCode`), porque cifrados ya no se pueden comparar.
- `AttentionRecordService` guarda el registro dentro de la misma transacción y bloqueo del cierre (teleconsulta) o de la actualización a `completada` (presencial). Después la cita no se edita (`AppointmentPolicy::update`) y no hay rutas para editar el registro.
- Correcciones: una aclaración puede traer `corrected_diagnosis`, que crea una fila nueva con `replaces_id` y el mismo rol. `currentDiagnoses()` devuelve los que ninguna corrección reemplazó.
- **Obligatoriedad:** el diagnóstico principal se exige cuando el catálogo `cie10` está importado. Sin él, la atención se cierra igual (con aviso) para no bloquear la atención clínica. **Importa la CIE-10 al desplegar** para que la regla aplique.
- CIE-11: el campo solo se acepta si el catálogo `cie11` está importado. No hay equivalencias automáticas CIE-10 → CIE-11: si el Ministerio publica una tabla oficial, se importa como catálogo propio.
- Tipo de diagnóstico, finalidad y causa externa: `config/catalogs.php` → `attention_fields`. El Documento técnico 1 de la Res. 948 de 2026 (versión 001, 4-jun-2026) confirma las tablas: `RIPSTipoDiagnosticoPrincipalVersion2`, `RIPSFinalidadConsultaVersion2` y `RIPSCausaExternaVersion2`. Sin catálogo importado se aceptan como texto.
- **Solo lo que aplica a consultas:** el anexo de la Res. 948 acepta en consultas solo la finalidad y la causa que la tabla de SISPRO marca con `Extra_I:Consultas=SI`. Por eso esas dos se importan del CSV de SISPRO y `active_column` lleva dos condiciones (habilitado **y** para consultas). Con las tablas del 24-sep-2026: 17 de las 34 finalidades aplican a consultas; de la causa externa aplican las 29. Las demás se guardan inactivas. Los códigos son los mismos del JSON del IHCE. La tabla de finalidad trae además columnas sin nombre (`Extra_VI` a `Extra_VIII`) que parecen sexo y edades, pero no se usan: sin su definición oficial no se aplican como regla.
- **Zona de residencia, códigos al revés:** `ColombianResidenceZone` del IHCE usa `01` Urbana y `02` Rural. La tabla `ZonaVersion2` de SISPRO, la del RIPS, usa `01` Rural y `02` Urbano. La plataforma guarda el código del IHCE, que es el que va en el RDA. Si otro sistema toma la zona para el RIPS, debe traducirla, no copiar el número.
- Las requests de cierre (`UpdateTeleconsultationRequest`, `UpdateAppointmentRequest`, `StoreTeleconsultationClarificationRequest`) autorizan **antes** de validar.

## Preparación para interoperar

`App\Services\InteroperabilityReadiness` tiene una regla por requisito: `missingForPatient`, `missingForPractitioner`, `missingForInstitution`, `missingForAppointment`, y `missingForDocument(Appointment)`, que las junta por dónde se completa. Es la que usarán la generación del RDA y la de RIPS para explicar por qué no pueden generar un documento. `summary()` alimenta Admin → Preparación para interoperar. Recorre en PHP porque varios datos van cifrados y no se pueden filtrar en SQL. Nunca devuelve contenido clínico.

## Custodia de la historia: sin borrados en cascada

Las llaves foráneas de los datos clínicos son `restrict` (migración `restrict_deletes_on_clinical_foreign_keys`): `appointments.doctor_id` y `patient_id`, `teleconsultations.appointment_id`, `vital_signs.patient_id` y `recorded_by`, `clinical_histories.patient_id` y `clinical_forms.patient_id` y `recorded_by`, y todas las tablas nuevas del registro de la atención. **La base rechaza un borrado mientras haya historia colgando**, aunque venga de la consola o de código nuevo. Por eso la aplicación no ofrece esos borrados: las cuentas se desactivan, las citas se cancelan y las fichas solo se borran de forma lógica. `patients.user_id` y `sus_responses.user_id` quedaron como estaban.

## Autor de cada entrada de historia

`clinical_histories.author_id` (nullable solo por las entradas viejas, restrict, con índice) lo asocia `ClinicalHistoryService::create` con el usuario autenticado; queda fuera de `$fillable` para que nadie firme por otro. La migración `backfill_author_id_on_clinical_histories` recuperó el autor de las entradas viejas desde el evento `created` de `activity_log`, saltando los autores que ya no existen (el `EXISTS` evita que la llave nueva haga fallar el despliegue). Su `down()` no deshace nada, porque el autor recuperado es un dato verdadero.

