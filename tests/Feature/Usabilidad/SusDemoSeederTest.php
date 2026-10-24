<?php

use App\Models\SusResponse;
use App\Models\User;
use App\Support\SusInstrument;
use Database\Seeders\RoleSeeder;
use Database\Seeders\SusDemoSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('los datos de demo llenan el reporte con respuestas de médicos y pacientes', function () {
    $this->seed(SusDemoSeeder::class);

    expect(SusResponse::where('role', 'medico')->count())->toBe(6);
    expect(SusResponse::where('role', 'paciente')->count())->toBe(6);

    SusResponse::all()->each(function (SusResponse $response) {
        expect($response->answers)->toHaveCount(10);
        expect(collect($response->answers)->every(fn (int $answer) => $answer >= 1 && $answer <= 5))->toBeTrue();
        expect($response->score)->toBe(SusInstrument::score($response->answers));
    });

    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get(route('admin.usabilidad.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('total', 12)->has('byRole', 2));
});

test('las cuentas de las respuestas de demo no pueden iniciar sesión', function () {
    $this->seed(SusDemoSeeder::class);

    expect(User::whereHas('roles')->whereNull('deactivated_at')->count())->toBe(0);
});

test('correr los datos de demo dos veces no duplica respuestas', function () {
    $this->seed(SusDemoSeeder::class);
    $this->seed(SusDemoSeeder::class);

    expect(SusResponse::count())->toBe(12);
});

test('los datos de demo se niegan a correr en producción', function () {
    app()['env'] = 'production';

    // Se llama directo: db:seed pediría confirmación en producción y la prueba se quedaría esperando.
    expect(fn () => app(SusDemoSeeder::class)->run())->toThrow(RuntimeException::class);
    expect(SusResponse::count())->toBe(0);
});

test('en el VPS de pruebas, que corre como producción, los datos de demo se permiten con el interruptor', function () {
    app()['env'] = 'production';
    config(['nefrochoco.allow_demo_data' => true]);

    app(SusDemoSeeder::class)->run();

    expect(SusResponse::count())->toBe(12);
});
