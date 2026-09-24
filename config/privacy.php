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

    /*
    |--------------------------------------------------------------------------
    | Consentimiento informado de teleconsulta
    |--------------------------------------------------------------------------
    |
    | Versión del consentimiento específico para ser atendido por videollamada
    | (Resolución 1644 de 2026). Es independiente del anterior: uno autoriza
    | tratar los datos, este autoriza la modalidad de atención. Se versiona por
    | separado porque sus textos cambian por razones distintas.
    |
    */

    'teleconsultation_consent_version' => env('PRIVACY_TELECONSULTATION_CONSENT_VERSION', '2026-09'),

    'contact_email' => env('PRIVACY_CONTACT_EMAIL', 'habeasdata@nefrochoco.co'),

];
