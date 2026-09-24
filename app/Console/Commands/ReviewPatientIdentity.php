<?php

namespace App\Console\Commands;

use App\Services\PatientIdentityService;
use Illuminate\Console\Command;

/**
 * Vuelve a mapear tipo de documento y municipio contra los catálogos, por
 * ejemplo después de importar DIVIPOLA o los tipos de documento. Nunca pisa
 * lo que una persona completó en "Fichas por revisar".
 */
class ReviewPatientIdentity extends Command
{
    protected $signature = 'pacientes:revisar-identidad';

    protected $description = 'Mapea contra los catálogos lo que coincide exacto y marca el resto para revisión';

    public function handle(PatientIdentityService $identity): int
    {
        $result = $identity->backfillAll();

        $this->info("Fichas revisadas: {$result['reviewed']}. Pendientes de revisión: {$result['pending']}.");

        return self::SUCCESS;
    }
}
