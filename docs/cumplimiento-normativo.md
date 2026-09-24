# Matriz de cumplimiento normativo

Qué exige cada norma, dónde lo cumple la plataforma, qué prueba lo cubre, en qué estado está y quién tiene el siguiente paso.

**Estados:** ✅ implementado y probado · 🟡 implementado, pero con valores o textos pendientes de validar (`TODO` o `[CONFIRMAR]`) · ❌ pendiente.
**Responsables:** **Bello** (desarrollo y servidor) · **IPS** (médica, área jurídica, dirección) · **Tania** (datos institucionales y facturación).

> Los números de artículo son los que se citan en los encargos. Donde no hay uno confirmado, la fila dice "—". Esta matriz no reemplaza la revisión del área jurídica de la IPS.

## Ley 1581 de 2012 — protección de datos personales

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Autorización previa, informada y versionada del titular | Muro de consentimiento (`EnsureDataConsent`, `config/privacy.php`) | `Privacy/ConsentTest` | ✅ | Bello |
| Revocar la autorización y pedir la supresión | "Mis consentimientos": explica que la historia se conserva y a qué correo escribir | `Paciente/RevocacionConsentimientoTest` | 🟡 texto por validar con jurídica | IPS |
| Seguridad: cifrado en reposo de datos sensibles | Casts `encrypted` en paciente, historia, formularios, notas, aclaraciones, registro de la atención y perfil profesional | `Privacy/EncryptionTest`, `Pacientes/IdentidadPacienteTest`, `Atencion/RegistroAtencionTest`, `Profesionales/PerfilProfesionalTest` | ✅ | Bello |
| Trazabilidad de accesos y cambios, sin copiar valores | `ClinicalAccessAuditor` y `LogsChangedFields` | `Auditoria/*` | ✅ | Bello |
| Encargados del tratamiento: no enviar datos de salud a terceros sin decisión | Respaldos solo locales y cifrados; nada se envía a nubes | — (infraestructura) | ✅ | IPS decide las copias externas |

## Ley 2015 de 2020 — historia clínica electrónica interoperable

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Datos estructurados e interoperables | Identidad del paciente, catálogos oficiales, registro estructurado de la atención | `Pacientes/IdentidadPacienteTest`, `Catalogos/CatalogosOficialesTest`, `Atencion/RegistroAtencionTest` | 🟡 faltan los catálogos reales importados | Bello (importar) |
| Generar e intercambiar el RDA | — | — | ❌ prompts 10–12, esperando el paquete FHIR, el manual y las credenciales | Bello / IPS |

## Resolución 839 de 2017 — manejo y custodia de la historia clínica

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Conservar la historia (15 años desde la última atención): nada clínico se borra físicamente | Llaves `restrict`, borrado lógico de fichas, cuentas que se desactivan, citas que se cancelan | `Privacy/CustodiaDeDatosTest`, `Admin/DesactivarUsuarioTest`, `Medico/CitaAtendidaTest` | ✅ | Bello |
| Los registros no se alteran: una nota cerrada no se edita | Notas inmutables y aclaraciones append-only; diagnósticos corregidos sin tocar el original | `Medico/TeleconsultationClosureTest`, `Medico/AclaracionTeleconsultaTest`, `Atencion/RegistroAtencionTest` | ✅ | Bello |
| Cada registro identifica a su autor | `author_id` en historia, aclaraciones y registro de la atención; "Registrada por" en pantalla e impresión | `ClinicalHistory/AutorHistoriaTest` | ✅ | Bello |

## Resolución 866 de 2021 — datos mínimos para la interoperabilidad

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Identidad: tipo y número de documento, nombres y apellidos separados, sexo biológico | Ficha del paciente | `Pacientes/IdentidadPacienteTest`, `Medico/SexoBiologicoTest` | ✅ | Bello |
| Residencia (DIVIPOLA), asegurador (EAPB) | Ficha, validada contra catálogo cuando está importado | `Pacientes/IdentidadPacienteTest` | 🟡 importar DIVIPOLA y EAPB | Bello |
| Género, etnia, discapacidad, ocupación, zona, tipo de afiliación | Ficha, cifrados, validados contra los CodeSystem de la guía RDA 1.0.0 | `Pacientes/IdentidadPacienteTest`, `Catalogos/ListasYAgrupadoresTest` | 🟡 importarlos en el servidor; el tipo de afiliación usa el tipo de usuario del RIPS [CONFIRMAR con la Res. 948] | Bello |
| Fichas viejas completadas sin adivinar | Migración de datos y "Fichas por revisar" | `Pacientes/IdentidadPacienteTest` | ✅ | IPS (revisar las fichas marcadas) |

