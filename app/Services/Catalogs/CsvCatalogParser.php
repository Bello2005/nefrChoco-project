<?php

namespace App\Services\Catalogs;

/**
 * Lee un catálogo oficial exportado a CSV.
 *
 * Las columnas de cada sistema salen de config/catalogs.php (en [CONFIRMAR]
 * hasta cotejarlas con el archivo real) o de las opciones del comando. Si no
 * se indican, busca nombres comunes. El resto de columnas se guarda en
 * `extra`, para no perder información del archivo oficial.
 */
class CsvCatalogParser
{
    private const CODE_CANDIDATES = ['code', 'codigo', 'código', 'cod'];

    private const DISPLAY_CANDIDATES = ['display', 'nombre', 'descripcion', 'descripción', 'name'];

    private const PARENT_CANDIDATES = ['parent_code', 'codigo_padre', 'padre'];

    /**
     * Con varias columnas de activo, el código debe cumplirlas todas.
     *
     * @param  array{name: string, value: string}|list<array{name: string, value: string}>|null  $activeColumn
     * @return list<ParsedCode>
     */
    public function parse(string $path, ?string $codeColumn = null, ?string $displayColumn = null, ?array $activeColumn = null): array
    {
        $content = file_get_contents($path);

        if ($content === false || trim($content) === '') {
            throw new CatalogFileException('El archivo está vacío.');
        }

        // Los archivos oficiales a veces vienen en Windows-1252: se pasan a UTF-8
        // para que las tildes de los nombres no se dañen.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $lines = preg_split('/\r\n|\n|\r/', trim($content));
        $delimiter = $this->detectDelimiter($lines[0]);
        $header = array_map(fn ($name) => trim((string) $name), str_getcsv(array_shift($lines), $delimiter));
        $normalized = array_map(fn ($name) => mb_strtolower($name), $header);

        $codeIndex = $this->columnIndex($normalized, $codeColumn, self::CODE_CANDIDATES, 'del código', '--columna-codigo');
        $displayIndex = $this->columnIndex($normalized, $displayColumn, self::DISPLAY_CANDIDATES, 'del nombre', '--columna-nombre');
        $parentIndex = $this->findIndex($normalized, self::PARENT_CANDIDATES);
        // Una condición o varias: SISPRO marca aparte si un código está
        // habilitado y si aplica a consultas (Res. 948 de 2026).
        $conditions = $activeColumn === null ? [] : (isset($activeColumn['name']) ? [$activeColumn] : $activeColumn);
        $activeChecks = [];
        foreach ($conditions as $condition) {
            $index = $this->findIndex($normalized, [mb_strtolower($condition['name'])]);
            if ($index !== null) {
                $activeChecks[$index] = mb_strtoupper($condition['value']);
            }
        }

        $codes = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }

            $row = str_getcsv($line, $delimiter);
            $code = trim((string) ($row[$codeIndex] ?? ''));

            if ($code === '') {
                continue;
            }

            $extra = [];
            foreach ($header as $index => $name) {
                if (! in_array($index, [$codeIndex, $displayIndex, $parentIndex], true) && isset($row[$index]) && $row[$index] !== '') {
                    $extra[$name] = trim($row[$index]);
                }
            }

            $codes[] = new ParsedCode(
                code: $code,
                display: trim((string) ($row[$displayIndex] ?? '')),
                parentCode: $parentIndex !== null && ($row[$parentIndex] ?? '') !== '' ? trim($row[$parentIndex]) : null,
                extra: $extra,
                // SISPRO marca con Habilitado=NO los códigos que ya no se deben usar.
                active: $this->meetsAll($row, $activeChecks),
            );
        }

        return $codes;
    }

    /** @param  list<string|null>  $row  @param  array<int, string>  $checks */
    private function meetsAll(array $row, array $checks): bool
    {
        foreach ($checks as $index => $value) {
            if (mb_strtoupper(trim((string) ($row[$index] ?? ''))) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function detectDelimiter(string $headerLine): string
    {
        $counts = [';' => substr_count($headerLine, ';'), ',' => substr_count($headerLine, ','), "\t" => substr_count($headerLine, "\t"), '|' => substr_count($headerLine, '|')];
        arsort($counts);

        return array_key_first($counts);
    }

    /** @param  list<string>  $normalized  @param  list<string>  $candidates */
    private function columnIndex(array $normalized, ?string $explicit, array $candidates, string $what, string $option): int
    {
        $index = $explicit !== null
            ? array_search(mb_strtolower($explicit), $normalized, true)
            : $this->findIndex($normalized, $candidates);

        if ($index === false || $index === null) {
            throw new CatalogFileException(sprintf(
                'No encontré la columna %s. Columnas del archivo: %s. Indícala con %s="NOMBRE".',
                $what,
                implode(', ', $normalized),
                $option,
            ));
        }

        return $index;
    }

    /** @param  list<string>  $normalized  @param  list<string>  $candidates */
    private function findIndex(array $normalized, array $candidates): ?int
    {
        foreach ($candidates as $candidate) {
            $index = array_search($candidate, $normalized, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }
}
