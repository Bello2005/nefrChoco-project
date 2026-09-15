<?php

use App\Models\ClinicalHistory;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;

test('el contenido clínico se guarda cifrado en la base de datos', function () {
    $history = ClinicalHistory::factory()->create([
        'ecnt_diagnosis' => 'Diabetes mellitus tipo 2',
        'allergies' => 'Penicilina',
        'current_medication' => 'Metformina 850mg',
    ]);

    $raw = DB::table('clinical_histories')->where('id', $history->id)->first();

    // En reposo no debe quedar nada legible.
    expect($raw->ecnt_diagnosis)->not->toBe('Diabetes mellitus tipo 2');
    expect($raw->allergies)->not->toContain('Penicilina');
    expect($raw->current_medication)->not->toContain('Metformina');

    // Pero la aplicación lo lee transparentemente.
    expect($history->fresh()->ecnt_diagnosis)->toBe('Diabetes mellitus tipo 2');
    expect($history->fresh()->allergies)->toBe('Penicilina');
});

test('los datos de contacto del paciente se guardan cifrados', function () {
    $patient = Patient::factory()->create([
        'phone' => '3001234567',
        'emergency_contact_phone' => '3009876543',
    ]);

    $raw = DB::table('patients')->where('id', $patient->id)->first();

    expect($raw->phone)->not->toBe('3001234567');
    expect($raw->emergency_contact_phone)->not->toBe('3009876543');
    expect($patient->fresh()->phone)->toBe('3001234567');
});

test('el documento y el nombre quedan legibles porque se buscan e indexan', function () {
    $patient = Patient::factory()->create([
        'full_name' => 'Rosalba Mosquera',
        'document_number' => '1077445566',
    ]);

    $raw = DB::table('patients')->where('id', $patient->id)->first();

    // Decisión explícita: cifrarlos rompería el índice único y la búsqueda.
    expect($raw->document_number)->toBe('1077445566');
    expect($raw->full_name)->toBe('Rosalba Mosquera');

    expect(Patient::where('document_number', '1077445566')->exists())->toBeTrue();
});

test('dos pacientes con el mismo teléfono producen textos cifrados distintos', function () {
    $first = Patient::factory()->create(['phone' => '3001112233']);
    $second = Patient::factory()->create(['phone' => '3001112233']);

    $rawFirst = DB::table('patients')->where('id', $first->id)->value('phone');
    $rawSecond = DB::table('patients')->where('id', $second->id)->value('phone');

    // Cada cifrado usa un IV distinto: por eso estas columnas no se pueden comparar en SQL.
    expect($rawFirst)->not->toBe($rawSecond);
});
