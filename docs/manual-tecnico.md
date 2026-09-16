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
| `JITSI_DOMAIN` | Dominio de la videollamada. Hoy `meet.jit.si`; al autoalojar, cambiar aquí y la política de permisos del navegador se ajusta sola |
| `PRIVACY_CONSENT_VERSION` | Versión del consentimiento de datos (Ley 1581) |
| `PRIVACY_TELECONSULTATION_CONSENT_VERSION` | Versión del consentimiento de teleconsulta (Res. 2654) |
| `PRIVACY_CONTACT_EMAIL` | Correo de habeas data que se muestra al titular |
| `TELECONSULTATION_JOIN_MINUTES_BEFORE` | Minutos antes de la cita en que se abre la sala |
| `TELECONSULTATION_JOIN_MINUTES_AFTER` | Minutos después en que la sala se cierra |

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
├── Enums/            Role, VitalSignType, EcntCategory
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
    └── ClinicalRules/            Motor de apoyo a decisiones

config/
├── privacy.php             Versiones de consentimiento
├── teleconsultation.php    Ventana de entrada a la sala
└── clinical_support.php    Umbrales operativos del motor de reglas

resources/js/
├── components/   ui/ (primitivas), forms/, charts.tsx
├── hooks/        use-offline-sync.tsx
├── lib/          offline-queue.ts
└── pages/        Un componente por ruta
```

## Pruebas

```bash
php artisan test                                   # todo
php artisan test tests/Feature/Autorizacion        # una carpeta
php artisan test --filter="contraste"              # por nombre
```

Las pruebas corren sobre SQLite en memoria con `RefreshDatabase`. Cubren, entre otras cosas, la matriz de autorización entre profesionales, el segundo factor completo, la idempotencia de la cola sin conexión, el motor de reglas clínicas caso por caso, y el contraste de color de ambos temas leyendo los tokens del CSS.

Al agregar una prueba que renderiza una página nueva, compilar antes con `npm run build`.

## Operación

**Cuentas.** Las crea el administrador desde `/admin/usuarios`; no hay autoservicio para el personal. Cada usuario activa su segundo factor de forma opcional desde `/settings/two-factor`.

**Si alguien pierde el teléfono del segundo factor**, entra con uno de sus ocho códigos de recuperación. Si tampoco los tiene, un administrador debe limpiar las columnas `two_factor_*` de ese usuario en la base de datos: no hay forma de recuperarlo desde la interfaz, y es deliberado.

**Vincular un paciente a su cuenta.** La ficha clínica (`patients`) y la cuenta (`users`) son cosas distintas: muchas fichas del programa corresponden a personas sin acceso a la plataforma. Para dar acceso, se crea el usuario con rol `paciente` y se asocia su `user_id` a la ficha.

**Auditoría.** `/admin/auditoria` permite filtrar entre accesos a historias clínicas y cambios sobre registros. Los cambios guardan qué campos se tocaron, nunca sus valores.

## Problemas frecuentes

| Síntoma | Causa y solución |
|---|---|
| `Unable to locate file in Vite manifest` | Falta compilar. `npm run build` |
| El QR del segundo factor no aparece | Falta la extensión `xmlwriter` de PHP |
| La videollamada carga en negro | Jitsi tarda; si falla del todo, el componente muestra un aviso con opción de reintentar |
| La sala dice que ya se cerró | La cita quedó fuera de la ventana. Ajustable con `TELECONSULTATION_JOIN_MINUTES_*` |
| Historias clínicas ilegibles | Se cambió la `APP_KEY`. Restaurar la original |
| Un médico recibe 403 sobre una cita | Correcto: solo gestiona las suyas. El padrón sí es compartido |

## Pendiente para producción

- **Desplegar en el VPS** (Ubuntu, Nginx + PHP-FPM + PostgreSQL).
- **Autoalojar Jitsi** y apuntar `JITSI_DOMAIN` al servidor propio. Hoy las salas viven en `meet.jit.si`, que es público: el nombre de sala es un UUID no adivinable, pero la conversación pasa por infraestructura de terceros.
- **Definir una Content-Security-Policy** una vez que el video sea de origen propio. No se puso antes porque una CSP mal ajustada rompe la videollamada sin mostrar ningún error.
- **Validar los umbrales clínicos** de `config/clinical_support.php` con la médica de la IPS.
- **Respaldo de la `APP_KEY`** separado del respaldo de la base de datos.
