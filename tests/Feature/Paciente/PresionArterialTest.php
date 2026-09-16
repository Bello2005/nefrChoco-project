<?php

use App\Enums\VitalSignType;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use App\Notifications\VitalSignOutOfRangeNotification;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * Presión arterial como par sistólica/diastólica.
 *
 * Se guardan dos lecturas para no romper el modelo de "un tipo, un valor, un
 * rango", pero entran en un mismo envío: no existe el caso de que una
 * sincronice y la otra quede huérfana.
 */
beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->user->id]);

    $this->medico = User::factory()->create();
    $this->medico->assignRole('medico');
    Appointment::factory()->create(['patient_id' => $this->patient->id, 'doctor_id' => $this->medico->id]);
});

function envioDePresion(array $extra = []): array
{
    return array_merge([
        'type' => VitalSignType::BloodPressure->value,
        'value' => 128,
        'value_diastolic' => 82,
        'recorded_at' => now()->subHour()->format('Y-m-d H:i:s'),
    ], $extra);
}

test('registrar el par crea dos mediciones con sus tipos y unidades', function () {
    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), envioDePresion());

    expect(VitalSign::count())->toBe(2);

    $sistolica = VitalSign::where('type', VitalSignType::BloodPressure->value)->first();
    $diastolica = VitalSign::where('type', VitalSignType::BloodPressureDiastolic->value)->first();

    expect((float) $sistolica->value)->toBe(128.0);
    expect($sistolica->unit)->toBe('mmHg');
    expect((float) $diastolica->value)->toBe(82.0);
    expect($diastolica->unit)->toBe('mmHg');
    expect($diastolica->patient_id)->toBe($this->patient->id);
});

test('una diastólica igual o mayor que la sistólica es rechazada', function (float $diastolica) {
    $this->actingAs($this->user)
        ->post(route('paciente.signos-vitales.store'), envioDePresion(['value' => 120, 'value_diastolic' => $diastolica]))
        ->assertSessionHasErrors('value_diastolic');

    expect(VitalSign::count())->toBe(0);
})->with([
    'igual' => 120.0,
    'mayor' => 135.0,
]);

test('registrar solo la sistólica sigue creando una única medición', function () {
    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), [
        'type' => VitalSignType::BloodPressure->value,
        'value' => 125,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ]);

    expect(VitalSign::count())->toBe(1);
    expect(VitalSign::first()->type)->toBe(VitalSignType::BloodPressure->value);
});

test('si ambas cifras salen de rango llega un solo aviso al médico', function () {
    Notification::fake();

    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), envioDePresion([
        'value' => 165,
        'value_diastolic' => 105,
    ]));

    Notification::assertSentToTimes($this->medico, VitalSignOutOfRangeNotification::class, 1);
});

test('el aviso menciona las dos cifras cuando ambas salen de rango', function () {
    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), envioDePresion([
        'value' => 165,
        'value_diastolic' => 105,
    ]));

    $mensaje = $this->medico->notifications()->latest()->first()->data['message'];

    expect($mensaje)->toContain('sistólica');
    expect($mensaje)->toContain('diastólica');
    expect($mensaje)->toContain('165');
    expect($mensaje)->toContain('105');
});

test('si solo la diastólica sale de rango también se avisa', function () {
    Notification::fake();

    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), envioDePresion([
        'value' => 118,
        'value_diastolic' => 95,
    ]));

    Notification::assertSentToTimes($this->medico, VitalSignOutOfRangeNotification::class, 1);
});

test('un par dentro de rango no genera ningún aviso', function () {
    Notification::fake();

    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), envioDePresion([
        'value' => 118,
        'value_diastolic' => 72,
    ]));

    Notification::assertNothingSentTo($this->medico);
});

test('reenviar el mismo client_uuid no duplica ninguna de las dos filas', function () {
    $payload = envioDePresion(['client_uuid' => Str::uuid()->toString()]);

    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload)->assertOk();
    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload)->assertOk();

    expect(VitalSign::count())->toBe(2);
    expect(VitalSign::where('type', VitalSignType::BloodPressure->value)->count())->toBe(1);
    expect(VitalSign::where('type', VitalSignType::BloodPressureDiastolic->value)->count())->toBe(1);
});

test('un reenvío de la cola no vuelve a alertar por ninguna de las dos', function () {
    Notification::fake();

    $payload = envioDePresion([
        'client_uuid' => Str::uuid()->toString(),
        'value' => 170,
        'value_diastolic' => 110,
    ]);

    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload);
    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload);

    Notification::assertSentToTimes($this->medico, VitalSignOutOfRangeNotification::class, 1);
});

test('las claves derivadas del par son distintas entre sí y deterministas', function () {
    $payload = envioDePresion(['client_uuid' => Str::uuid()->toString()]);

    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload);

    $claves = VitalSign::pluck('client_uuid');

    expect($claves)->toHaveCount(2);
    expect($claves->unique())->toHaveCount(2);
    // Ninguna coincide con el uuid original: son derivadas, no el mismo valor.
    expect($claves)->not->toContain($payload['client_uuid']);
});

test('la ficha del paciente grafica la sistólica y la diastólica como series separadas', function () {
    $this->actingAs($this->user)->post(route('paciente.signos-vitales.store'), envioDePresion());

    $this->actingAs($this->user)
        ->get(route('paciente.signos-vitales.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('paciente/signos-vitales/index')
            ->has('series', 2)
        );
});

test('la diastólica no se ofrece como tipo suelto en el formulario', function () {
    $valores = collect(VitalSignType::options())->pluck('value');

    expect($valores)->toContain(VitalSignType::BloodPressure->value);
    expect($valores)->not->toContain(VitalSignType::BloodPressureDiastolic->value);
});

test('la presión arterial expone su acompañante con su propio rango', function () {
    $presion = collect(VitalSignType::options())->firstWhere('value', VitalSignType::BloodPressure->value);

    expect($presion['companion']['value'])->toBe(VitalSignType::BloodPressureDiastolic->value);
    expect($presion['companion']['unit'])->toBe('mmHg');
    expect($presion['companion']['min'])->toBe(60.0);
    expect($presion['companion']['max'])->toBe(80.0);
});
