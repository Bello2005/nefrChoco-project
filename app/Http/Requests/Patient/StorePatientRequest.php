<?php

namespace App\Http\Requests\Patient;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:20'],
            'document_number' => ['required', 'string', 'max:50', 'unique:patients,document_number'],
            'birth_date' => ['required', 'date', 'before:today'],
            'municipality' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function attributes(): array
    {
        return [
            'full_name' => 'nombre completo',
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'birth_date' => 'fecha de nacimiento',
            'municipality' => 'municipio',
            'phone' => 'teléfono',
            'emergency_contact_name' => 'contacto de emergencia',
            'emergency_contact_phone' => 'teléfono de emergencia',
        ];
    }
}
