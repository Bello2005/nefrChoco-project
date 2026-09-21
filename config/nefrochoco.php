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

];
