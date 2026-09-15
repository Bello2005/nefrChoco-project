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

];
