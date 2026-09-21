<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\Concerns\ValidatesStaffEmailDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
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
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user')), ...$this->staffEmailDomainRules()],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => ['required', Rule::in(array_column(Role::cases(), 'value'))],
            // Su propia ficha o una libre. Cualquier otra pertenece a alguien más.
            'patient_id' => [
                'nullable',
                'integer',
                Rule::exists('patients', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where(
                        fn ($clause) => $clause->whereNull('user_id')->orWhere('user_id', $this->route('user')?->id),
                    )),
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
