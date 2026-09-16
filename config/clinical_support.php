<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apoyo a decisiones clínicas por reglas
    |--------------------------------------------------------------------------
    |
    | Umbrales OPERATIVOS del motor de reglas. No son criterios diagnósticos:
    | definen cuándo vale la pena llamar la atención del profesional sobre un
    | paciente, no qué tiene ese paciente.
    |
    | Los umbrales CLÍNICOS de verdad no viven aquí: los niveles de riesgo salen
    | de los instrumentos validados de ClinicalFormCatalog (FINDRISC,
    | Morisky-Green) y los rangos de referencia del enum VitalSignType. Este
    | archivo solo decide cuántas lecturas seguidas cuentan como "sostenido" y
    | qué niveles ameritan una recomendación.
    |
    | TODO: validar estos valores con la médica de la IPS antes de usarlos en
    | producción. Están puestos para que la demostración sea razonable, no por
    | criterio clínico propio.
    |
    */

    'vital_signs' => [
        // Cuántas lecturas seguidas fuera de rango cuentan como sostenido.
        // Una sola lectura rara puede ser un error de medición en casa.
        'sustained_readings' => 2,

        // Ventana hacia atrás para considerar una medición vigente.
        'lookback_days' => 30,
    ],

    'diabetes' => [
        // Niveles del tamizaje que ameritan cruzarse con otras señales.
        // Corresponden a los definidos en ClinicalFormCatalog.
        'escalating_levels' => ['moderado', 'alto'],
    ],

    'adherence' => [
        // Niveles del test de Morisky-Green que preocupan.
        'concerning_levels' => ['no_adherente'],
    ],

];
