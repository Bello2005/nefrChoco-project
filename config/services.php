<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * Dominio del servidor de videollamada. El valor por defecto queda en el
     * Jitsi público solo como último recurso: ahí un profesional no puede
     * iniciar la sala sin autenticarse, así que en cualquier entorno real esta
     * variable debe apuntar al servidor propio.
     */
    'jitsi' => [
        'domain' => env('JITSI_DOMAIN', 'meet.jit.si'),
    ],

    /*
     * Contraseña inicial del admin sembrado por AdminUserSeeder fuera de
     * local/testing. Se lee acá (y no con env() directo en el seeder) porque
     * config:cache, que corre en cualquier deploy real, deja de leer el .env:
     * un env() fuera de un archivo de config devolvería null aunque la
     * variable sí esté definida en el servidor.
     */
    'admin' => [
        'initial_password' => env('ADMIN_INITIAL_PASSWORD'),
    ],

];
