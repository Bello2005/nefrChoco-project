<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentDiagnosis;
use App\Models\AppointmentMedication;
use App\Models\AppointmentProcedure;
use App\Models\PatientAllergy;
use App\Models\TeleconsultationClarification;
use App\Models\User;
use App\Rules\ActiveCode;
use App\Services\Catalogs\CodeCatalog;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro estructurado de la atención (RDA y RIPS): diagnósticos CIE-10 (y
 * CIE-11 en la transición), procedimientos CUPS, medicamentos, alergias,
 * motivo, finalidad y causa externa.
 *
 * Se escribe una sola vez, al cerrar la atención. Después solo se agregan
 * correcciones desde una aclaración (append-only).
 *
 * El diagnóstico principal es obligatorio cuando el catálogo CIE-10 está
 * importado. Si todavía no lo está, la atención se puede cerrar igual (con un
 * aviso en pantalla): bloquear el cierre de consultas por un archivo pendiente
 * dejaría a los médicos sin poder atender. El tablero de datos faltantes
 * cuenta esas atenciones sin diagnóstico.
 */
class AttentionRecordService
{
    public function __construct(
        private readonly CodeCatalog $catalog,
    ) {}

    public function codingAvailable(): bool
    {
        return $this->catalog->has('cie10');
    }

    /** Qué catálogos están importados, para que el formulario sepa qué mostrar. */
    public function availability(): array
    {
        return [
            'cie10' => $this->catalog->has('cie10'),
            'cie11' => $this->catalog->has('cie11'),
            'cups' => $this->catalog->has('cups'),
            'diagnosisType' => $this->catalogFor('diagnosis_type'),
            'purpose' => $this->catalogFor('purpose'),
            'externalCause' => $this->catalogFor('external_cause'),
        ];
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $hasCie10 = $this->catalog->has('cie10');

        return [
            'consultation_reason' => ['nullable', 'string', 'max:2000'],
            'purpose' => ['nullable', 'string', ...$this->codedRule('purpose')],
            'external_cause' => ['nullable', 'string', ...$this->codedRule('external_cause')],

            'diagnoses' => [$hasCie10 ? 'required' : 'nullable', 'array', 'max:10'],
            'diagnoses.*.cie10_code' => ['required', 'string', new ActiveCode('cie10')],
            'diagnoses.*.cie11_code' => $this->catalog->has('cie11') ? ['nullable', 'string', new ActiveCode('cie11')] : ['prohibited'],
            'diagnoses.*.role' => ['required', Rule::in([AppointmentDiagnosis::ROLE_PRINCIPAL, AppointmentDiagnosis::ROLE_RELATED])],
            'diagnoses.*.diagnosis_type' => ['nullable', 'string', ...$this->codedRule('diagnosis_type')],

            'procedures' => [$this->catalog->has('cups') ? 'nullable' : 'prohibited', 'array', 'max:20'],
            'procedures.*.cups_code' => ['required', 'string', new ActiveCode('cups')],
            'procedures.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],

            'medications' => ['nullable', 'array', 'max:20'],
            'medications.*.description' => ['required', 'string', 'max:500'],
            'medications.*.dose' => ['nullable', 'string', 'max:255'],
            'medications.*.frequency' => ['nullable', 'string', 'max:255'],
            // TODO: validar contra el catálogo oficial de medicamentos cuando exista.
            'medications.*.code' => ['nullable', 'string', 'max:50'],

            'no_known_allergies' => ['nullable', 'boolean'],
            'allergies' => ['nullable', 'array', 'max:20', 'prohibited_if:no_known_allergies,true,1'],
            'allergies.*.substance' => ['required', 'string', 'max:255'],
            'allergies.*.allergy_type' => ['nullable', 'string', 'max:100'],
            'allergies.*.status' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'diagnoses.required' => 'Registra el diagnóstico principal (CIE-10) para cerrar la atención.',
            'diagnoses.*.cie11_code.prohibited' => 'El catálogo CIE-11 todavía no está importado.',
            'procedures.prohibited' => 'El catálogo CUPS todavía no está importado.',
            'allergies.prohibited_if' => 'Marcaste "sin alergias conocidas": quita las alergias de la lista o desmarca la casilla.',
        ];
    }

    /** Exactamente un diagnóstico principal, si se registró alguno. */
    public function afterValidation(Validator $validator): void
    {
        $diagnoses = collect($validator->getData()['diagnoses'] ?? []);

        if ($diagnoses->isEmpty()) {
            return;
        }

        $principal = $diagnoses->where('role', AppointmentDiagnosis::ROLE_PRINCIPAL)->count();

        if ($principal !== 1) {
            $validator->errors()->add('diagnoses', $principal === 0
                ? 'Marca uno de los diagnósticos como principal.'
                : 'Solo puede haber un diagnóstico principal.');
        }
    }

