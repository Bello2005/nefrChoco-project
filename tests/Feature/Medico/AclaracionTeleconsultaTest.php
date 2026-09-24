<?php

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\TeleconsultationClarification;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

/*
 * Una nota cerrada no se reescribe: se corrige con aclaraciones, que quedan con
 * su autor y su fecha al lado de la nota original.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->pacienteUser = User::factory()->create();
    $this->pacienteUser->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->pacienteUser->id]);

    $this->cita = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => $this->patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
        'status' => Appointment::STATUS_COMPLETED,
    ]);
    $this->teleconsulta = Teleconsultation::factory()->create([
        'appointment_id' => $this->cita->id,
        'status' => Teleconsultation::STATUS_FINISHED,
        'notes' => 'Nota original de la consulta.',
    ]);
});

function aclarar(Appointment $cita, string $body = 'La dosis correcta es 50 mg, no 500 mg.')
{
    return test()->post(route('medico.citas.teleconsulta.aclaraciones.store', $cita), ['body' => $body]);
}

test('el médico de la cita agrega una aclaración a una teleconsulta cerrada y queda con su autor', function () {
    $this->actingAs($this->medico);

    aclarar($this->cita)->assertSessionHas('success', 'Aclaración agregada.');

    $aclaracion = TeleconsultationClarification::sole();
    expect($aclaracion->author_id)->toBe($this->medico->id);
    expect($aclaracion->teleconsultation_id)->toBe($this->teleconsulta->id);
    expect($aclaracion->body)->toBe('La dosis correcta es 50 mg, no 500 mg.');

    // La nota original no se toca.
    expect($this->teleconsulta->fresh()->notes)->toBe('Nota original de la consulta.');
});

test('otro médico no puede aclarar la nota', function () {
    $otro = User::factory()->create();
    $otro->assignRole('medico');

    $this->actingAs($otro);
    aclarar($this->cita)->assertForbidden();

    expect(TeleconsultationClarification::count())->toBe(0);
});

test('un paciente no puede agregar aclaraciones', function () {
    $this->actingAs($this->pacienteUser);
    aclarar($this->cita)->assertForbidden();

    expect(TeleconsultationClarification::count())->toBe(0);
});

test('no se aceptan aclaraciones antes de cerrar la teleconsulta', function () {
    $abierta = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => $this->patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);
    Teleconsultation::factory()->create(['appointment_id' => $abierta->id]);

    $this->actingAs($this->medico);
    aclarar($abierta)->assertForbidden();

    expect(TeleconsultationClarification::count())->toBe(0);
});

test('el texto de la aclaración se guarda cifrado en la base de datos', function () {
    $this->actingAs($this->medico);
    aclarar($this->cita, 'Paciente refiere alergia a la penicilina.');

    $raw = DB::table('teleconsultation_clarifications')->first();

    expect($raw->body)->not->toContain('penicilina');
    expect(TeleconsultationClarification::sole()->body)->toBe('Paciente refiere alergia a la penicilina.');
});

test('la auditoría registra el campo de la aclaración pero no su texto', function () {
    $this->actingAs($this->medico);
    aclarar($this->cita, 'Texto clínico que no debe quedar en la auditoría.');

    $registro = Activity::where('subject_type', TeleconsultationClarification::class)->latest('id')->first();

    expect($registro->event)->toBe('created');
    expect($registro->causer_id)->toBe($this->medico->id);
    expect($registro->properties['campos'])->toContain('body');
    expect(DB::table('activity_log')->pluck('properties')->implode(' '))->not->toContain('Texto clínico');
});

test('la historia muestra la aclaración con su autor, y solo el médico de la cita puede agregar otra', function () {
    $this->actingAs($this->medico);
    aclarar($this->cita, 'Aclaración visible para los dos.');

    $historia = ClinicalHistory::factory()->create(['patient_id' => $this->patient->id]);

    $otroMedico = User::factory()->create();
    $otroMedico->assignRole('medico');

    $esperado = [
        [$this->medico, true],
        [$otroMedico, false],
        [$this->pacienteUser, false],
    ];

    foreach ($esperado as [$usuario, $puedeAclarar]) {
        $this->actingAs($usuario)
            ->get(route('historias-clinicas.show', $historia))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('teleconsultationNotes.0.canClarify', $puedeAclarar)
                ->where('teleconsultationNotes.0.clarifications.0.body', 'Aclaración visible para los dos.')
                ->where('teleconsultationNotes.0.clarifications.0.authorName', $this->medico->name)
            );
    }
});

test('no existen rutas para editar ni borrar aclaraciones', function () {
    $rutasDeAclaraciones = collect(Route::getRoutes())
        ->filter(fn ($route) => str_contains($route->uri(), 'aclaraciones'));

    expect($rutasDeAclaraciones)->toHaveCount(1);
    expect($rutasDeAclaraciones->first()->methods())->toBe(['POST']);
});
