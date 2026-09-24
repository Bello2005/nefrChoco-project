# NefroChocó — reglas para el agente

Plataforma de telemedicina de la IPS NefroChocó para personal de salud y pacientes con ECNT
(diabetes, hipertensión, ERC, obesidad, riesgo cardiovascular) en municipios rurales del Chocó,
con señal intermitente y poca familiaridad con la tecnología. Toda decisión de diseño se juzga
con una pregunta: ¿funciona con mala señal y sin capacitación previa?

## Stack
Laravel 12 (PHP ^8.4) + Inertia 2 + React 19 + TypeScript + Tailwind 4 con tokens propios.
PostgreSQL es el único motor soportado. Pruebas con Pest (PHP) y Vitest (TS).
Roles (spatie/laravel-permission): admin, medico, paciente.
Auditoría: spatie/laravel-activitylog + trait LogsChangedFields + app/Services/ClinicalAccessAuditor.php.

## Organización
- Rutas por zona: routes/admin.php, medico.php, paciente.php (auth, role:<rol>, throttle:zona-clinica).
- Controladores delgados en app/Http/Controllers/{Admin,Medico,Paciente}.
- Lógica en app/Services, validación en app/Http/Requests, autorización en app/Policies.
- Menú por rol: resources/js/components/app-sidebar.tsx.

## Decisiones que no se cambian sin preguntar
- El padrón es institucional (cualquier médico ve y edita cualquier ficha); la agenda es personal
  (solo el médico de la cita la reprograma, cancela, abre su sala y firma sus notas). Solo admin
  elimina fichas, y siempre con borrado lógico.
- Cifrado en reposo (cast encrypted) de todo contenido clínico y dato de contacto. NO se cifran
  full_name, document_number ni municipality (romperían búsqueda, índice único y reportes).
- La auditoría guarda nombres de campos, nunca valores. Todo modelo clínico usa LogsChangedFields.
  Toda pantalla nueva con contenido clínico llama a ClinicalAccessAuditor.
- Nada clínico se borra físicamente: borrado lógico y FKs restrict (Res. 839 de 2017: conservación
  mínima de 15 años desde la última atención). Una nota cerrada no se edita: se corrige con aclaraciones.
- Mutaciones del paciente que deban funcionar sin señal pasan por la cola offline con client_uuid.
- El apoyo a decisiones son reglas escritas, no IA.
- Consentimientos separados y versionados (config/privacy.php).
- Notificaciones solo por base de datos.

## Reglas de trabajo
- NUNCA inventes umbrales clínicos, dosis, textos médicos o legales, ni CÓDIGOS OFICIALES
  (CIE-10, CIE-11, CUPS, DIVIPOLA, EAPB, REPS, perfiles o URLs FHIR, endpoints del Ministerio).
  Los códigos se importan de archivos oficiales. Si falta la fuente, deja el mecanismo listo y un TODO.
  Lo clínico queda marcado: TODO: validar con la médica de la IPS.
- Si algo envía datos de salud a un tercero, DETENTE y pregunta.
- Todo cambio de comportamiento lleva prueba Pest (tests/Feature/<Tema>/<Algo>Test.php, en español).
  Corre php artisan test (y npm test si tocas TS) antes del commit.
- Migraciones para PostgreSQL. Toda FK lleva índice. Campos clínicos nuevos: cifrados + LogsChangedFields.
- Clases en inglés; valores de dominio, rutas y textos visibles en español. Los comentarios explican el porqué.
- Reutiliza Field, FormCard, PageHeader, EmptyState, Badge y los tokens de Tailwind.
- Mantén sincronizados README.md y docs/{manual-tecnico,arquitectura,guia-usuario,despliegue}.md.
- Norma de telemedicina vigente: Res. 1644 de 2026.
- Commits: Conventional Commits en español, con un cuerpo que explique el porqué.
  Sin líneas de coautor ni de sesión (Co-Authored-By, Claude-Session): decisión de Bello.
- Si una instrucción choca con estas reglas, detente y pregunta antes de seguir.
