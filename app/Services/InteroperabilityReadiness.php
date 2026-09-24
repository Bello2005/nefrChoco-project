<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\Appointment;
use App\Models\AppointmentDiagnosis;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Qué datos faltan para interoperar (RDA del IHCE, RIPS).
 *
 * Una regla por requisito, reutilizable: el tablero de admin la usa para
 * mostrar el avance, y la generación del RDA y de los RIPS la usará para
 * explicar por qué no puede generar un documento. Nunca devuelve contenido
 * clínico: solo nombres, qué campo falta y dónde completarlo.
 */
class InteroperabilityReadiness
{
    public function __construct(
        private readonly InstitutionService $institution,
        private readonly AttentionRecordService $attention,
    ) {}

    /**
     * Lo que exige el IHCE para identificar al paciente, más DIVIPOLA y EAPB.
     *
     * @return list<string>
     */
    public function missingForPatient(Patient $patient): array
    {
        return collect([
            'número de documento' => $patient->document_number,
            'tipo de documento' => $patient->document_type,
            'primer nombre' => $patient->first_name,
            'primer apellido' => $patient->first_surname,
            'sexo biológico' => $patient->biological_sex?->value,
            'municipio (DIVIPOLA)' => $patient->municipality_code,
            'EAPB' => $patient->eapb_code,
        ])->filter(fn ($value) => blank($value))->keys()->values()->all();
    }

    /** @return list<string> */
    public function missingForPractitioner(User $doctor): array
    {
        $profile = $doctor->practitionerProfile;
        $missing = $profile?->missingFields() ?? ['datos profesionales'];

        if ($profile?->rethus_verified_at === null) {
            $missing[] = 'verificación en RETHUS';
        }

        return $missing;
    }

    /**
     * Para el RDA basta con REPS y sede; el resto de los datos de la IPS se
     * muestran en "Datos de la institución".
     *
     * @return list<string>
     */
    public function missingForInstitution(): array
    {
        $data = $this->institution->data();

        return collect(['reps_code' => 'código de habilitación REPS', 'site_code' => 'código de sede'])
            ->filter(fn (string $label, string $key) => $data[$key] === null)
            ->values()
            ->all();
    }

    /** @return list<string> */
    public function missingForAppointment(Appointment $appointment): array
    {
        $hasPrincipal = $this->attention->currentDiagnoses($appointment)
            ->contains(fn (AppointmentDiagnosis $diagnosis) => $diagnosis->role === AppointmentDiagnosis::ROLE_PRINCIPAL);

        return $hasPrincipal ? [] : ['diagnóstico principal CIE-10'];
    }

    /**
     * Todo lo que impide generar el documento de una atención, agrupado por
     * dónde se completa. Es lo que mostrarán el RDA y los RIPS al fallar.
     *
     * @return array{patient: list<string>, practitioner: list<string>, institution: list<string>, attention: list<string>}
     */
    public function missingForDocument(Appointment $appointment): array
    {
        $appointment->loadMissing(['patient', 'doctor.practitionerProfile', 'diagnoses']);

        return [
            'patient' => $this->missingForPatient($appointment->patient),
            'practitioner' => $appointment->doctor ? $this->missingForPractitioner($appointment->doctor) : ['médico de la atención'],
            'institution' => $this->missingForInstitution(),
            'attention' => $this->missingForAppointment($appointment),
        ];
    }

    /**
     * Resumen para el tablero. Recorre en PHP porque varios datos van
     * cifrados (EAPB, datos profesionales) y no se pueden filtrar en SQL.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $patients = Patient::query()->orderBy('full_name')->get()
            ->map(fn (Patient $patient) => ['id' => $patient->id, 'name' => $patient->full_name, 'missing' => $this->missingForPatient($patient)])
            ->filter(fn (array $row) => $row['missing'] !== [])
            ->values();

        $practitioners = User::role(Role::Medico->value)->with('practitionerProfile')->orderBy('name')->get()
            ->map(fn (User $doctor) => ['id' => $doctor->id, 'name' => $doctor->name, 'missing' => $this->missingForPractitioner($doctor)])
            ->filter(fn (array $row) => $row['missing'] !== [])
            ->values();

        $attentions = Appointment::query()
            ->where('status', Appointment::STATUS_COMPLETED)
            ->with(['patient:id,full_name', 'doctor:id,name', 'diagnoses'])
            ->latest('scheduled_at')
            ->get()
            ->filter(fn (Appointment $appointment) => $this->missingForAppointment($appointment) !== [])
            ->values()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'patientName' => $appointment->patient?->full_name,
                'doctorName' => $appointment->doctor?->name,
                'scheduledAt' => $appointment->scheduled_at,
            ]);

        return [
            'patients' => $this->withCount($patients),
            'practitioners' => $this->withCount($practitioners),
            'institution' => $this->missingForInstitution(),
            'attentions' => $this->withCount($attentions),
        ];
    }

    /** @return array{count: int, items: Collection} */
    private function withCount(Collection $rows): array
    {
        // La lista se corta para no mandar cientos de filas a la pantalla; el
        // contador sí es el total.
        return ['count' => $rows->count(), 'items' => $rows->take(50)->values()];
    }
}
