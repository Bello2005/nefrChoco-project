<?php

namespace App\Services;

/**
 * Datos de la IPS para el RDA (config/nefrochoco.php → institution).
 *
 * Un valor vacío en el .env cuenta como faltante: nunca se rellena con nada.
 */
class InstitutionService
{
    private const LABELS = [
        'name' => 'Razón social',
        'nit' => 'NIT',
        'reps_code' => 'Código de habilitación REPS',
        'site_code' => 'Código de sede',
        'municipality_code' => 'Municipio de la sede (DIVIPOLA)',
    ];

    /** @return array<string, ?string> */
    public function data(): array
    {
        return collect(self::LABELS)
            ->mapWithKeys(fn (string $label, string $key) => [$key => filled(config("nefrochoco.institution.{$key}")) ? (string) config("nefrochoco.institution.{$key}") : null])
            ->all();
    }

    /** @return list<array{key: string, label: string, value: ?string}> */
    public function fields(): array
    {
        $data = $this->data();

        return collect(self::LABELS)
            ->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label, 'value' => $data[$key]])
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function missing(): array
    {
        return collect($this->fields())->whereNull('value')->pluck('label')->all();
    }
}
