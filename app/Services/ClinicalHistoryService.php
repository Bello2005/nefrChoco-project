<?php

namespace App\Services;

use App\Models\ClinicalHistory;
use App\Models\ClinicalHistoryDiagnosis;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicalHistoryService
{
    /**
     * Registra una entrada de historia con su autor.
     *
     * El autor se asocia aquí y no por asignación masiva, para que nadie
     * pueda firmar una entrada a nombre de otro profesional mandando
     * author_id en la petición.
     */
    public function create(Patient $patient, User $author, array $data): ClinicalHistory
    {
        // TODO doc: manual técnico — clinical_histories.author_id y el relleno desde activity_log.
        $diagnoses = $data['diagnoses'] ?? [];
        unset($data['diagnoses']);

        return DB::transaction(function () use ($patient, $author, $data, $diagnoses) {
            $history = $patient->clinicalHistories()->make($data);
            $history->author()->associate($author);
            $history->save();

            foreach ($diagnoses as $code) {
                (new ClinicalHistoryDiagnosis(['cie10_code' => $code]))
                    ->forceFill(['clinical_history_id' => $history->id, 'author_id' => $author->id])
                    ->save();
            }

            return $history;
        });
    }
}
