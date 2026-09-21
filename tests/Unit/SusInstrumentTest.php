<?php

use App\Support\SusInstrument;

/**
 * El puntaje SUS es el resultado que se reporta en el anteproyecto, así que la
 * fórmula se fija aquí contra sets de respuestas cuyo valor se puede verificar a
 * mano, no contra lo que hoy devuelva el código.
 */

/** @param  array<int, int>  $answers */
function respuestas(array $answers): array
{
    return array_combine(range(1, 10), $answers);
}

test('responder todo al extremo favorable da el puntaje máximo', function () {
    // Impares en 5 (aportan 4 cada uno) y pares en 1 (aportan 4 cada uno): 40 × 2.5.
    expect(SusInstrument::score(respuestas([5, 1, 5, 1, 5, 1, 5, 1, 5, 1])))->toBe(100.0);
});

test('responder todo al extremo desfavorable da cero', function () {
    expect(SusInstrument::score(respuestas([1, 5, 1, 5, 1, 5, 1, 5, 1, 5])))->toBe(0.0);
});

test('marcar la misma casilla en los diez ítems siempre da 50', function () {
    // La alternancia de sentido hace que una columna recta se anule a sí misma.
    // Es justo lo que el instrumento busca detectar, y por eso 50 no significa
    // "usabilidad media" sino "esta respuesta no dice nada".
    expect(SusInstrument::score(respuestas([1, 1, 1, 1, 1, 1, 1, 1, 1, 1])))->toBe(50.0);
    expect(SusInstrument::score(respuestas([3, 3, 3, 3, 3, 3, 3, 3, 3, 3])))->toBe(50.0);
    expect(SusInstrument::score(respuestas([5, 5, 5, 5, 5, 5, 5, 5, 5, 5])))->toBe(50.0);
});

test('un set mixto conocido da el puntaje calculado a mano', function () {
    // Impares: 4,4,4,4,4 → aportan 3 cada uno = 15
    // Pares:   2,2,2,3,2 → aportan 3,3,3,2,3   = 14
    // Suma 29 × 2.5 = 72.5
    expect(SusInstrument::score(respuestas([4, 2, 4, 2, 4, 2, 4, 3, 4, 2])))->toBe(72.5);
});

test('un set realista de valoración baja da el puntaje calculado a mano', function () {
    // Impares: 2,3,2,3,2 → aportan 1,2,1,2,1 = 7
    // Pares:   4,4,3,4,4 → aportan 1,1,2,1,1 = 6
    // Suma 13 × 2.5 = 32.5
    expect(SusInstrument::score(respuestas([2, 4, 3, 4, 2, 3, 3, 4, 2, 4])))->toBe(32.5);
});

test('la interpretación sitúa el puntaje contra el promedio de referencia', function () {
    expect(SusInstrument::interpret(90.0))->toBe('Excelente');
    expect(SusInstrument::interpret(72.5))->toBe('Buena');
    expect(SusInstrument::interpret(68.0))->toBe('Aceptable');
    expect(SusInstrument::interpret(60.0))->toBe('Pobre');
    expect(SusInstrument::interpret(30.0))->toBe('Inaceptable');
});