    /** Guarda el registro de la atención. Se llama una vez, al cerrar. */
    public function record(Appointment $appointment, User $author, array $data): void
    {
        $appointment->forceFill([
            'consultation_reason' => $data['consultation_reason'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'external_cause' => $data['external_cause'] ?? null,
        ])->save();

        foreach ($data['diagnoses'] ?? [] as $diagnosis) {
            $this->newDiagnosis($appointment, $author, $diagnosis)->save();
        }

        foreach ($data['procedures'] ?? [] as $procedure) {
            $row = new AppointmentProcedure(['cups_code' => $procedure['cups_code'], 'quantity' => $procedure['quantity']]);
            $row->forceFill(['appointment_id' => $appointment->id, 'author_id' => $author->id])->save();
        }

        foreach ($data['medications'] ?? [] as $medication) {
            $row = new AppointmentMedication(array_intersect_key($medication, array_flip(['description', 'dose', 'frequency', 'code'])));
            $row->forceFill(['appointment_id' => $appointment->id, 'author_id' => $author->id])->save();
        }

        if (! empty($data['no_known_allergies'])) {
            $this->newAllergy($appointment, $author, ['no_known_allergies' => true])->save();
        }

        foreach ($data['allergies'] ?? [] as $allergy) {
            $this->newAllergy($appointment, $author, array_intersect_key($allergy, array_flip(['substance', 'allergy_type', 'status'])))->save();
        }
    }

    /** @return array<string, mixed> */
    public function correctionRules(Appointment $appointment): array
    {
        $current = $this->currentDiagnoses($appointment)->pluck('id')->all();

        return [
            'corrected_diagnosis' => ['nullable', 'array'],
            'corrected_diagnosis.replaces_id' => ['required_with:corrected_diagnosis', 'integer', Rule::in($current)],
            'corrected_diagnosis.cie10_code' => ['required_with:corrected_diagnosis', 'string', new ActiveCode('cie10')],
            'corrected_diagnosis.cie11_code' => $this->catalog->has('cie11') ? ['nullable', 'string', new ActiveCode('cie11')] : ['prohibited'],
        ];
    }

    /**
     * Agrega un diagnóstico corregido desde una aclaración. No toca el
     * anterior: la fila nueva lo reemplaza para efectos de reportes y conserva
     * su mismo rol.
     */
    public function addCorrection(TeleconsultationClarification $clarification, User $author, array $correction): AppointmentDiagnosis
    {
        $appointment = $clarification->teleconsultation->appointment;
        $replaced = AppointmentDiagnosis::where('appointment_id', $appointment->id)->findOrFail($correction['replaces_id']);

        $row = $this->newDiagnosis($appointment, $author, [
            'cie10_code' => $correction['cie10_code'],
            'cie11_code' => $correction['cie11_code'] ?? null,
            'role' => $replaced->role,
            'diagnosis_type' => $replaced->diagnosis_type,
        ]);
        $row->forceFill(['replaces_id' => $replaced->id, 'teleconsultation_clarification_id' => $clarification->id])->save();

        return $row;
    }

    /**
     * Diagnósticos vigentes: los que ninguna corrección reemplazó.
     *
     * @return Collection<int, AppointmentDiagnosis>
     */
    public function currentDiagnoses(Appointment $appointment): Collection
    {
        $diagnoses = $appointment->relationLoaded('diagnoses') ? $appointment->diagnoses : $appointment->diagnoses()->get();
        $replaced = $diagnoses->pluck('replaces_id')->filter()->all();

        return $diagnoses->reject(fn (AppointmentDiagnosis $diagnosis) => in_array($diagnosis->id, $replaced, true))->values();
    }

    /**
     * Para mostrar en la historia, la ficha y la versión imprimible.
     *
     * @return list<array<string, mixed>>
     */
    public function presentDiagnoses(Appointment $appointment): array
    {
        $current = $this->currentDiagnoses($appointment)->pluck('id')->all();

        return $appointment->diagnoses->map(fn (AppointmentDiagnosis $diagnosis) => [
            'id' => $diagnosis->id,
            'code' => $diagnosis->cie10_code,
            'display' => $this->catalog->display('cie10', $diagnosis->cie10_code),
            'cie11Code' => $diagnosis->cie11_code,
            'cie11Display' => $this->catalog->display('cie11', $diagnosis->cie11_code),
            'role' => $diagnosis->role,
            'isCurrent' => in_array($diagnosis->id, $current, true),
            'isCorrection' => $diagnosis->replaces_id !== null,
            'authorName' => $diagnosis->author?->name,
            'createdAt' => $diagnosis->created_at,
        ])->all();
    }

    private function newDiagnosis(Appointment $appointment, User $author, array $data): AppointmentDiagnosis
    {
        $row = new AppointmentDiagnosis([
            'cie10_code' => $data['cie10_code'],
            'cie11_code' => $data['cie11_code'] ?? null,
            'role' => $data['role'],
            'diagnosis_type' => $data['diagnosis_type'] ?? null,
        ]);

        return $row->forceFill(['appointment_id' => $appointment->id, 'author_id' => $author->id]);
    }

    private function newAllergy(Appointment $appointment, User $author, array $data): PatientAllergy
    {
        return (new PatientAllergy($data))->forceFill([
            'patient_id' => $appointment->patient_id,
            'appointment_id' => $appointment->id,
            'author_id' => $author->id,
        ]);
    }

    private function catalogFor(string $field): ?string
    {
        $system = config("catalogs.attention_fields.{$field}");

        return $system !== null && $this->catalog->has($system) ? $system : null;
    }

    /** @return list<mixed> */
    private function codedRule(string $field): array
    {
        $system = $this->catalogFor($field);

        // TODO: sin catálogo oficial importado (Res. 948), texto libre.
        return $system !== null ? [new ActiveCode($system)] : ['max:255'];
    }
}
