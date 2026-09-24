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

    'storage_path' => 'catalogos',

    // Resultados del buscador: pocos, porque viajan a un teléfono con mala señal.
    'search_limit' => 20,

];
