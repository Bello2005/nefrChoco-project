<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    /**
     * Eliminar la ficha de un paciente.
     *
     * El padrón es institucional: el programa de ECNT rota profesionales y
     * cubre municipios dispersos, así que cualquier médico de la IPS consulta
     * y actualiza a cualquier persona inscrita, y eso ya lo resuelve el
     * middleware de rol.
     *
     * Eliminar es distinto: arrastra la historia clínica, los controles y las
     * mediciones de esa persona. Queda reservado a administración, que es
     * quien responde por la custodia del dato (Ley 1581 de 2012).
     */
    public function delete(User $user, Patient $patient): bool
    {
        return $user->hasRole('admin');
    }
}
