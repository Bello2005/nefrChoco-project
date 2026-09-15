<?php

use App\Enums\VitalSignType;
use App\Models\Patient;
use App\Models\User;
use App\Models\VitalSign;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RoleSeeder::class);

    $this->user = User::factory()->create();
    $this->user->assignRole('paciente');
    $this->patient = Patient::factory()->create(['user_id' => $this->user->id]);
});

test('reenviar una medición con la misma clave de idempotencia no la duplica', function () {
    $clientUuid = Str::uuid()->toString();

    $payload = [
        'client_uuid' => $clientUuid,
        'type' => VitalSignType::Glucose->value,
        'value' => 132,
        'recorded_at' => now()->subHour()->format('Y-m-d H:i:s'),
    ];

    // Simula el caso real: el servidor guardó pero la respuesta nunca llegó al
    // dispositivo, así que la cola reintenta el mismo envío.
    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload)->assertOk();
    $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), $payload)->assertOk();

    expect(VitalSign::where('client_uuid', $clientUuid)->count())->toBe(1);
    expect(VitalSign::count())->toBe(1);
});

test('dos mediciones distintas de la cola se guardan por separado', function () {
    foreach ([120, 145] as $value) {
        $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), [
            'client_uuid' => Str::uuid()->toString(),
            'type' => VitalSignType::Glucose->value,
            'value' => $value,
            'recorded_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
        ])->assertOk();
    }

    expect(VitalSign::count())->toBe(2);
});

test('la sincronización responde JSON en vez de redirigir', function () {
    $response = $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), [
        'client_uuid' => Str::uuid()->toString(),
        'type' => VitalSignType::HeartRate->value,
        'value' => 78,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ]);

    $response->assertOk()->assertJsonStructure(['message']);
});

test('una medición inválida sincronizada devuelve 422 y no se guarda', function () {
    $response = $this->actingAs($this->user)->postJson(route('paciente.signos-vitales.store'), [
        'client_uuid' => Str::uuid()->toString(),
        'type' => 'tipo_inexistente',
        'value' => 78,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ]);

    // 422 permite a la cola descartar el envío en lugar de reintentarlo por siempre.
    $response->assertStatus(422);
    expect(VitalSign::count())->toBe(0);
});

test('una cuenta sin ficha de paciente recibe 422 al sincronizar', function () {
    $orphan = User::factory()->create();
    $orphan->assignRole('paciente');

    $this->actingAs($orphan)->postJson(route('paciente.signos-vitales.store'), [
        'client_uuid' => Str::uuid()->toString(),
        'type' => VitalSignType::Weight->value,
        'value' => 70,
        'recorded_at' => now()->format('Y-m-d H:i:s'),
    ])->assertStatus(422);

    expect(VitalSign::count())->toBe(0);
});
