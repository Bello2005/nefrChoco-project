<?php

use App\Enums\FollowUpRiskLevel;
use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Notifications\FollowUpDueNotification;
use App\Services\FollowUpScheduleService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Frecuencia mínima de seguimiento remoto por nivel de riesgo
 * (Res. 1644 de 2026, art. 19 par. 1). Los valores reales están en null
 * hasta que la médica los valide; aquí se usan valores de prueba.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->pacienteUser = User::factory()->create();
    $this->pacienteUser->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->pacienteUser->id]);
    $this->patient->forceFill(['follow_up_risk_level' => FollowUpRiskLevel::High->value])->save();
});

/** Valores de PRUEBA, no clínicos: alto exige glucemia cada 3 días. */
function configurarFrecuenciaDePrueba(): void
{
    config()->set('vital_signs.max_days_without_reading.alto.glucemia', 3);
}

function medicionDe(Patient $patient, int $daysAgo): void
{
    VitalSign::factory()->ofType(VitalSignType::Glucose)->create([
        'patient_id' => $patient->id,
        'recorded_at' => now()->subDays($daysAgo),
    ]);
}

test('con los valores en null la función queda inactiva y nadie aparece vencido', function () {
    $servicio = app(FollowUpScheduleService::class);

    expect($servicio->isActive())->toBeFalse();
    expect($servicio->overduePatients())->toBeEmpty();

    $this->artisan('seguimiento:recordatorios')->assertSuccessful();
    expect($this->pacienteUser->notifications()->count())->toBe(0);

    $this->actingAs($this->medico)
        ->get(route('medico.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('followUp.isActive', false)->where('followUp.overduePatients', []));
});

test('con valores de prueba se marca vencido solo lo que pasó el plazo', function () {
    configurarFrecuenciaDePrueba();
    $servicio = app(FollowUpScheduleService::class);

    // Sin ninguna medición: vencido.
    expect($servicio->overdueFor($this->patient)->pluck('type')->all())->toBe(['glucemia']);

    // Medición de hace 1 día: al día.
    medicionDe($this->patient, 1);
    expect($servicio->overdueFor($this->patient))->toBeEmpty();

    // Otro paciente con la última medición de hace 5 días: vencido.
    $otro = Patient::factory()->create();
    $otro->forceFill(['follow_up_risk_level' => 'alto'])->save();
    medicionDe($otro, 5);
    expect($servicio->overdueFor($otro)->first()['maxDays'])->toBe(3);

    // Un paciente sin nivel asignado nunca aparece.
    $sinNivel = Patient::factory()->create();
    expect($servicio->overdueFor($sinNivel))->toBeEmpty();

    expect($servicio->overduePatients()->pluck('patient.id')->all())->toBe([$otro->id]);
});

test('el paciente con el control vencido ve "Te toca medirte" y recibe un solo aviso al día', function () {
    configurarFrecuenciaDePrueba();

    $this->actingAs($this->pacienteUser)
        ->get(route('paciente.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('followUpDue', [VitalSignType::Glucose->label()]));

    $this->artisan('seguimiento:recordatorios')->assertSuccessful();
    $this->artisan('seguimiento:recordatorios')->assertSuccessful();

    $avisos = $this->pacienteUser->notifications()->where('type', FollowUpDueNotification::class)->get();
    expect($avisos)->toHaveCount(1);
    expect($avisos->first()->data['title'])->toBe('Te toca medirte');
});

test('solo el médico asigna el nivel de riesgo', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach ([$admin, $this->pacienteUser] as $usuario) {
        $this->actingAs($usuario)
            ->patch(route('medico.pacientes.riesgo-seguimiento', $this->patient), ['follow_up_risk_level' => 'bajo'])
            ->assertForbidden();
    }

    expect($this->patient->fresh()->followUpRiskLevel())->toBe(FollowUpRiskLevel::High);

    $this->actingAs($this->medico)
        ->patch(route('medico.pacientes.riesgo-seguimiento', $this->patient), ['follow_up_risk_level' => 'bajo'])
        ->assertSessionHasNoErrors();

    expect($this->patient->fresh()->followUpRiskLevel())->toBe(FollowUpRiskLevel::Low);
});

test('el nivel queda cifrado y la auditoría registra el cambio sin el valor', function () {
    $this->actingAs($this->medico)
        ->patch(route('medico.pacientes.riesgo-seguimiento', $this->patient), ['follow_up_risk_level' => 'medio']);

    $crudo = DB::table('patients')->where('id', $this->patient->id)->value('follow_up_risk_level');
    expect($crudo)->not->toBe('medio');

    $registro = Activity::where('subject_type', Patient::class)
        ->where('subject_id', $this->patient->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($registro->causer_id)->toBe($this->medico->id);
    expect($registro->properties['campos'])->toContain('follow_up_risk_level');
    expect(json_encode($registro->properties))->not->toContain('medio');
});

test('no se acepta un nivel inventado', function () {
    $this->actingAs($this->medico)
        ->patch(route('medico.pacientes.riesgo-seguimiento', $this->patient), ['follow_up_risk_level' => 'critico'])
        ->assertSessionHasErrors('follow_up_risk_level');
});
