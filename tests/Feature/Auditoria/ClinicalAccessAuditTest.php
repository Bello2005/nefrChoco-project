<?php

use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalAccessAuditor;
use Database\Seeders\RoleSeeder;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('consultar una historia clínica queda registrado en la auditoría', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $history = ClinicalHistory::factory()->create();

    $this->actingAs($medico)->get(route('historias-clinicas.show', $history))->assertOk();

    $activity = Activity::where('log_name', ClinicalAccessAuditor::LOG_NAME)->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('consultado');
    expect($activity->causer_id)->toBe($medico->id);
    expect($activity->subject_id)->toBe($history->id);
});

test('un acceso denegado no genera registro de auditoría', function () {
    $userA = User::factory()->create();
    $userA->assignRole('paciente');
    Patient::factory()->create(['user_id' => $userA->id]);

    $otherHistory = ClinicalHistory::factory()->create();

    $this->actingAs($userA)->get(route('historias-clinicas.show', $otherHistory))->assertForbidden();

    expect(Activity::where('log_name', ClinicalAccessAuditor::LOG_NAME)->count())->toBe(0);
});

test('crear un paciente queda registrado en el log de cambios', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->actingAs($medico)->post(route('medico.pacientes.store'), [
        'full_name' => 'Auditoría Prueba',
        'document_type' => 'CC',
        'document_number' => '1099887766',
        'birth_date' => '1990-01-01',
        'biological_sex' => 'femenino',
        'municipality' => 'Quibdó',
        'phone' => '3001112233',
    ]);

    $activity = Activity::where('subject_type', Patient::class)->latest()->first();

    expect($activity)->not->toBeNull();
    expect($activity->event)->toBe('created');
    expect($activity->causer_id)->toBe($medico->id);
});

test('un admin puede consultar el registro de auditoría', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('admin.auditoria.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/auditoria/index'));
});

test('un médico no puede consultar el registro de auditoría', function () {
    $medico = User::factory()->create();
    $medico->assignRole('medico');

    $this->actingAs($medico)->get(route('admin.auditoria.index'))->assertForbidden();
});

test('los enlaces de paginación del registro de auditoría están traducidos', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    // paginate(20): hacen falta más de 20 filas para que aparezcan enlaces de
    // «anterior»/«siguiente» y no solo números de página.
    Patient::factory()->count(21)->create();

    $this->actingAs($admin)
        ->get(route('admin.auditoria.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/auditoria/index')
            ->where('activities.links.0.label', '&laquo; Anterior')
            ->where('activities.links.3.label', 'Siguiente &raquo;')
        );
});
