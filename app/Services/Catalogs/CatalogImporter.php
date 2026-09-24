<?php

namespace App\Services\Catalogs;

use App\Models\Code;
use App\Models\CodeSystem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Importa un catálogo oficial de forma idempotente.
 *
 * - Upsert por (sistema, código): importar dos veces el mismo archivo deja
 *   todo igual.
 * - Los códigos que no vienen en la versión nueva quedan inactivos. NUNCA se
 *   borran: hay registros históricos que los usan.
 * - Guarda versión, fuente, SHA-256 del archivo, fecha y quién importó.
 */
class CatalogImporter
{
    public function __construct(
        private readonly CsvCatalogParser $csv,
        private readonly FhirCatalogParser $fhir,
    ) {}

    /**
     * @return array{system: CodeSystem, total: int, deactivated: int}
     */
    public function import(
        string $systemKey,
        string $path,
        ?string $version = null,
        ?string $source = null,
        ?User $importedBy = null,
        ?string $codeColumn = null,
        ?string $displayColumn = null,
    ): array {
        if (! preg_match('/^[a-z0-9_]+$/', $systemKey)) {
            throw new CatalogFileException('La clave del sistema va en minúsculas, sin espacios (por ejemplo: cie10, divipola).');
        }

        if (! is_file($path)) {
            throw new CatalogFileException("No existe el archivo {$path}.");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            // composer.json no trae librería de Excel: una exportación a CSV
            // evita sumar una dependencia pesada solo para esto.
            throw new CatalogFileException('Los archivos de Excel no se leen directamente. Ábrelo y guárdalo como "CSV UTF-8 (delimitado por comas)", y vuelve a correr el comando con ese archivo.');
        }

        $configured = config("catalogs.systems.{$systemKey}", []);
        $name = $configured['name'] ?? null;

        if ($extension === 'json') {
            $parsed = $this->fhir->parse($path);
            $codes = $parsed['codes'];
            $version ??= $parsed['version'];
            $name ??= $parsed['name'];
            $source ??= $parsed['url'];
        } else {
            $codes = $this->csv->parse(
                $path,
                $codeColumn ?? ($configured['csv_columns']['code'] ?? null),
                $displayColumn ?? ($configured['csv_columns']['display'] ?? null),
            );
        }

        if ($codes === []) {
            throw new CatalogFileException('El archivo no trae ningún código.');
        }

        return DB::transaction(function () use ($systemKey, $name, $version, $source, $path, $importedBy, $codes) {
            $system = CodeSystem::updateOrCreate(['key' => $systemKey], [
                'name' => $name ?? $systemKey,
                'version' => $version,
                'source' => $source ?? basename($path),
                'source_sha256' => hash_file('sha256', $path),
                'imported_at' => now(),
                'imported_by' => $importedBy?->id,
            ]);

            // Un código repetido en el archivo: gana la última fila.
            $rows = [];
            foreach ($codes as $parsed) {
                $rows[$parsed->code] = [
                    'code_system_id' => $system->id,
                    'code' => $parsed->code,
                    'display' => $parsed->display,
                    'parent_code' => $parsed->parentCode,
                    'extra' => $parsed->extra ? json_encode($parsed->extra, JSON_UNESCAPED_UNICODE) : null,
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Por lotes: la CIE-10 y los CUPS traen miles de códigos.
            foreach (array_chunk(array_values($rows), 500) as $chunk) {
                Code::upsert($chunk, ['code_system_id', 'code'], ['display', 'parent_code', 'extra', 'active', 'updated_at']);
            }

            // Lo que ya no viene queda inactivo. La diferencia se calcula aquí y no
            // con un whereNotIn gigante, que en SQLite pasa el límite de parámetros.
            $missing = Code::where('code_system_id', $system->id)
                ->where('active', true)
                ->pluck('code')
                ->reject(fn (string $code) => isset($rows[$code]))
                ->values();

            foreach ($missing->chunk(500) as $chunk) {
                Code::where('code_system_id', $system->id)->whereIn('code', $chunk->all())->update(['active' => false, 'updated_at' => now()]);
            }

            $deactivated = $missing->count();

            activity('catalogos')
                ->causedBy($importedBy)
                ->performedOn($system)
                ->withProperties(['version' => $version, 'codigos' => count($rows), 'desactivados' => $deactivated])
                ->event('catalogo_importado')
                ->log("Importó el catálogo {$systemKey}");

            return ['system' => $system, 'total' => count($rows), 'deactivated' => $deactivated];
        });
    }
}
