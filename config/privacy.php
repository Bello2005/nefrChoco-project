<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Política de tratamiento de datos personales
    |--------------------------------------------------------------------------
    |
    | Versión vigente del aviso de privacidad y la autorización que acepta el
    | titular (Ley 1581 de 2012). Al cambiar el texto se debe subir la versión:
    | los pacientes que aceptaron una anterior vuelven a ver la pantalla de
    | consentimiento, y queda registrado qué versión autorizó cada uno.
    |
    */

    'consent_version' => env('PRIVACY_CONSENT_VERSION', '2026-01'),

    'contact_email' => env('PRIVACY_CONTACT_EMAIL', 'habeasdata@nefrochoco.co'),

];
