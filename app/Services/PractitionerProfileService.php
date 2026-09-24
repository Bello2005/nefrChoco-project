<?php

namespace App\Services;

use App\Models\PractitionerProfile;
use App\Models\User;

class PractitionerProfileService
{
    /**
     * Guarda los datos profesionales del médico.
     *
     * Si cambia el documento o el registro profesional, la verificación RETHUS
     * anterior deja de valer: se verificó a otra identidad profesional.
     */
    public function save(User $doctor, array $data): PractitionerProfile
    {
        $profile = $doctor->practitionerProfile ?? new PractitionerProfile(['user_id' => $doctor->id]);
        $profile->user()->associate($doctor);
        $profile->fill($data);

        if ($profile->exists && $profile->isDirty(['document_type', 'document_number', 'professional_registration'])) {
            $profile->forceFill(['rethus_verified_at' => null, 'rethus_verified_by' => null, 'rethus_note' => null]);
        }

        $profile->save();

        return $profile;
    }

    /**
     * Registra que una persona verificó al médico en la consulta pública de
     * ReTHUS. No consulta nada por su cuenta.
     */
    public function markRethusVerified(User $doctor, User $admin, string $note): PractitionerProfile
    {
        $profile = $doctor->practitionerProfile ?? throw new \DomainException('Primero registra los datos profesionales del médico.');

        $profile->forceFill([
            'rethus_verified_at' => now(),
            'rethus_verified_by' => $admin->id,
            'rethus_note' => $note,
        ])->save();

        return $profile;
    }
}
