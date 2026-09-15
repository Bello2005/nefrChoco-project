<?php

namespace App\Policies;

use App\Models\ClinicalHistory;
use App\Models\User;

class ClinicalHistoryPolicy
{
    public function view(User $user, ClinicalHistory $clinicalHistory): bool
    {
        if ($user->hasRole(['admin', 'medico'])) {
            return true;
        }

        return $user->hasRole('paciente')
            && $clinicalHistory->patient->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'medico']);
    }

    public function update(User $user, ClinicalHistory $clinicalHistory): bool
    {
        return $user->hasRole(['admin', 'medico']);
    }
}
