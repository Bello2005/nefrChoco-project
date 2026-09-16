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

    /*
    |--------------------------------------------------------------------------
    | Clasificación de enfermedad renal crónica (KDIGO)
    |--------------------------------------------------------------------------
    |
    | Categorías de la guía KDIGO 2012 de enfermedad renal crónica:
    | kdigo.org/wp-content/uploads/2017/02/KDIGO_2012_CKD_GL.pdf
    |
    | A diferencia del resto de este archivo, estos cortes NO son umbrales
    | operativos elegidos por la IPS: son la clasificación publicada, y
    | cambiarlos dejaría de ser KDIGO. Se dejan en config para que queden a la
    | vista y sean verificables, no para ajustarlos.
    |
    | TODO: validar con la médica de la IPS que la clasificación aplicada
    | corresponde a la versión de la guía que la institución sigue.
    |
    */

    'kidney' => [
        // Ordenadas de mayor a menor: se toma la primera cuyo mínimo se alcanza.
        'gfr_categories' => [
            ['category' => 'G1', 'min' => 90],   // normal o alta
            ['category' => 'G2', 'min' => 60],   // levemente disminuida
            ['category' => 'G3a', 'min' => 45],  // leve a moderadamente disminuida
            ['category' => 'G3b', 'min' => 30],  // moderada a severamente disminuida
            ['category' => 'G4', 'min' => 15],   // severamente disminuida
            ['category' => 'G5', 'min' => 0],    // falla renal
        ],

        // Relación albúmina/creatinina en mg/g: A1 <30, A2 30-300, A3 >300.
        'albuminuria_categories' => [
            'a1_below' => 30,
            'a2_up_to' => 300,
        ],

        /*
         | Caída de TFGe que se considera progresión rápida, en mL/min/1,73 m²
         | por año. El valor por defecto es el de la guía KDIGO 2012, sección
         | "Definition and identification of CKD progression", que define el
         | descenso sostenido mayor a 5 por año como progresión rápida.
         |
         | La guía señala que la confianza en la evaluación aumenta con el
         | número de mediciones; la regla del apoyo a decisiones usa solo dos
         | controles como aproximación razonable para esta etapa del proyecto,
         | no el criterio completo de la guía.
         |
         | TODO: validar con la médica de la IPS.
         */
        'rapid_progression_per_year' => 5,

        // Intervalo mínimo entre dos laboratorios para anualizar la caída sin
        // que un par de fechas casi iguales dispare una pendiente enorme.
        'minimum_days_between_labs' => 30,

        // Categorías que ameritan sugerir valoración por nefrología sin
        // necesidad de esperar a ver progresión entre controles.
        // TODO: validar con la médica de la IPS.
        'referral_gfr_categories' => ['G4', 'G5'],
        'referral_albuminuria_categories' => ['A3'],
    ],

];
