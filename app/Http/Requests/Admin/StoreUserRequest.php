<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesStaffEmailDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    use ValidatesStaffEmailDomain;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email', ...$this->staffEmailDomainRules()],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
            // Solo fichas sin cuenta: vincular una que ya tiene dueño le daría a
            // esta cuenta la historia clínica de otra persona.
            'patient_id' => [
                'nullable',
                'integer',
                Rule::exists('patients', 'id')->whereNull('user_id')->whereNull('deleted_at'),
            ],
        ];
    }

    public function messages(): array
    {
        return $this->staffEmailDomainMessages();
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'password' => 'contraseña',
            'role' => 'rol',
            'patient_id' => 'ficha del paciente',
        ];
    }
}
