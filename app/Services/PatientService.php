<?php

namespace App\Services;

use App\Models\Patient;
use App\Services\Catalogs\CodeCatalog;

class PatientService
{
    public function __construct(
        private readonly PatientIdentityService $identity,
        private readonly CodeCatalog $catalog,
    ) {}

    public function create(array $data): Patient
    {
        return Patient::create($this->prepare($data));
    }

    /**
     * Guardar la ficha desde el formulario es la revisión humana: los datos
     * de identidad ya pasaron la validación, así que la ficha deja de estar
     * pendiente en "Fichas por revisar".
     */
    public function update(Patient $patient, array $data): Patient
    {
        $patient->fill($this->prepare($data));
        $patient->forceFill(['identity_review_pending' => false, 'identity_review_reasons' => null]);
        $patient->save();

        return $patient;
    }

    public function delete(Patient $patient): void
    {
        $patient->delete();
    }

    /**
     * full_name se calcula de los nombres separados, para que la búsqueda y
     * los reportes sigan funcionando; y el nombre del municipio sale del
     * catálogo DIVIPOLA cuando se eligió un código.
     */
    private function prepare(array $data): array
    {
        $data['full_name'] = $this->identity->fullName(
            $data['first_name'] ?? null,
            $data['middle_name'] ?? null,
            $data['first_surname'] ?? null,
            $data['second_surname'] ?? null,
        );

        if (! empty($data['municipality_code'])) {
            $data['municipality'] = $this->catalog->display('divipola', $data['municipality_code']) ?? ($data['municipality'] ?? null);
        }

        return $data;
    }
}
