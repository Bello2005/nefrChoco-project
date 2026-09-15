<?php

namespace App\Http\Requests\Appointment;

use App\Models\Appointment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'type' => ['required', Rule::in([Appointment::TYPE_IN_PERSON, Appointment::TYPE_TELECONSULTATION])],
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'scheduled_at' => 'fecha y hora',
            'type' => 'tipo de cita',
        ];
    }
}
