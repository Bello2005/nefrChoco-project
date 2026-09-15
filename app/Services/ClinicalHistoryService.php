<?php

namespace App\Services;

use App\Models\ClinicalHistory;
use App\Models\Patient;

class ClinicalHistoryService
{
    public function create(Patient $patient, array $data): ClinicalHistory
    {
        return $patient->clinicalHistories()->create($data);
    }
}
