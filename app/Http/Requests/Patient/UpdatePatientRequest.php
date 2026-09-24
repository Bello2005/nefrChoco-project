<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePatientRequest extends FormRequest
{
    use PatientIdentityRules;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            ...$this->identityRules(),
            'document_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('patients', 'document_number')->ignore($this->route('patient')),
            ],
        ];
    }

    public function attributes(): array
    {
        return $this->identityAttributes();
    }
}
