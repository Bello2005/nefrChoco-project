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
| active_column: columna que marca si el código está habilitado y el valor
| que significa "sí". Un código que el archivo trae deshabilitado se guarda
| inactivo: queda para mostrar registros viejos, pero no se puede elegir.
| Si el archivo no trae la columna, todos los códigos quedan activos.
|
| CIE-10, CUPS y EAPB: columnas cotejadas el 24-sep-2026 contra las tablas de
| referencia de SISPRO (TablaReferencia_CIE10, _CUPS y _CodigoEAPByNit,
| exportadas de web.sispro.gov.co): Codigo, Nombre y Habilitado (SI/NO).
| DIVIPOLA: cotejado con DIVIPOLA_Municipios.xlsx del DANE (junio de 2026).
|
| Se puede importar cualquier otro sistema (por ejemplo, los CodeSystem y
| ValueSet del paquete FHIR del IHCE) con una clave en minúsculas.
|
*/

return [

    'systems' => [
        'cie10' => ['name' => 'CIE-10', 'csv_columns' => ['code' => 'Codigo', 'display' => 'Nombre'], 'active_column' => ['name' => 'Habilitado', 'value' => 'SI']],
        'cie11' => ['name' => 'CIE-11', 'csv_columns' => ['code' => null, 'display' => null]], // [CONFIRMAR]
        'cups' => ['name' => 'CUPS', 'csv_columns' => ['code' => 'Codigo', 'display' => 'Nombre'], 'active_column' => ['name' => 'Habilitado', 'value' => 'SI']],
        // El Excel del DANE trae título y encabezado en dos filas: se pasa a una
        // tabla plana codigo,nombre,codigo_departamento,departamento,tipo
        // (docs/manual-tecnico.md, "Catálogos oficiales").
        'divipola' => ['name' => 'DIVIPOLA (municipios)', 'csv_columns' => ['code' => 'codigo', 'display' => 'nombre']],
        'eapb' => ['name' => 'EAPB', 'csv_columns' => ['code' => 'Codigo', 'display' => 'Nombre'], 'active_column' => ['name' => 'Habilitado', 'value' => 'SI']],
        // Del CodeSystem ColombianPersonIdentifier de la guía RDA 1.0.0. Sus
        // códigos de primer nivel (RNEC, CANCILLERIA, DIAN, OTROS) agrupan por
        // entidad y no son tipos de documento: leaves_only los deja inactivos.
        'tipo_documento' => ['name' => 'Tipos de documento', 'csv_columns' => ['code' => null, 'display' => null], 'leaves_only' => true],

        // CodeSystem de la guía de implementación RDA 1.0.0 del IHCE
        // (https://fhir.minsalud.gov.co/rda/CodeSystem/...), copiados por Bello
        // de vulcano.ihcecol.gov.co el 24-sep-2026. Se importan como JSON.
        'identidad_genero' => ['name' => 'Identidad de género'],
        'etnia' => ['name' => 'Pertenencia étnica'],
        'discapacidad' => ['name' => 'Discapacidad'],
        'zona_residencia' => ['name' => 'Zona de residencia'],
        'ocupacion' => ['name' => 'Ocupación (CIUO-88 A.C.)'],
        'finalidad_consulta' => ['name' => 'Finalidad de la consulta'],
        'causa_externa' => ['name' => 'Causa externa'],
        'tipo_diagnostico' => ['name' => 'Tipo de diagnóstico principal'],
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
    | texto libre. Las claves salen de los CodeSystem de la guía RDA 1.0.0.
    */
    'patient_fields' => [
        'document_type' => 'tipo_documento',
        'municipality_code' => 'divipola',
        'eapb_code' => 'eapb',
        'gender_identity' => 'identidad_genero',
        'ethnicity' => 'etnia',
        'disability' => 'discapacidad',
        'occupation' => 'ocupacion',
        'residence_zone' => 'zona_residencia',
        // La guía RDA 1.0.0 no trae un CodeSystem de régimen o tipo de afiliación.
        'affiliation_type' => null, // TODO: catálogo oficial [CONFIRMAR]
    ],

    /*
    | Catálogos del registro de la atención (RDA y RIPS). Son los CodeSystem
    | RIPS*Version2 que usa la guía RDA 1.0.0. [CONFIRMAR] contra el anexo
    | técnico de la Res. 948 de 2026 que siguen vigentes para los RIPS.
    | Mientras el catálogo no esté importado, el campo se guarda como texto.
    */
    'attention_fields' => [
        'diagnosis_type' => 'tipo_diagnostico',
        'purpose' => 'finalidad_consulta',
        'external_cause' => 'causa_externa',
    ],

    'storage_path' => 'catalogos',

    // Resultados del buscador: pocos, porque viajan a un teléfono con mala señal.
    'search_limit' => 20,

    // Hasta cuántos códigos un catálogo se muestra como lista desplegable en
    // vez de buscador (CodeCatalog::options).
    'select_max' => 40,

];
