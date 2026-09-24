<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
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
            'document_number' => ['required', 'string', 'max:50', 'unique:patients,document_number'],
        ];
    }

    public function attributes(): array
    {
        return $this->identityAttributes();
    }
}
