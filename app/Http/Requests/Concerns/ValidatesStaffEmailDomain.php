<?php

namespace App\Http\Requests\Concerns;

use App\Enums\Role;

/**
 * Exige el dominio institucional solo al personal. Vive en un trait porque la
 * regla se aplica al crear y al editar, y las dos deben moverse juntas: si solo
 * una exigiera el dominio, editar bastaría para sacar a un admin del dominio.
 */
trait ValidatesStaffEmailDomain
{
    /**
     * @return array<int, string>
     */
    protected function staffEmailDomainRules(): array
    {
        $input = $this->input('role');
        $role = is_string($input) ? Role::tryFrom($input) : null;

        if (! in_array($role, [Role::Admin, Role::Medico], true)) {
            return [];
        }

        return ['lowercase', 'ends_with:@'.config('nefrochoco.email_domain')];
    }

    /**
     * @return array<string, string>
     */
    protected function staffEmailDomainMessages(): array
    {
        return [
            'email.ends_with' => 'El personal debe usar un correo del dominio institucional (@'.config('nefrochoco.email_domain').').',
            'email.lowercase' => 'El correo institucional debe escribirse en minúsculas.',
        ];
    }
}
