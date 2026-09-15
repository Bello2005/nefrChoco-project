<?php

namespace App\Http\Requests\Teleconsultation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTeleconsultationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'notes' => 'notas de la consulta',
        ];
    }

    public function messages(): array
    {
        return [
            'notes.required' => 'Registra al menos una nota antes de cerrar la teleconsulta.',
        ];
    }
}
