<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Catalogs\CatalogFileException;
use App\Services\Catalogs\CatalogImporter;
use Illuminate\Console\Command;

/**
 * php artisan catalogos:importar {sistema} {archivo} --version-catalogo=
 *
 * La opción no se llama --version porque Artisan la reserva para mostrar la
 * versión de Laravel y nunca llegaría al comando.
 */
class ImportCatalog extends Command
{
    protected $signature = 'catalogos:importar
        {sistema : Clave del catálogo, por ejemplo cie10, cups, divipola, eapb, tipo_documento}
        {archivo : Ruta del archivo oficial (CSV o FHIR JSON). Se sugiere storage/app/catalogos/}
        {--version-catalogo= : Versión oficial del archivo (en FHIR se toma del recurso si no se indica)}
        {--fuente= : De dónde salió el archivo (URL o descripción)}
        {--por= : Correo de quien importa, para dejarlo registrado}
        {--columna-codigo= : Nombre de la columna del código en el CSV}
        {--columna-nombre= : Nombre de la columna de la descripción en el CSV}';

    protected $description = 'Importa un catálogo oficial (CSV o FHIR CodeSystem/ValueSet) sin borrar códigos';

    public function handle(CatalogImporter $importer): int
    {
        $importedBy = null;
        if ($email = $this->option('por')) {
            $importedBy = User::where('email', $email)->first();

            if ($importedBy === null) {
                $this->error("No existe un usuario con el correo {$email}.");

                return self::FAILURE;
            }
        }

        try {
            $result = $importer->import(
                systemKey: $this->argument('sistema'),
                path: $this->argument('archivo'),
                version: $this->option('version-catalogo'),
                source: $this->option('fuente'),
                importedBy: $importedBy,
                codeColumn: $this->option('columna-codigo'),
                displayColumn: $this->option('columna-nombre'),
            );
        } catch (CatalogFileException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Catálogo %s (versión %s): %d códigos vigentes, %d desactivados. SHA-256: %s',
            $result['system']->key,
            $result['system']->version ?? 'sin versión',
            $result['total'],
            $result['deactivated'],
            $result['system']->source_sha256,
        ));

        return self::SUCCESS;
    }
}
