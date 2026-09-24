<?php

namespace App\Http\Requests\Teleconsultation;

use App\Services\AttentionRecordService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateTeleconsultationRequest extends FormRequest
{
    /**
     * La autorización va antes que la validación: quien no puede tocar esta
     * cita recibe un 403, no mensajes de validación sobre sus datos.
     */
    public function authorize(): bool
    {
        return $this->user()->can('manageTeleconsultation', $this->route('appointment'));
    }

    public function rules(): array
    {
        // Cerrar la teleconsulta es cerrar la atención: van las notas y el
        // registro estructurado (diagnósticos, procedimientos, etc.).
        return [
            'notes' => ['required', 'string', 'max:5000'],
            ...app(AttentionRecordService::class)->rules(),
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
            ...app(AttentionRecordService::class)->messages(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => app(AttentionRecordService::class)->afterValidation($validator));
    }
}
