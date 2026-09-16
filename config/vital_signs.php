<?php

return [

    // TODO doc: manual-tecnico.md no menciona este archivo de configuración
    // ni la variable de rangos; agregar a la tabla de configuración.

    /*
    |--------------------------------------------------------------------------
    | Rangos de referencia de los signos vitales
    |--------------------------------------------------------------------------
    |
    | Orientan el tamizaje en territorio y alimentan el panel de alertas de
    | telemonitoreo. No reemplazan el criterio clínico del profesional que
    | evalúa al paciente.
    |
    | Los valores de presión arterial salen de la guía ACC/AHA 2017 de
    | hipertensión: el estadio 1 empieza en 130 mmHg de sistólica O 80 mmHg de
    | diastólica, así que ambos topes vienen del mismo umbral y no de criterios
    | distintos. Los pisos (90/60) corresponden a la convención habitual de
    | hipotensión.
    |
    | TODO: validar con la médica de la IPS. Estos rangos estaban antes fijos en
    | el enum VitalSignType; aquí solo se reubicaron sin cambiar ningún valor,
    | salvo la diastólica, que es nueva.
    |
    */

    'reference_ranges' => [
        'presion_arterial' => ['min' => 90, 'max' => 130],

        // Nuevo: hasta ahora la diastólica no se medía ni alertaba.
        'presion_diastolica' => ['min' => 60, 'max' => 80],

        'frecuencia_cardiaca' => ['min' => 60, 'max' => 100],
        'peso' => ['min' => 40, 'max' => 120],
        'temperatura' => ['min' => 36, 'max' => 37.5],
        'saturacion_oxigeno' => ['min' => 94, 'max' => 100],
        'glucemia' => ['min' => 70, 'max' => 140],
    ],

];
