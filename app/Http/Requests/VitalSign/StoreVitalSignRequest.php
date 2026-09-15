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
            'recorded_at' => 'fecha de la medición',
            'notes' => 'notas',
        ];
    }
}
