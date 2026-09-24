<?php

use App\Models\Appointment;
use App\Models\ClinicalHistory;
use App\Models\Patient;
use App\Models\Teleconsultation;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * La base de datos es la última línea de defensa del registro clínico: aunque
 * alguien borre desde la consola o desde un código nuevo que se salte las
 * reglas de la aplicación, la base rechaza el borrado mientras haya historia
 * colgando, en vez de llevársela en cascada sin dejar rastro.
 *
 * Cada borrado va en su propia transacción: en PostgreSQL, un error dentro de
 * la transacción de la prueba la deja abortada, y las consultas siguientes
 * fallarían por eso y no por lo que se quiere comprobar.
 */

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    $this->patient = Patient::factory()->create();
});

test('borrar un usuario que tiene citas lo rechaza la base y la cita sigue ahí', function () {
    $cita = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => $this->patient->id,
    ]);

    expect(fn () => DB::transaction(fn () => $this->medico->delete()))->toThrow(QueryException::class);

    expect(Appointment::find($cita->id))->not->toBeNull();
});

test('borrar un usuario que registró signos vitales lo rechaza la base', function () {
    $registrador = User::factory()->create();
    VitalSign::factory()->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $registrador->id,
    ]);

    expect(fn () => DB::transaction(fn () => $registrador->delete()))->toThrow(QueryException::class);

    expect(VitalSign::where('recorded_by', $registrador->id)->exists())->toBeTrue();
});

test('borrar una cita que tiene teleconsulta lo rechaza la base', function () {
    $cita = Appointment::factory()->create([
        'doctor_id' => $this->medico->id,
        'patient_id' => $this->patient->id,
        'type' => Appointment::TYPE_TELECONSULTATION,
    ]);
    Teleconsultation::factory()->create(['appointment_id' => $cita->id]);

    expect(fn () => DB::transaction(fn () => $cita->delete()))->toThrow(QueryException::class);

    expect(Teleconsultation::where('appointment_id', $cita->id)->exists())->toBeTrue();
});

test('el borrado definitivo de una ficha con historia lo rechaza la base, y el lógico del admin sigue funcionando', function () {
    ClinicalHistory::factory()->create(['patient_id' => $this->patient->id]);

    expect(fn () => DB::transaction(fn () => $this->patient->forceDelete()))->toThrow(QueryException::class);

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->delete(route('admin.pacientes.destroy', $this->patient))
        ->assertRedirect(route('admin.pacientes.index'));

    expect($this->patient->fresh()->deleted_at)->not->toBeNull();
    expect(ClinicalHistory::where('patient_id', $this->patient->id)->exists())->toBeTrue();
});
