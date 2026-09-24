<?php

namespace App\Http\Requests\Teleconsultation;

use App\Services\AttentionRecordService;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeleconsultationClarificationRequest extends FormRequest
{
    /**
     * La autorización va antes que la validación: quien no puede tocar esta
     * cita recibe un 403, no mensajes de validación sobre sus datos.
     */
    public function authorize(): bool
    {
        return $this->user()->can('clarifyTeleconsultation', $this->route('appointment'));
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            // Opcional: el diagnóstico corregido reemplaza a uno vigente de la cita.
            ...app(AttentionRecordService::class)->correctionRules($this->route('appointment')),
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
