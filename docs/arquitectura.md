# Arquitectura — IPS NefroChocó

Documento técnico de la plataforma de telemedicina para el seguimiento de enfermedades crónicas no transmisibles (ECNT) en el departamento del Chocó.

- [Visión general](#visión-general)
- [Zonas y roles](#zonas-y-roles)
- [Flujo de una petición](#flujo-de-una-petición)
- [Modelo de datos](#modelo-de-datos)
- [Autorización](#autorización)
- [Cifrado en reposo y auditoría](#cifrado-en-reposo-y-auditoría)
- [Modo sin conexión](#modo-sin-conexión)
- [Apoyo a decisiones clínicas](#apoyo-a-decisiones-clínicas)
- [Decisiones de diseño y sus motivos](#decisiones-de-diseño-y-sus-motivos)

---

## Visión general

Monolito Laravel con frontend React servido por Inertia. **No hay API REST separada**: los controladores devuelven respuestas de Inertia y React recibe las propiedades ya resueltas. Esa decisión elimina una capa entera de serialización, autenticación de API y versionado, que en un proyecto de un solo cliente institucional solo habría sumado superficie que mantener.

| Capa | Tecnología |
|---|---|
| Backend | Laravel 12 (PHP ^8.2) |
| Base de datos | PostgreSQL (único motor soportado) |
| Frontend | React 19 + TypeScript vía Inertia.js 2 |
| Estilos | Tailwind CSS 4 con sistema de tokens propio |
| Roles | spatie/laravel-permission |
| Auditoría | spatie/laravel-activitylog + trait propio |
| Video | Jitsi Meet External API |
| Segundo factor | TOTP (pragmarx/google2fa + bacon/bacon-qr-code) |
| Pruebas | Pest |

## Zonas y roles

```mermaid
flowchart TD
    Login["/login"] --> Dash{"Redirección<br/>por rol"}

    Dash -->|admin| Admin["/admin<br/>usuarios · padrón · contenido · auditoría"]
    Dash -->|medico| Medico["/medico<br/>pacientes · agenda · teleconsulta<br/>formularios · telemonitoreo"]
    Dash -->|paciente| Pac["/paciente<br/>mis citas · historia · signos vitales<br/>educación · sala de teleconsulta"]

    Pac -.->|sin autorizar datos| Consent["Muro de consentimiento<br/>Ley 1581"]
    Pac -.->|al entrar a la sala| ConsentTele["Consentimiento de teleconsulta<br/>Resolución 2654"]

    Login -.->|con doble factor| Reto["Desafío TOTP"]
    Reto --> Dash
```

Cada zona es un grupo de rutas con su propio middleware: `auth`, `role:<rol>` y un límite de peticiones por usuario. La zona del paciente añade `EnsureDataConsent`.

## Flujo de una petición

```mermaid
sequenceDiagram
    participant N as Navegador
    participant M as Middleware
    participant FR as Form Request
    participant C as Controlador
    participant S as Servicio
    participant Mo as Modelo

    N->>M: POST /medico/pacientes
    M->>M: auth · role · throttle
    M->>FR: valida la entrada
    FR-->>N: 422 con errores si no pasa
    FR->>C: datos ya validados
    C->>S: delega la lógica
    S->>Mo: persiste
    Mo->>Mo: cifra campos sensibles
    Mo-->>S: registra el cambio en auditoría
    S-->>C: modelo
    C-->>N: redirección Inertia + flash
```

Los controladores son delgados a propósito: reciben, autorizan, delegan y responden. La lógica vive en `app/Services`, y toda la validación en `app/Http/Requests`.

## Modelo de datos

```mermaid
erDiagram
    USERS ||--o| PATIENTS : "cuenta del titular"
    USERS ||--o{ APPOINTMENTS : "atiende como médico"
    PATIENTS ||--o{ APPOINTMENTS : "recibe"
    PATIENTS ||--o{ CLINICAL_HISTORIES : "acumula"
    PATIENTS ||--o{ CLINICAL_FORMS : "se le aplican"
    PATIENTS ||--o{ VITAL_SIGNS : "registra"
    APPOINTMENTS ||--o| TELECONSULTATIONS : "genera sala"

    USERS {
        id bigint PK
        name string
        email string UK
        password string
        two_factor_secret text "cifrado"
        two_factor_recovery_codes text "cifrado"
        two_factor_confirmed_at timestamp
    }

    PATIENTS {
        id bigint PK
        user_id bigint FK "opcional"
        full_name string "sin cifrar: se busca"
        document_number string UK "sin cifrar: índice único"
        municipality string "sin cifrar: se agrupa"
        birth_date date
        phone text "cifrado"
        emergency_contact_name text "cifrado"
        emergency_contact_phone text "cifrado"
        consent_accepted_at timestamp "Ley 1581"
        consent_version string
        teleconsultation_consent_accepted_at timestamp "Res. 2654"
        teleconsultation_consent_version string
    }

    CLINICAL_HISTORIES {
        id bigint PK
        patient_id bigint FK
        ecnt_diagnosis text "cifrado"
        medical_history text "cifrado"
        allergies text "cifrado"
        current_medication text "cifrado"
    }

    APPOINTMENTS {
        id bigint PK
        patient_id bigint FK
        doctor_id bigint FK
        scheduled_at datetime
        status string "programada|completada|cancelada|no_asistio"
        type string "presencial|teleconsulta"
    }

    TELECONSULTATIONS {
        id bigint PK
        appointment_id bigint FK "único: una sala por cita"
        room_name string UK "uuid no adivinable"
        status string
        notes text "cifrado"
    }

    CLINICAL_FORMS {
        id bigint PK
        patient_id bigint FK
        recorded_by bigint FK
        form_type string
        answers jsonb
        score smallint "resultado congelado"
        risk_level string
    }

    VITAL_SIGNS {
        id bigint PK
        patient_id bigint FK
        recorded_by bigint FK
        client_uuid uuid UK "idempotencia offline"
        type string
        value decimal
        unit string
        recorded_at datetime
        notes text "cifrado"
    }
```

Los índices de consulta están en una migración aparte. PostgreSQL no indexa claves foráneas por su cuenta: sin ellos, cada panel resolvía recorriendo la tabla completa.

## Autorización

El middleware de rol responde *qué rol tiene* el usuario; las policies responden *si el recurso es suyo*. Hacen falta las dos.

| Recurso | admin | medico | paciente |
|---|---|---|---|
| Padrón de pacientes (ver, crear, editar) | ✅ | ✅ institucional | ❌ |
| Eliminar una ficha de paciente | ✅ | ❌ | ❌ |
| Agenda: reprogramar, cancelar, eliminar | ❌ | ✅ solo sus citas | ❌ |
| Abrir sala y cerrar teleconsulta con notas | ❌ | ✅ solo sus citas | ❌ |
| Entrar a la sala como paciente | ❌ | ❌ | ✅ solo su cita |
| Historia clínica | ✅ | ✅ | ✅ solo la suya |
| Registro de auditoría | ✅ | ❌ | ❌ |

**El padrón es institucional y la agenda es personal.** El programa de ECNT rota profesionales y cubre municipios dispersos, así que cualquier médico de la IPS atiende a cualquier persona inscrita. Pero una cita es el compromiso de un profesional concreto: dejar que otro la mueva rompe la agenda ajena y la trazabilidad de quién decidió qué.

## Cifrado en reposo y auditoría

Se cifra con el cast `encrypted` de Laravel todo el contenido clínico y los datos de contacto. **No se cifran** `full_name`, `document_number` ni `municipality`: cada cifrado usa un vector de inicialización distinto, así que dos valores iguales producen textos distintos y se romperían la búsqueda de pacientes, el índice único del documento y el reporte por municipio.

La auditoría cubre dos cosas distintas que la Ley 1581 exige por separado:

```mermaid
flowchart LR
    A["Lectura de historia clínica"] --> B["ClinicalAccessAuditor<br/>log_name: acceso_clinico"]
    C["Creación o cambio de un registro clínico"] --> D["Trait LogsChangedFields<br/>log_name: default"]
    B --> E[("activity_log")]
    D --> E
```

El trait registra **los nombres de los campos tocados, nunca sus valores**. `activity_log.properties` es JSON sin cifrar: copiar ahí un diagnóstico anularía el cifrado de la tabla de origen. Con el nombre del campo alcanza para demostrar quién modificó qué, y el contenido vigente se consulta en la fila, cuya lectura queda auditada aparte.

## Modo sin conexión

```mermaid
sequenceDiagram
    participant P as Paciente
    participant SW as Service Worker
    participant Q as Cola IndexedDB
    participant API as Servidor

    P->>SW: registra una medición sin señal
    SW->>Q: encola con client_uuid propio
    Note over Q: la medición no se pierde

    P-->>SW: vuelve la señal (evento online)
    SW->>API: reenvía la cola
    API->>API: firstOrCreate por client_uuid
    alt ya existía
        API-->>SW: 200 sin duplicar ni volver a alertar
    else nueva
        API->>API: notifica al médico si está fuera de rango
        API-->>SW: 200
    end
    SW->>Q: descarta lo sincronizado
```

La clave de idempotencia la genera el dispositivo. Si el servidor guardó pero la respuesta se perdió antes de llegar, el reintento no duplica la medición **ni vuelve a alertar** al médico por algo que ya revisó. Un 422 se descarta en vez de reintentarse por siempre.

## Apoyo a decisiones clínicas

No es analítica predictiva ni aprendizaje automático: es un motor de reglas escritas y explicables.

```mermaid
flowchart LR
    S1["risk_level del instrumento<br/>(FINDRISC, Morisky-Green)"] --> PS["PatientSignals"]
    S2["evaluate() del enum<br/>de signos vitales"] --> PS
    S3["diagnóstico ECNT<br/>de la historia"] --> PS

    PS --> R1["Signo vital sostenido<br/>fuera de rango"]
    PS --> R2["Riesgo de diabetes<br/>con señal de respaldo"]
    PS --> R3["No adherencia sobre<br/>ECNT diagnosticada"]

    R1 --> REC["Recommendation<br/>prioridad · título · MOTIVO · acción"]
    R2 --> REC
    R3 --> REC

    REC --> UI["Franja del dashboard<br/>y panel de la ficha"]
```

Ninguna regla recalcula riesgo: leen señales que el sistema ya calculó. Los umbrales clínicos salen de los instrumentos validados del catálogo y de los rangos del enum; `config/clinical_support.php` solo define umbrales operativos (cuántas lecturas seguidas cuentan como sostenido) y está marcado como pendiente de validación clínica.

Cada recomendación **debe** decir qué regla se disparó y con qué dato. Sin eso el profesional no puede juzgar si aplica a la persona que tiene enfrente.

## Decisiones de diseño y sus motivos

| Decisión | Motivo |
|---|---|
| Inertia en vez de API REST | Un solo cliente institucional; una API separada solo habría sumado capas que mantener |
| Gráficas en SVG propio, sin librería | El peso importa más que las animaciones cuando la red es intermitente |
| Service worker escrito a mano | La estrategia de caché es una decisión clínica, no solo de rendimiento: vale más una historia de hace cinco minutos que una pantalla de error, pero nunca vale mostrar datos a quien ya cerró sesión |
| Notificaciones solo por base de datos | En zonas rurales un correo puede tardar horas; el aviso vive donde el profesional sí entra |
| Instrumentos clínicos en código, no en BD | Son instrumentos validados que cambian con evidencia, no configuración de cliente; versionarlos en git deja trazabilidad de qué se aplicó y cuándo |
| TOTP en vez de SMS para el segundo factor | La aplicación genera el código sin red; un SMS depende de la misma cobertura que falla |
| Consentimiento de teleconsulta al entrar a la sala | Es una autorización sobre la modalidad de atención: tiene sentido pedirla cuando se va a usar, con la cita a la vista |
| Sin Content-Security-Policy todavía | La teleconsulta carga un script externo y la tipografía viene de un CDN; una CSP mal ajustada rompe la videollamada en silencio. Queda para cuando Jitsi esté autoalojado |
