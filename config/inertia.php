<?php

/*
|--------------------------------------------------------------------------
| Inertia
|--------------------------------------------------------------------------
|
| Solo se sobrescribe dónde están las páginas. El paquete las busca en
| 'js/Pages', con mayúscula, pero aquí la carpeta es 'js/pages'. En macOS y
| Windows da igual porque el disco no distingue mayúsculas; en Linux (el CI y
| el VPS) las pruebas no encuentran ninguna página y fallan. Lo demás queda
| con los valores del paquete: Laravel une este archivo con el suyo.
|
*/

return [

    'page_paths' => [
        resource_path('js/pages'),
    ],

    'testing' => [
        'ensure_pages_exist' => true,
        'page_paths' => [
            resource_path('js/pages'),
        ],
        'page_extensions' => ['js', 'jsx', 'svelte', 'ts', 'tsx', 'vue'],
    ],

];
