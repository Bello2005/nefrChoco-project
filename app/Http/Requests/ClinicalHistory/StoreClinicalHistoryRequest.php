<?php

namespace App\Http\Requests\ClinicalHistory;

use App\Rules\ActiveCode;
use App\Services\Catalogs\CodeCatalog;
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
            // Opcionales, además de ecnt_diagnosis (que se conserva como texto).
            'diagnoses' => [app(CodeCatalog::class)->has('cie10') ? 'nullable' : 'prohibited', 'array', 'max:10'],
            'diagnoses.*' => ['string', 'distinct', new ActiveCode('cie10')],
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
