<?php

namespace App\Services;

use App\Models\ClinicalForm;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Registro de accesos a datos clínicos sensibles.
 *
 * La Ley 1581 de 2012 exige poder demostrar quién consultó datos de salud y
 * cuándo, no solo quién los modificó: la lectura no deja rastro por sí sola.
 *
 * Se audita toda pantalla que ponga contenido clínico frente a un profesional,
 * no únicamente la historia clínica: la ficha del paciente arrastra historias,
 * formularios y mediciones, y el telemonitoreo expone la serie completa de
 * signos vitales.
 *
 * En las propiedades va el nombre del paciente —que se guarda sin cifrar
 * porque se busca e indexa— y nunca contenido clínico: el registro de accesos
 * no puede convertirse en una copia sin cifrar de lo que se protegió.
 */
class ClinicalAccessAuditor
{
    public const LOG_NAME = 'acceso_clinico';

    public const RESOURCE_HISTORY = 'historia_clinica';

    public const RESOURCE_PATIENT_FILE = 'ficha';

    public const RESOURCE_CLINICAL_FORM = 'formulario_clinico';

    public const RESOURCE_MONITORING = 'telemonitoreo';

    public function recordHistoryAccess(ClinicalHistory $clinicalHistory, Request $request): void
    {
        $this->record(
            $clinicalHistory,
            self::RESOURCE_HISTORY,
            $clinicalHistory->patient->full_name,
            $request,
            'Consultó una historia clínica',
        );
    }

    public function recordPatientFileAccess(Patient $patient, Request $request): void
    {
        $this->record(
            $patient,
            self::RESOURCE_PATIENT_FILE,
            $patient->full_name,
            $request,
            'Consultó la ficha de un paciente',
        );
    }

    public function recordClinicalFormAccess(ClinicalForm $clinicalForm, Request $request): void
    {
        $this->record(
            $clinicalForm,
            self::RESOURCE_CLINICAL_FORM,
            $clinicalForm->patient?->full_name ?? '',
            $request,
            'Consultó un formulario clínico',
        );
    }

    public function recordMonitoringAccess(Patient $patient, Request $request): void
    {
        $this->record(
            $patient,
            self::RESOURCE_MONITORING,
            $patient->full_name,
            $request,
            'Consultó el telemonitoreo de un paciente',
        );
    }

    private function record(Model $subject, string $resource, string $patientName, Request $request, string $description): void
    {
        activity(self::LOG_NAME)
            ->causedBy($request->user())
            ->performedOn($subject)
            ->withProperties([
                'paciente' => $patientName,
                'recurso' => $resource,
                'ip' => $request->ip(),
                'parcial' => $this->isPartialReload($request),
            ])
            ->event('consultado')
            ->log($description);
    }

    /**
     * Recarga parcial de Inertia.
     *
     * Se registra igual que una lectura completa: la respuesta devuelve las
     * props que se le piden, así que el dato clínico vuelve a viajar al cliente
     * y no registrarlo dejaría un punto ciego alcanzable con solo forzar
     * recargas parciales. Se marca aparte para que en la auditoría se distinga
     * un refresco de una consulta nueva.
     */
    private function isPartialReload(Request $request): bool
    {
        return $request->hasHeader('X-Inertia-Partial-Data');
    }
}
