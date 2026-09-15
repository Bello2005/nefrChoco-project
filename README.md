# IPS NefroChocó · Plataforma de Telemedicina

Plataforma de telemedicina construida para **IPS NefroChocó**, orientada a la promoción, prevención y seguimiento de **enfermedades crónicas no transmisibles** (ECNT: diabetes, hipertensión arterial, enfermedad renal crónica) en zonas rurales del departamento del Chocó, Colombia — un contexto de **conectividad intermitente y dispersión poblacional alta**.

No es un producto multi-tenant: es un desarrollo a medida para un solo cliente institucional.

---

## Tabla de contenidos

- [Qué resuelve](#qué-resuelve)
- [Stack técnico](#stack-técnico)
- [Roles del sistema](#roles-del-sistema)
- [Módulos](#módulos)
- [Arquitectura](#arquitectura)
- [Decisiones de diseño que importan](#decisiones-de-diseño-que-importan)
- [Cumplimiento — Ley 1581 de 2012](#cumplimiento--ley-1581-de-2012)
- [Instalación local](#instalación-local)
- [Usuarios de demostración](#usuarios-de-demostración)
- [Tests](#tests)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Historial de desarrollo](#historial-de-desarrollo)
- [Pendiente para producción](#pendiente-para-producción)

---

## Qué resuelve

| Necesidad | Cómo se resuelve |
|---|---|
| El paciente vive lejos y la señal falla | La app funciona offline: registra signos vitales sin conexión y los sincroniza sola cuando vuelve la señal |
| El médico necesita ver todo su programa de un vistazo | Dashboards con métricas reales, alertas de telemonitoreo y distribución de diagnósticos ECNT |
| Hay que tamizar riesgo, no solo registrar datos | Formularios clínicos con motor de puntuación (FINDRISC, Morisky-Green) e interpretación automática |
| La atención no siempre puede ser presencial | Teleconsulta por videollamada con sala única y privada por cita |
| Los datos de salud están protegidos por ley | Cifrado en reposo, consentimiento informado versionado y auditoría de cada acceso a una historia clínica |

## Stack técnico

- **Backend:** Laravel 12 (PHP ^8.2), PostgreSQL
- **Frontend:** React 19 + TypeScript, vía [Inertia.js](https://inertiajs.com/) — sin API REST separada, el backend renderiza componentes React directamente
- **Estilos:** Tailwind CSS 4, sistema de diseño propio (tipografía Plus Jakarta Sans)
- **Roles y permisos:** [spatie/laravel-permission](https://spatie.be/docs/laravel-permission)
- **Auditoría:** [spatie/laravel-activitylog](https://spatie.be/docs/laravel-activitylog)
- **Videollamada:** Jitsi Meet External API (sala única no adivinable por cita)
- **Offline:** Service Worker propio + cola de sincronización en IndexedDB
- **Tests:** [Pest](https://pestphp.com/)

## Roles del sistema

- **`admin`** — gestión de usuarios, contenido educativo, auditoría
- **`medico`** — pacientes, historia clínica, citas, teleconsulta, formularios clínicos, telemonitoreo
- **`paciente`** — sus propias citas, su historia clínica, autorreporte de signos vitales, material educativo

## Módulos

| Módulo | Estado |
|---|---|
| Autenticación y gestión de usuarios | ✅ Funcional |
| Pacientes e historia clínica | ✅ Funcional |
| Agenda de citas + calendario | ✅ Funcional |
| Teleconsulta (Jitsi) con cierre y notas clínicas | ✅ Funcional |
| Formularios clínicos con motor de puntuación | ✅ Funcional |
| Telemonitoreo de signos vitales | ✅ Funcional |
| Módulo educativo | ✅ Funcional |
| Auditoría de accesos y cambios | ✅ Funcional |
| Modo sin conexión (PWA + cola offline) | ✅ Funcional |
| Notificaciones (alertas clínicas, citas) | ✅ Funcional |
| Cifrado en reposo + consentimiento informado | ✅ Funcional |
| Autenticación de dos factores, pagos, IA/analítica predictiva | ❌ Fuera de alcance |

## Arquitectura

Flujo de una petición de escritura:

```
Request HTTP → Form Request (valida) → Controller (delgado) → Service (lógica) → Model
```

```
app/
├── Enums/            Role, VitalSignType, EcntCategory — valores válidos + etiquetas + reglas clínicas
├── Http/
│   ├── Controllers/   Delgados: reciben, delegan a un Service, responden
│   ├── Requests/       Toda la validación de entrada vive aquí, no en el controlador
│   └── Middleware/      EnsureDataConsent (bloquea al paciente sin autorización vigente)
├── Models/            Eloquent + relaciones + casts de cifrado
├── Notifications/      Solo por base de datos (sin correo/push — en zonas rurales tardarían horas)
├── Policies/           ClinicalHistoryPolicy: el único punto donde la autorización depende del dato
├── Services/           Toda la lógica de negocio
└── Support/
    └── ClinicalFormCatalog.php   Instrumentos clínicos (preguntas, puntajes, umbrales) como datos versionados en código
```

```
resources/js/
├── components/
│   ├── ui/        Primitivas (botón, input, tabla, badge…)
│   ├── forms/      Campos compuestos por dominio
│   └── charts.tsx  Line/bar/donut chart en SVG puro, sin librería — por el peso en redes lentas
├── hooks/
│   └── use-offline-sync.tsx   Cola de sincronización + estado de conexión
├── lib/
│   └── offline-queue.ts       Persistencia en IndexedDB de mediciones pendientes de enviar
└── pages/          Un componente por ruta, organizado igual que routes/*.php
```

## Decisiones de diseño que importan

**Qué se cifró y qué no.** `full_name`, `document_number` y `municipality` se dejaron **sin cifrar a propósito**: cada cifrado usa un IV distinto, así que dos veces el mismo valor producen textos diferentes en la base de datos. Cifrarlos habría roto el índice único del documento y el buscador de pacientes. Todo el contenido clínico (diagnóstico, antecedentes, alergias, medicación, notas de teleconsulta) y los datos de contacto sí van cifrados.

**Idempotencia en la cola offline.** Cada medición registrada sin conexión lleva un `client_uuid` generado en el dispositivo. Si el servidor ya la guardó pero la respuesta se perdió antes de llegar al dispositivo, un reintento de la cola no la duplica ni vuelve a alertar al médico (`firstOrCreate` sobre esa clave).

**Cerrar una teleconsulta completa la cita.** Son el mismo hecho asistencial: `TeleconsultationService::complete()` exige notas clínicas y marca la cita como completada en la misma operación, para que la agenda nunca reporte una atención que ya ocurrió como pendiente.

**Formularios clínicos como datos, no como configuración.** `ClinicalFormCatalog` define instrumentos como FINDRISC o Morisky-Green (preguntas, opciones, puntajes, umbrales de riesgo) en código versionado, no en base de datos — son instrumentos clínicos validados que cambian con evidencia, no configuración de cliente.

## Cumplimiento — Ley 1581 de 2012

- **Cifrado en reposo** de campos con datos personales y de salud sensibles
- **Consentimiento informado**: el paciente no puede usar la plataforma hasta autorizar el tratamiento de sus datos; queda registrada la versión de la política que aceptó y la fecha
- **Auditoría de accesos**: cada consulta a una historia clínica (no solo cada cambio) queda registrada con quién, cuándo y desde qué IP
- **Control de acceso estricto por rol**, reforzado con una policy explícita para el caso donde el rol no basta (un paciente viendo su propia historia)

## Instalación local

Requisitos: PHP ^8.2, Composer, Node 20+, PostgreSQL.

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Crear la base de datos en PostgreSQL (ajusta credenciales en .env)
createdb teleproject

php artisan migrate --seed
```

```bash
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

La app queda disponible en `http://localhost:8000`.

## Usuarios de demostración

El seeder (`DemoDataSeeder`, solo corre en entorno `local`) crea pacientes, citas y mediciones de ejemplo del Chocó. Todos con contraseña `password`:

| Correo | Rol |
|---|---|
| `admin@nefrochoco.test` | Administrador |
| `ana.mosquera@nefrochoco.test` | Médico |
| `juan.perea@nefrochoco.test` | Paciente (pasa primero por la pantalla de consentimiento) |

## Tests

```bash
php artisan test
```

90 pruebas con Pest, incluyendo casos explícitos de seguridad: un paciente no puede ver la historia clínica de otro, un reenvío de la cola offline no duplica una medición ni repite una alerta, el personal de la IPS no pasa por el muro de consentimiento.

## Estructura del proyecto

```
routes/
├── web.php        Rutas compartidas (dashboard genérico, historia clínica, notificaciones)
├── admin.php      Prefijo /admin, middleware role:admin
├── medico.php     Prefijo /medico, middleware role:medico
└── paciente.php   Prefijo /paciente, middleware role:paciente + EnsureDataConsent
```

## Historial de desarrollo

El historial de commits está organizado por sprint, no por orden cronológico de edición de archivos:

```
Sprint 0   Esqueleto funcional: auth, roles, pacientes, historia clínica, citas, teleconsulta
Sprint 1   Rediseño visual completo + módulos clínicos reales (formularios, telemonitoreo, educativo, auditoría)
Sprint 2   Modo sin conexión: PWA, service worker, cola de sincronización con idempotencia
Sprint 3   Cierre de teleconsulta, notificaciones, exportación de historia clínica
Sprint 4   Cifrado en reposo y consentimiento informado (Ley 1581)
```

## Pendiente para producción

- Desplegar en el VPS (Hostinger / Ubuntu, PHP-FPM + Nginx + PostgreSQL) — las migraciones y `.env.example` ya están pensados para ese entorno
- Apuntar Jitsi a un servidor autoalojado en el VPS (por ahora usa `meet.jit.si` como placeholder, marcado con `TODO` en el código)
- Autenticación de dos factores, notificaciones push y pasarela de pagos quedaron fuera de alcance por decisión explícita del cliente
