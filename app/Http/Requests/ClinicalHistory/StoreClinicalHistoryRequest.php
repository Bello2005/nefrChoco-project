<?php

namespace App\Http\Requests\ClinicalHistory;

use Illuminate\Foundation\Http\FormRequest;

class StoreClinicalHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_history' => ['nullable', 'string'],
            'ecnt_diagnosis' => ['nullable', 'string', 'max:255'],
            'allergies' => ['nullable', 'string'],
            'current_medication' => ['nullable', 'string'],
        ];
    }

    public function attributes(): array
    {
        return [
            'medical_history' => 'antecedentes',
            'ecnt_diagnosis' => 'diagnóstico ECNT',
            'allergies' => 'alergias',
            'current_medication' => 'medicación actual',
        ];
    }
}
