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
| Puede ser una lista de condiciones: el código queda activo solo si cumple
| todas (por ejemplo, Habilitado=SI y Extra_I:Consultas=SI).
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
        // OJO: ColombianResidenceZone del IHCE usa 01=Urbana y 02=Rural; la tabla
        // ZonaVersion2 de SISPRO (RIPS) usa lo contrario, 01=Rural y 02=Urbano.
        // Se guarda el código del IHCE porque es el que va en el RDA. Quien lleve
        // la zona al RIPS debe traducirla, no copiarla.
        'zona_residencia' => ['name' => 'Zona de residencia'],
        'ocupacion' => ['name' => 'Ocupación (CIUO-88 A.C.)'],
        // Finalidad y causa externa se importan de las tablas de SISPRO (CSV),
        // no del JSON del IHCE: el anexo de la Res. 948 dice que en consultas
        // solo valen los códigos con Extra_I:Consultas=SI, y el JSON no trae esa
        // columna. Tablas exportadas el 24-sep-2026. En la finalidad, 17 de los
        // 34 códigos no aplican a consultas; en la causa externa aplican todos.
        'finalidad_consulta' => ['name' => 'Finalidad de la consulta', 'csv_columns' => ['code' => 'Codigo', 'display' => 'Nombre'], 'active_column' => [['name' => 'Habilitado', 'value' => 'SI'], ['name' => 'Extra_I:Consultas', 'value' => 'SI']]],
        'causa_externa' => ['name' => 'Causa externa', 'csv_columns' => ['code' => 'Codigo', 'display' => 'Nombre'], 'active_column' => [['name' => 'Habilitado', 'value' => 'SI'], ['name' => 'Extra_I:Consultas', 'value' => 'SI']]],
        'tipo_diagnostico' => ['name' => 'Tipo de diagnóstico principal'],

        // Tabla RIPSTipoUsuarioVersion2 de SISPRO (exportada el 24-sep-2026,
        // actualizada el 23-jul-2026). Es la que pide el campo tipoUsuario del
        // RIPS (Documento técnico 1 de la Res. 948 de 2026, versión 001 del 4-jun-2026): la condición del usuario frente al sistema de salud. Se
        // usa como tipo de afiliación porque la guía RDA 1.0.0 no trae ese CodeSystem.
        'tipo_usuario' => ['name' => 'Tipo de usuario (RIPS)', 'csv_columns' => ['code' => 'Codigo', 'display' => 'Nombre'], 'active_column' => ['name' => 'Habilitado', 'value' => 'SI']],
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
        // La guía RDA 1.0.0 no trae un CodeSystem de régimen o tipo de afiliación:
        // se usa el tipo de usuario del RIPS (campo tipoUsuario de la Res. 948).
        'affiliation_type' => 'tipo_usuario',
    ],

    /*
    | Catálogos del registro de la atención (RDA y RIPS). Son los CodeSystem
    | RIPS*Version2 que usa la guía RDA 1.0.0, y los mismos que pide el
    | Documento técnico 1 de la Res. 948 de 2026, versión 001 del 4-jun-2026:
    | tipoDiagnosticoPrincipal, finalidadTecnologiaSalud y causaMotivoAtencion.
    | Mientras el catálogo no esté importado, el campo se guarda como texto.
    |
    | En consultas solo valen los códigos que la tabla de SISPRO marca en
    | Extra_I:Consultas: ver finalidad_consulta y causa_externa en 'systems'.
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
