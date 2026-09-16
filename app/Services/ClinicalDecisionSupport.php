<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use App\Support\ClinicalRules\ClinicalRule;
use App\Support\ClinicalRules\HighDiabetesRiskWithEvidence;
use App\Support\ClinicalRules\KidneyFunctionDecline;
use App\Support\ClinicalRules\NonAdherentWithDiagnosedEcnt;
use App\Support\ClinicalRules\PatientSignals;
use App\Support\ClinicalRules\Recommendation;
use App\Support\ClinicalRules\SustainedOutOfRangeVitalSign;
use Illuminate\Support\Collection;

/**
 * Apoyo a decisiones clínicas por reglas explicables.
 *
 * No es analítica predictiva ni aprendizaje automático: es un conjunto de
 * reglas escritas que leen señales que el sistema ya calculó y explican, con el
 * dato a la vista, por qué sugieren revisar a alguien. Cualquier recomendación
 * se puede rastrear hasta la regla que la produjo.
 *
 * La decisión final siempre es del profesional; esto solo ordena a quién mirar
 * primero.
 */
class ClinicalDecisionSupport
{
    /** @var array<int, ClinicalRule> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new SustainedOutOfRangeVitalSign,
            new HighDiabetesRiskWithEvidence,
            new NonAdherentWithDiagnosedEcnt,
            new KidneyFunctionDecline,
        ];
    }

    /**
     * Recomendaciones para un paciente, de mayor a menor prioridad.
     *
     * @return Collection<int, Recommendation>
     */
    public function forPatient(Patient $patient): Collection
    {
        $patient->loadMissing(['clinicalForms', 'vitalSigns', 'latestClinicalHistory']);

        $signals = PatientSignals::for($patient, $this->renalControls($patient));

        return collect($this->rules)
            ->map(fn (ClinicalRule $rule) => $rule->evaluate($signals))
            ->filter()
            ->sortByDesc(fn (Recommendation $recommendation) => $recommendation->priority->weight())
            ->values();
    }

    /**
     * Controles renales del paciente, del más reciente al más antiguo por
     * fecha de laboratorio.
     *
     * Se consultan aparte en vez de filtrar la relación ya cargada: la ficha
     * del paciente solo trae los cinco formularios más recientes, y bastaría
     * con cinco instrumentos de otro tipo para que los controles renales
     * quedaran fuera y la regla dejara de ver la progresión.
     *
     * @return Collection<int, ClinicalForm>
     */
    private function renalControls(Patient $patient): Collection
    {
        return $patient->clinicalForms()
            ->where('form_type', ClinicalFormService::RENAL_FORM)
            ->whereNotNull('egfr')
            ->get()
            ->sortByDesc(fn (ClinicalForm $form) => $form->answers['fecha_laboratorio'] ?? '')
            ->values();
    }

    /**
     * Pacientes del médico que hoy ameritan revisarse primero.
     *
     * Se limita a quienes tienen cita con ese profesional: el padrón es
     * institucional, pero una franja de "revisa a estos primero" solo es útil
     * si son personas que esa persona efectivamente atiende.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function priorityPatients(User $doctor, int $limit = 4): Collection
    {
        $patients = Patient::query()
            ->whereIn('id', Appointment::query()
                ->where('doctor_id', $doctor->id)
                ->select('patient_id'))
            ->with(['clinicalForms', 'vitalSigns', 'latestClinicalHistory'])
            ->get();

        return $patients
            ->map(fn (Patient $patient) => [
                'patient' => $patient,
                'recommendations' => $this->forPatient($patient),
            ])
            ->filter(fn (array $row) => $row['recommendations']->isNotEmpty())
            ->sortByDesc(fn (array $row) => $row['recommendations']->first()->priority->weight())
            ->take($limit)
            ->map(fn (array $row) => [
                'patientId' => $row['patient']->id,
                'patientName' => $row['patient']->full_name,
                'municipality' => $row['patient']->municipality,
                'recommendations' => $row['recommendations']
                    ->map(fn (Recommendation $recommendation) => $recommendation->toArray())
                    ->all(),
            ])
            ->values();
    }
}
