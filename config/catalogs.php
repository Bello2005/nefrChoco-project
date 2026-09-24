<?php

/*
|--------------------------------------------------------------------------
| Catálogos oficiales
|--------------------------------------------------------------------------
|
| Sistemas que la plataforma conoce, con su nombre legible y las columnas que
| trae el archivo oficial cuando llega en CSV. Los archivos NO van al repo:
| los descarga una persona de la fuente oficial (SISPRO, DANE, paquete FHIR
| del IHCE) y los guarda en storage/app/catalogos, que está en .gitignore.
| Ver docs/manual-tecnico.md, "Catálogos oficiales".
|
| csv_columns: nombre de la columna del código y de la descripción en el
| archivo oficial. [CONFIRMAR] contra cada archivo real: no se escribieron de
| memoria. Mientras estén en null, el importador busca columnas llamadas
| "code"/"codigo" y "display"/"nombre"/"descripcion", y se pueden indicar a
| mano con --columna-codigo y --columna-nombre.
|
| Se puede importar cualquier otro sistema (por ejemplo, los CodeSystem y
| ValueSet del paquete FHIR del IHCE) con una clave en minúsculas.
|
*/

return [

    'systems' => [
        'cie10' => ['name' => 'CIE-10', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
        'cie11' => ['name' => 'CIE-11', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
        'cups' => ['name' => 'CUPS', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
        'divipola' => ['name' => 'DIVIPOLA (municipios)', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
        'eapb' => ['name' => 'EAPB', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
        'tipo_documento' => ['name' => 'Tipos de documento', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
    ],

    /*
    | Sexo biológico → código FHIR.
    |
    | Fuente: ValueSet IHCE-SexoBiologico-VS
    | (http://ihcecol.gov.co/fhir/ValueSet/IHCE-SexoBiologico-VS) del paquete
    | co.gov.minsalud.rda 1.0.0, que usa http://hl7.org/fhir/administrative-gender.
    | Copiado del package.tgz oficial (confirmado por Bello el 24-sep-2026). La
    | portada publicada de la guía dice otro canonical (fhir.minsalud.gov.co):
    | se revisa en el prompt 10 contra el paquete, con el validador de HL7.
    */
    'biological_sex_fhir' => [
        'femenino' => 'female',
        'masculino' => 'male',
        'indeterminado' => 'other',
        'desconocido' => 'unknown',
    ],

    /*
    | Catálogo contra el que se valida cada dato de la ficha (Res. 866 de 2021).
    |
    | Clave del catálogo importado con catalogos:importar. Mientras el
    | catálogo no esté importado, o la clave sea null, el campo se guarda como
    | texto libre. [CONFIRMAR] las claves de los CodeSystem del paquete FHIR
    | del IHCE que correspondan: no se escribieron de memoria.
    */
    'patient_fields' => [
        'document_type' => 'tipo_documento',
        'municipality_code' => 'divipola',
        'eapb_code' => 'eapb',
        'gender_identity' => null, // TODO: catálogo del IHCE [CONFIRMAR]
        'ethnicity' => null, // TODO: catálogo del IHCE [CONFIRMAR]
        'disability' => null, // TODO: catálogo del IHCE [CONFIRMAR]
        'occupation' => null, // TODO: catálogo de ocupaciones [CONFIRMAR]
        'residence_zone' => null, // TODO: catálogo del IHCE [CONFIRMAR]
        'affiliation_type' => null, // TODO: régimen / tipo de usuario [CONFIRMAR]
    ],

    /*
    | Catálogos del registro de la atención (RIPS, Res. 948 de 2026).
    | [CONFIRMAR] las claves contra el anexo técnico de la Res. 948: no se
    | escribieron de memoria. Mientras sean null, el campo se guarda como texto.
    */
    'attention_fields' => [
        'diagnosis_type' => null, // [CONFIRMAR] tipo de diagnóstico principal
        'purpose' => null, // [CONFIRMAR] finalidad de la consulta
        'external_cause' => null, // [CONFIRMAR] causa externa
    ],

    'storage_path' => 'catalogos',

    // Resultados del buscador: pocos, porque viajan a un teléfono con mala señal.
    'search_limit' => 20,

];
