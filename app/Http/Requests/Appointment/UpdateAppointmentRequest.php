<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
        ];
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
