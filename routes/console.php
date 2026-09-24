<?php

use App\Notifications\FollowUpDueNotification;
use App\Services\FollowUpScheduleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Recordatorio diario "Te toca medirte" (Res. 1644 de 2026, art. 19 par. 1).
// Mientras la frecuencia por riesgo siga sin validar (valores en null), no
// envía nada. Lo dispara el timer de schedule:run que instala deploy.sh.
Artisan::command('seguimiento:recordatorios', function (FollowUpScheduleService $schedule) {
    if (! $schedule->isActive()) {
        $this->info('La frecuencia de seguimiento no está configurada: no se envía nada.');

        return;
    }

    $sent = 0;

    foreach ($schedule->overduePatients() as $row) {
        $user = $row['patient']->user;

        // Un recordatorio por día como máximo, aunque el comando corra dos veces.
        $alreadyToday = $user?->notifications()
            ->where('type', FollowUpDueNotification::class)
            ->where('created_at', '>=', today())
            ->exists();

        if ($user === null || $alreadyToday) {
            continue;
        }

        $user->notify(new FollowUpDueNotification($row['overdue']->pluck('label')->all()));
        $sent++;
    }

    $this->info("Recordatorios enviados: {$sent}");
})->purpose('Avisa a los pacientes con el control remoto vencido');

Schedule::command('seguimiento:recordatorios')->dailyAt('07:00')->timezone('America/Bogota');
