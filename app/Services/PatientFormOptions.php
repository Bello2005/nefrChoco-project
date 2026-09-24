<?php

namespace App\Services;

use App\Enums\BiologicalSex;
use App\Models\Patient;
use App\Services\Catalogs\CodeCatalog;

/**
 * Lo que el formulario de la ficha necesita saber: opciones de sexo
 * biológico, qué campos ya tienen catálogo importado (y se eligen con
 * buscador) y el nombre de los códigos que la ficha ya tiene.
 */
class PatientFormOptions
{
    public function __construct(
        private readonly CodeCatalog $catalog,
    ) {}

    /** @return array<string, mixed> */
    public function for(?Patient $patient = null): array
    {
        $catalogs = collect(config('catalogs.patient_fields'))
            ->map(fn (?string $system) => $system !== null && $this->catalog->has($system) ? $system : null);

        $labels = $patient === null ? [] : $catalogs
            ->filter()
            ->map(fn (string $system, string $field) => $this->catalog->display($system, $patient->{$field}))
            ->filter()
            ->all();

        return [
            'biologicalSexOptions' => BiologicalSex::options(),
            'catalogs' => $catalogs->all(),
            'codeLabels' => $labels,
        ];
    }
}
