<?php

namespace App\Http\Requests\Teleconsultation;

use Illuminate\Foundation\Http\FormRequest;

class StoreTeleconsultationClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'body' => 'aclaración',
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Escribe la aclaración antes de agregarla.',
        ];
    }
}
