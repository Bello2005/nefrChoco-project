<?php

namespace App\Services\Catalogs;

/**
 * Lee recursos FHIR CodeSystem y ValueSet en JSON, como los que trae el
 * paquete de la guía de implementación del IHCE.
 *
 * De un CodeSystem toma `concept` (con sus hijos anidados, que quedan con su
 * parent_code). De un ValueSet toma `compose.include[].concept` y, si viene
 * expandido, `expansion.contains`. El sistema de origen de cada código queda
 * en `extra.system`, sin reescribirlo.
 */
class FhirCatalogParser
{
    /**
     * @return array{codes: list<ParsedCode>, version: ?string, name: ?string, url: ?string}
     */
    public function parse(string $path): array
    {
        $resource = json_decode((string) file_get_contents($path), true);

        if (! is_array($resource) || ! isset($resource['resourceType'])) {
            throw new CatalogFileException('El archivo no es un recurso FHIR en JSON.');
        }

        $codes = match ($resource['resourceType']) {
            'CodeSystem' => $this->fromConcepts($resource['concept'] ?? [], $resource['url'] ?? null),
            'ValueSet' => $this->fromValueSet($resource),
            default => throw new CatalogFileException("Solo se importan CodeSystem y ValueSet, no {$resource['resourceType']}."),
        };

        return [
            'codes' => $codes,
            'version' => $resource['version'] ?? null,
            'name' => $resource['title'] ?? $resource['name'] ?? null,
            'url' => $resource['url'] ?? null,
        ];
    }

    /** @return list<ParsedCode> */
    private function fromConcepts(array $concepts, ?string $system, ?string $parent = null): array
    {
        $codes = [];

        foreach ($concepts as $concept) {
            if (! isset($concept['code'])) {
                continue;
            }

            $codes[] = new ParsedCode(
                code: (string) $concept['code'],
                display: (string) ($concept['display'] ?? $concept['code']),
                parentCode: $parent,
                extra: array_filter(['system' => $system, 'definition' => $concept['definition'] ?? null]),
            );

            if (! empty($concept['concept'])) {
                array_push($codes, ...$this->fromConcepts($concept['concept'], $system, (string) $concept['code']));
            }
        }

        return $codes;
    }

    /** @return list<ParsedCode> */
    private function fromValueSet(array $resource): array
    {
        $codes = [];

        foreach ($resource['compose']['include'] ?? [] as $include) {
            array_push($codes, ...$this->fromConcepts($include['concept'] ?? [], $include['system'] ?? null));
        }

        foreach ($resource['expansion']['contains'] ?? [] as $item) {
            if (isset($item['code'])) {
                $codes[] = new ParsedCode((string) $item['code'], (string) ($item['display'] ?? $item['code']), null, array_filter(['system' => $item['system'] ?? null]));
            }
        }

        if ($codes === []) {
            throw new CatalogFileException('El ValueSet no enumera códigos (compose.include[].concept o expansion.contains). Si solo referencia un CodeSystem, importa ese CodeSystem.');
        }

        return $codes;
    }
}
