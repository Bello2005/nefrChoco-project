<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ventana para entrar a la sala
    |--------------------------------------------------------------------------
    |
    | Minutos antes y después de la hora agendada en los que el paciente puede
    | abrir la videollamada. Fuera de esa franja la sala no se abre, para que un
    | enlace viejo no siga dando acceso a una atención que ya terminó.
    |
    | Son umbrales operativos y ajustables por la IPS: donde la señal es mala
    | conviene ampliarlos para dar margen a reconexiones del paciente.
    |
    */

    'join_window' => [
        'minutes_before' => (int) env('TELECONSULTATION_JOIN_MINUTES_BEFORE', 15),
        'minutes_after' => (int) env('TELECONSULTATION_JOIN_MINUTES_AFTER', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prueba de conexión antes de la sala (Res. 1644 de 2026, art. 24.8)
    |--------------------------------------------------------------------------
    |
    | Umbrales TÉCNICOS (no clínicos) para decirle al paciente si su conexión
    | alcanza para video, para solo audio o para nada. La velocidad se mide
    | bajando un archivo propio (public/connection-test.bin), así que es
    | aproximada y nada sale hacia terceros.
    |
    | [CONFIRMAR] contra los requisitos de red publicados por Jitsi: son valores
    | provisionales, porque la documentación de Jitsi no fue accesible desde el
    | entorno de desarrollo. Ajustarlos también con las pruebas reales de
    | docs/protocolo-baja-conectividad.md.
    |
    */

    'connection_check' => [
        'video' => [
            'min_download_kbps' => (int) env('CONNECTION_CHECK_VIDEO_MIN_KBPS', 1000), // [CONFIRMAR]
            'max_latency_ms' => (int) env('CONNECTION_CHECK_VIDEO_MAX_LATENCY_MS', 400), // [CONFIRMAR]
        ],
        'audio' => [
            'min_download_kbps' => (int) env('CONNECTION_CHECK_AUDIO_MIN_KBPS', 100), // [CONFIRMAR]
            'max_latency_ms' => (int) env('CONNECTION_CHECK_AUDIO_MAX_LATENCY_MS', 1000), // [CONFIRMAR]
        ],
    ],

];
