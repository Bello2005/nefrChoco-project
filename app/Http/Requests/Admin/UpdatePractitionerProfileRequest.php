<?php

namespace App\Http\Requests\Admin;

use App\Rules\ActiveCode;
use App\Services\Catalogs\CodeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePractitionerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->route('user');
        $documentTypeRule = app(CodeCatalog::class)->has('tipo_documento')
            ? new ActiveCode('tipo_documento')
            : 'max:20';

        return [
            'document_type' => ['required', 'string', $documentTypeRule],
            'document_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('practitioner_profiles', 'document_number')->ignore($user->practitionerProfile?->id),
            ],
            'profession' => ['required', 'string', 'max:255'],
            'professional_registration' => ['required', 'string', 'max:100'],
            'specialty' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'profession' => 'profesión',
            'professional_registration' => 'registro profesional',
            'specialty' => 'especialidad',
        ];
    }
}
