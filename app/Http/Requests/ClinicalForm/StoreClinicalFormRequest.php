<?php

namespace App\Http\Requests\ClinicalForm;

use App\Models\Patient;
use App\Support\ClinicalFormCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClinicalFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'form_type' => ['required', 'string', Rule::in(ClinicalFormCatalog::keys())],
            'answers' => ['required', 'array'],
            // Las reglas de cada respuesta salen de la plantilla, así el
            // instrumento y su validación no pueden desincronizarse.
            ...ClinicalFormCatalog::validationRules($this->input('form_type', '')),
        ];
    }

    public function attributes(): array
    {
        return [
            'patient_id' => 'paciente',
            'form_type' => 'formulario',
        ];
    }

    public function messages(): array
    {
        return [
            'answers.*.required' => 'Esta respuesta es obligatoria.',
            'answers.*.in' => 'Selecciona una opción válida.',
            'answers.*.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
        ];
    }

    /**
     * El seguimiento renal necesita el sexo biológico de la ficha.
     *
     * CKD-EPI 2021 lo usa como variable, así que sin ese dato no hay TFG que
     * calcular. Se bloquea antes de guardar en vez de registrar el formulario
     * sin resultado o, peor, asumir un valor por defecto.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('form_type') !== 'seguimiento_erc') {
                return;
            }

            $patient = Patient::find($this->integer('patient_id'));

            if ($patient && $patient->biological_sex === null) {
                $validator->errors()->add(
                    'patient_id',
                    'Esta ficha no tiene registrado el sexo biológico, que se necesita para calcular la función renal. Complétalo en la ficha del paciente antes de aplicar el seguimiento.',
                );
            }
        });
    }
}
