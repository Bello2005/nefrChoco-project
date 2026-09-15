<?php

namespace App\Http\Requests\ClinicalForm;

use App\Support\ClinicalFormCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'form_type' => ['required', 'string', Rule::in(ClinicalFormCatalog::keys())],
            'answers' => ['required', 'array'],
            // Las reglas de cada respuesta salen de la plantilla, así el
            // instrumento y su validación no pueden desincronizarse.
            ...ClinicalFormCatalog::validationRules($this->input('form_type', '')),
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'form_type' => 'formulario',
        ];
    }

    public function messages(): array
    {
        return [
            'answers.*.required' => 'Esta respuesta es obligatoria.',
            'answers.*.in' => 'Selecciona una opción válida.',
        ];
    }
}
