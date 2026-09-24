<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Dominio de correo institucional
    |--------------------------------------------------------------------------
    |
    | Solo el personal (administradores y médicos) entra con correo de la IPS:
    | son cuentas con acceso a datos clínicos de terceros, así que su identidad
    | tiene que ser verificable por la institución y su acceso revocable con la
    | cuenta de correo. Los pacientes quedan fuera de la regla a propósito,
    | porque usan su correo personal (Gmail y similares), que es el único que
    | revisan de verdad; exigirles un dominio institucional los dejaría sin
    | forma de recuperar la contraseña.
    |
    | [CONFIRMAR] Dominio de producción. Se asume 'nefrochoco.co' por ser el
    | mismo de PRIVACY_CONTACT_EMAIL (habeasdata@nefrochoco.co). Si la IPS
    | opera con otro dominio, basta con definir ALLOWED_EMAIL_DOMAIN.
    |
    */

    'email_domain' => env('ALLOWED_EMAIL_DOMAIN', 'nefrochoco.co'),

    /*
    |--------------------------------------------------------------------------
    | Presupuesto del material educativo
    |--------------------------------------------------------------------------
    |
    | Tope de caracteres del cuerpo de un contenido propio. No es un límite de
    | base de datos sino de conexión: el material marcado como disponible sin
    | conexión se descarga entero cuando el paciente abre la sección, y esa
    | descarga ocurre sobre el plan de datos de alguien que puede estar en zona
    | rural con señal intermitente.
    |
    | 20.000 caracteres son unas ocho páginas de texto, más de lo que dura la
    | atención de nadie leyendo en un teléfono.
    |
    | [CONFIRMAR] La cifra sale de estimar qué aguanta una conexión 3G mala, no
    | de una medición en el territorio. Si hay datos reales de Quibdó o Istmina,
    | ajustar EDUCATIONAL_MAX_BODY_CHARACTERS.
    |
    */

    'educational_content' => [
        'max_body_characters' => (int) env('EDUCATIONAL_MAX_BODY_CHARACTERS', 20000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Institución (REPS)
    |--------------------------------------------------------------------------
    |
    | Identificación de la IPS para el RDA del IHCE: razón social, NIT, código
    | de habilitación REPS, código de sede y municipio de la sede (DIVIPOLA).
    |
    | [CONFIRMAR] con la IPS (Tania). Todos en null por defecto: NUNCA se
    | escriben valores inventados. Mientras falten, Admin → Datos de la
    | institución muestra qué falta, y la historia imprimible no los muestra.
    |
    */

    'institution' => [
        'name' => env('INSTITUTION_NAME'), // [CONFIRMAR] razón social
        'nit' => env('INSTITUTION_NIT'), // [CONFIRMAR]
        'reps_code' => env('INSTITUTION_REPS_CODE'), // [CONFIRMAR] código de habilitación REPS
        'site_code' => env('INSTITUTION_SITE_CODE'), // [CONFIRMAR] código de sede
        'municipality_code' => env('INSTITUTION_MUNICIPALITY_CODE'), // [CONFIRMAR] DIVIPOLA de la sede
    ],

];
