<?php

namespace App\Http\Requests\Patient;

use App\Enums\BiologicalSex;
use App\Rules\ActiveCode;
use App\Services\Catalogs\CodeCatalog;
use Illuminate\Validation\Rule;

/**
 * Reglas de la ficha compartidas por crear y editar (Res. 866 de 2021).
 *
 * Cada dato codificado se valida contra su catálogo oficial cuando ese
 * catálogo ya está importado (config/catalogs.php → patient_fields). Mientras
 * no lo esté, se acepta como texto, para no bloquear el registro de pacientes
 * por un archivo que todavía no se ha descargado.
 */
trait PatientIdentityRules
{
    /** @return array<string, mixed> */
    protected function identityRules(): array
    {
        $catalog = app(CodeCatalog::class);
        $hasMunicipalities = $this->catalogFor('municipality_code') !== null && $catalog->has($this->catalogFor('municipality_code'));

        return [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'first_surname' => ['required', 'string', 'max:100'],
            'second_surname' => ['nullable', 'string', 'max:100'],
            'document_type' => ['required', ...$this->codedRules('document_type', 20)],
            'birth_date' => ['required', 'date', 'before:today'],
            // Los cuatro valores del ValueSet de sexo biológico del IHCE.
            'biological_sex' => ['required', Rule::enum(BiologicalSex::class)],
            // Con DIVIPOLA importado se elige el código y el nombre sale del
            // catálogo; sin él, el municipio se escribe como antes.
            'municipality_code' => $hasMunicipalities ? ['required', ...$this->codedRules('municipality_code', 20)] : ['nullable', 'string', 'max:20'],
            'municipality' => $hasMunicipalities ? ['nullable', 'string', 'max:255'] : ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'gender_identity' => ['nullable', ...$this->codedRules('gender_identity')],
            'ethnicity' => ['nullable', ...$this->codedRules('ethnicity')],
            'disability' => ['nullable', ...$this->codedRules('disability')],
            'occupation' => ['nullable', ...$this->codedRules('occupation')],
            'residence_zone' => ['nullable', ...$this->codedRules('residence_zone')],
            'eapb_code' => ['nullable', ...$this->codedRules('eapb_code')],
            'affiliation_type' => ['nullable', ...$this->codedRules('affiliation_type')],
        ];
    }

    /** @return array<string, string> */
    protected function identityAttributes(): array
    {
        return [
            'first_name' => 'primer nombre',
            'middle_name' => 'segundo nombre',
            'first_surname' => 'primer apellido',
            'second_surname' => 'segundo apellido',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'birth_date' => 'fecha de nacimiento',
            'biological_sex' => 'sexo biológico',
            'municipality_code' => 'municipio',
            'municipality' => 'municipio',
            'phone' => 'teléfono',
            'emergency_contact_name' => 'contacto de emergencia',
            'emergency_contact_phone' => 'teléfono de emergencia',
            'gender_identity' => 'identidad de género',
            'ethnicity' => 'pertenencia étnica',
            'disability' => 'discapacidad',
            'occupation' => 'ocupación',
            'residence_zone' => 'zona de residencia',
            'eapb_code' => 'EAPB (asegurador)',
            'affiliation_type' => 'tipo de afiliación',
        ];
    }

    private function catalogFor(string $field): ?string
    {
        return config("catalogs.patient_fields.{$field}");
    }

    /** @return list<mixed> */
    private function codedRules(string $field, int $maxText = 255): array
    {
        $system = $this->catalogFor($field);

        if ($system !== null && app(CodeCatalog::class)->has($system)) {
            return ['string', new ActiveCode($system)];
        }

        // TODO: sin catálogo oficial importado, texto libre.
        return ['string', "max:{$maxText}"];
    }
}
