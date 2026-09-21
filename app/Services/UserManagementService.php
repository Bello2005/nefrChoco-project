<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    public function create(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles([$data['role']]);

        $this->syncPatientLink($user, $data);

        return $user;
    }

    public function update(User $user, array $data): User
    {
        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        $user->syncRoles([$data['role']]);

        $this->syncPatientLink($user, $data);

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * Deja la cuenta apuntando exactamente a la ficha elegida, o a ninguna.
     *
     * Solo un paciente puede tener ficha: si la cuenta cambia a otro rol, se
     * suelta la que tuviera, porque una historia clínica colgando de una cuenta
     * de médico o de admin sería un dato falso en la tabla.
     *
     * Se guarda con el modelo, no con un update masivo, para que el cambio pase
     * por LogsChangedFields y quede en el rastro de auditoría.
     */
    private function syncPatientLink(User $user, array $data): void
    {
        $patientId = $data['role'] === Role::Paciente->value && isset($data['patient_id'])
            ? (int) $data['patient_id']
            : null;

        Patient::where('user_id', $user->id)
            ->get()
            ->reject(fn (Patient $patient) => $patient->id === $patientId)
            ->each(fn (Patient $patient) => $patient->update(['user_id' => null]));

        if ($patientId !== null) {
            Patient::find($patientId)?->update(['user_id' => $user->id]);
        }
    }
}
