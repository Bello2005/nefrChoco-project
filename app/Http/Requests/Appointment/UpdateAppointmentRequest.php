<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use App\Services\AttentionRecordService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAppointmentRequest extends FormRequest
{
    /**
     * La autorización va antes que la validación: quien no puede tocar esta
     * cita recibe un 403, no mensajes de validación sobre sus datos.
     */
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('appointment'));
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'scheduled_at' => ['required', 'date'],
            'type' => ['required', Rule::in([Appointment::TYPE_IN_PERSON, Appointment::TYPE_TELECONSULTATION])],
            'status' => ['required', Rule::in([
                Appointment::STATUS_SCHEDULED,
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ])],
            // Marcarla atendida es cerrar la atención: pide el mismo registro
            // que el cierre de una teleconsulta.
            ...($this->closesAttention() ? app(AttentionRecordService::class)->rules() : []),
        ];
    }

    public function messages(): array
    {
        return $this->closesAttention() ? app(AttentionRecordService::class)->messages() : [];
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->closesAttention()) {
            $validator->after(fn (Validator $validator) => app(AttentionRecordService::class)->afterValidation($validator));
        }
    }

    public function closesAttention(): bool
    {
        return $this->input('status') === Appointment::STATUS_COMPLETED;
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'scheduled_at' => 'fecha y hora',
            'type' => 'tipo de cita',
            'status' => 'estado',
        ];
    }
}
