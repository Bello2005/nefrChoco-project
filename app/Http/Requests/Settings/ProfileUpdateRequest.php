<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],

            'email' => $this->user()->isStaff()
                ? $this->staffEmailRules()
                : [
                    'required',
                    'string',
                    'lowercase',
                    'email',
                    'max:255',
                    Rule::unique(User::class)->ignore($this->user()->id),
                ],
        ];
    }

    /**
     * El personal no cambia su propio correo: es la identidad institucional
     * que da la IPS y el buzón al que llega la recuperación de contraseña, así
     * que solo lo cambia un administrador desde el panel de usuarios, donde se
     * exige el dominio. Se sigue aceptando el correo actual porque el
     * formulario de perfil lo envía siempre junto con el nombre.
     *
     * @return array<int, mixed>
     */
    private function staffEmailRules(): array
    {
        return ['required', Rule::in([$this->user()->email])];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.in' => 'Tu correo institucional solo lo puede cambiar un administrador.',
        ];
    }
}
