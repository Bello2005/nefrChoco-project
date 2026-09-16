<?php

use App\Enums\BiologicalSex;
use App\Models\ClinicalForm;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\RoleSeeder;

/**
 * Serie de evolución de la TFGe en la ficha del paciente.
 *
 * El gráfico solo muestra la serie: no decide si hay alerta. Esa lectura la
 * hace la regla del apoyo a decisiones, no la visualización.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');

    $this->patient = Patient::factory()->create([
        'biological_sex' => BiologicalSex::Female,
        'birth_date' => now()->subYears(50)->format('Y-m-d'),
    ]);
});

function controlRenal(Patient $patient, User $medico, int $egfr, string $fechaLaboratorio, ?string $categoria = 'G2', array $extra = []): ClinicalForm
{
    return ClinicalForm::factory()->create(array_merge([
        'patient_id' => $patient->id,
        'recorded_by' => $medico->id,
        'form_type' => 'seguimiento_erc',
        'answers' => ['creatinina' => 1.1, 'fecha_laboratorio' => $fechaLaboratorio],
        'score' => null,
        'risk_level' => null,
        'egfr' => $egfr,
        'kdigo_g' => $categoria,
    ], $extra));
}

test('la ficha expone la serie de TFGe de todos los controles', function () {
    controlRenal($this->patient, $this->medico, 78, now()->subMonths(9)->format('Y-m-d'));
    controlRenal($this->patient, $this->medico, 71, now()->subMonths(5)->format('Y-m-d'));
    controlRenal($this->patient, $this->medico, 64, now()->subMonth()->format('Y-m-d'));

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('egfrSeries', 3)
            ->where('egfrSeries.0.value', 78)
            ->where('egfrSeries.2.value', 64)
        );
});

test('la serie se ordena por fecha de laboratorio, no por fecha de captura', function () {
    // Se carga primero un examen viejo y después uno más antiguo todavía: si se
    // ordenara por created_at, la serie quedaría al revés.
    controlRenal($this->patient, $this->medico, 60, now()->subMonths(2)->format('Y-m-d'));
    controlRenal($this->patient, $this->medico, 85, now()->subMonths(10)->format('Y-m-d'));

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertInertia(fn ($page) => $page
            ->where('egfrSeries.0.value', 85)
            ->where('egfrSeries.1.value', 60)
        );
});

test('la serie incluye la categoría KDIGO de cada control', function () {
    controlRenal($this->patient, $this->medico, 22, now()->subMonth()->format('Y-m-d'), 'G4', [
        'kdigo_a' => 'A3',
    ]);

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertInertia(fn ($page) => $page
            ->where('egfrSeries.0.category', 'G4')
            ->where('egfrSeries.0.albuminuria', 'A3')
        );
});

test('un paciente sin seguimiento renal no tiene serie', function () {
    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('egfrSeries', 0));
});

test('la serie ignora los formularios que no son de seguimiento renal', function () {
    controlRenal($this->patient, $this->medico, 70, now()->subMonth()->format('Y-m-d'));

    ClinicalForm::factory()->create([
        'patient_id' => $this->patient->id,
        'recorded_by' => $this->medico->id,
        'form_type' => 'adherencia_tratamiento',
    ]);

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertInertia(fn ($page) => $page->has('egfrSeries', 1));
});

test('la serie completa se muestra aunque supere los cinco formularios de la ficha', function () {
    // La ficha solo lista los cinco formularios más recientes; la evolución
    // renal necesita todos los controles.
    foreach (range(1, 7) as $mes) {
        controlRenal($this->patient, $this->medico, 90 - $mes, now()->subMonths(8 - $mes)->format('Y-m-d'));
    }

    $this->actingAs($this->medico)
        ->get(route('medico.pacientes.show', $this->patient))
        ->assertInertia(fn ($page) => $page
            ->has('egfrSeries', 7)
            ->has('clinicalForms', 5)
        );
});