## Resolución 1888 de 2025 — Resumen Digital de Atención (RDA)

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Profesional identificado y activo en RETHUS | Perfil profesional y verificación manual en Admin → Usuarios | `Profesionales/PerfilProfesionalTest` | 🟡 cargar los datos de cada médico | IPS |
| IPS identificada con REPS y sede | `config/nefrochoco.php` → `institution`; "Datos de la institución" | `Profesionales/PerfilProfesionalTest` | 🟡 datos en null hasta que los entregue Tania | Tania |
| Saber qué falta antes de generar el documento | Admin → Preparación para interoperar | `Interoperabilidad/PreparacionInteroperarTest` | ✅ | Bello |
| Generar y validar el RDA en FHIR R4 | — | — | ❌ prompt 10, esperando el `package.tgz` | Bello |
| Enviar el RDA (API gateway, API Key, cola y bitácora) | — | — | ❌ prompt 11, esperando el manual y las credenciales de QA | Bello / IPS |
| Consultar RDA de otros prestadores | — | — | ❌ prompt 12, además con visto bueno de jurídica | IPS |

## Resolución 1644 de 2026 — telemedicina (derogó la Res. 2654 de 2019)

| Norma y artículo | Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|---|
| Art. 7 | Consentimiento informado de telemedicina completo y revocable | Consentimiento de teleconsulta y "Mis consentimientos" | `Paciente/ConsentimientoTeleconsultaTest`, `Paciente/RevocacionConsentimientoTest` | 🟡 textos por validar con la médica y jurídica | IPS |
| Art. 14, par. 2 | Respaldo y recuperación ante fallas | `deploy/backup.sh`, timer diario, restauración documentada | — (infraestructura; probado contra PostgreSQL local) | 🟡 falta la llave pública de la IPS y confirmar la retención | IPS / Bello |
| Art. 19, par. 1 | Frecuencia mínima de seguimiento por riesgo, alertas y escalamiento | Nivel de riesgo, controles vencidos, "Te toca medirte", `docs/protocolo-escalamiento.md` | `Telemonitoreo/FrecuenciaSeguimientoTest` | 🟡 todos los plazos en null hasta que la médica los defina | IPS |
| Art. 22 | Sincronización con la Hora Legal de Colombia | `deploy/deploy.sh` (NTP del INM) y evidencia en `/var/log/nefrochoco/` | — (infraestructura) | 🟡 correr el despliegue y guardar la evidencia | Bello |
| Art. 24.8 | Verificar las condiciones del dispositivo antes de la teleconsulta | "Probar mi conexión" | `Paciente/ProbarConexionTest` + Vitest `connection-check.test.ts` | 🟡 umbrales en `[CONFIRMAR]` | Bello |
| Art. 33 | Contingencia por baja conectividad y evidencia de pruebas | `docs/protocolo-baja-conectividad.md` | — | 🟡 protocolo por validar y pruebas reales por registrar | IPS / Bello |
| — | Segundo factor de autenticación | TOTP | `Auth/TwoFactor*` | 🟡 [CONFIRMAR] qué artículo de la 1644 lo respalda | IPS |

## Resolución 948 de 2026 — RIPS (reemplazó a la 2275 de 2023)

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Datos de la atención codificados (diagnóstico, procedimiento, finalidad, causa externa) | Registro estructurado de la atención, con los catálogos RIPS*Version2 de la guía RDA | `Atencion/RegistroAtencionTest`, `Catalogos/ListasYAgrupadoresTest` | 🟡 confirmar con el anexo de la Res. 948 que siguen vigentes para RIPS | Bello |
| Exportar los datos de las atenciones para facturación (camino b) | — | — | ❌ prompt 13, esperando el anexo técnico y la confirmación de Tania | Tania / Bello |

## Resolución 1442 de 2024 y Resolución 1657 de 2025 — CIE-11

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Codificación dual CIE-10 / CIE-11 durante la transición | Campo CIE-11 opcional, visible solo si el catálogo está importado, sin equivalencias automáticas | `Atencion/RegistroAtencionTest` | 🟡 importar la CIE-11 cuando corresponda | Bello |

## Circular 019 de 2026 — prescripción de medicamentos UPC por RDA

| Requisito | Dónde se cumple | Prueba Pest | Estado | Responsable |
|---|---|---|---|---|
| Prescripción interoperable de medicamentos | Medicamentos de la atención en texto; el código queda nullable | `Atencion/RegistroAtencionTest` | ❌ falta el catálogo oficial de medicamentos y los perfiles de Gestión Farmacéutica | Bello / IPS |

## Pendientes que dependen de terceros

- **Tania:** razón social, NIT, código REPS, código de sede y municipio de la sede. Confirmar quién factura (hoy: camino b, exportación).
- **Médica de la IPS:** umbrales clínicos, plazos de seguimiento por riesgo, protocolo de escalamiento, sexo biológico indeterminado o desconocido, y los textos del consentimiento.
- **Área jurídica:** textos del consentimiento y de "Mis consentimientos", y la consulta de historias de otros prestadores.
- **Bello:** descargar e importar los catálogos oficiales, subir los archivos del IHCE y de la Res. 948 a la rama `fuentes-oficiales`, y generar la llave `age` de los respaldos junto con la IPS.
