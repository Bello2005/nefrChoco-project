<?php

namespace App\Http\Requests\VitalSign;

use App\Enums\VitalSignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVitalSignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(VitalSignType::class)],
            'value' => ['required', 'numeric', 'min:0', 'max:1000'],
            // Acompaña a la sistólica en un mismo envío. Nunca puede igualarla
            // ni superarla: sería una medición imposible y casi siempre indica
            // que se invirtieron las dos cifras al digitar.
            'value_diastolic' => ['nullable', 'numeric', 'min:0', 'max:1000', 'lt:value'],
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'tipo de signo vital',
            'value' => 'valor',
            'value_diastolic' => 'presión diastólica',
            'recorded_at' => 'fecha de la medición',
            'notes' => 'notas',
        ];
    }

    public function messages(): array
    {
        return [
            'value_diastolic.lt' => 'La presión diastólica debe ser menor que la sistólica. Revisa si se intercambiaron las cifras.',
        ];
    }
}
