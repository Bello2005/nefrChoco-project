<?php

/**
 * Contraste de color según WCAG 2.1 nivel AA.
 *
 * La tesis mide usabilidad, y el contraste es lo único de accesibilidad que se
 * puede verificar de forma exacta en vez de por apreciación. Este test lee los
 * tokens del CSS y calcula la relación real, así que una regresión de color
 * falla aquí y no se descubre el día de la sustentación.
 *
 * Umbral 4.5:1 para texto normal. Los iconos sobre fondo suave usan los mismos
 * tokens que el texto, así que se les exige lo mismo y no el 3:1 de gráficos.
 */
const UMBRAL_AA = 4.5;

function tokensDeTema(string $selector): array
{
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/app.css');

    // Bloque del selector hasta su llave de cierre a comienzo de línea.
    preg_match('/'.preg_quote($selector, '/').'\s*\{(.*?)\n\}/s', $css, $bloque);

    preg_match_all(
        '/--([a-z-]+):\s*hsl\(\s*([\d.]+)\s+([\d.]+)%\s+([\d.]+)%\s*\)/',
        $bloque[1] ?? '',
        $matches,
        PREG_SET_ORDER,
    );

    $tokens = [];

    foreach ($matches as [, $nombre, $h, $s, $l]) {
        $tokens[$nombre] = [(float) $h, (float) $s, (float) $l];
    }

    return $tokens;
}

function aRgb(array $hsl): array
{
    [$h, $s, $l] = [$hsl[0], $hsl[1] / 100, $hsl[2] / 100];

    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    $rgb = match (true) {
        $h < 60 => [$c, $x, 0],
        $h < 120 => [$x, $c, 0],
        $h < 180 => [0, $c, $x],
        $h < 240 => [0, $x, $c],
        $h < 300 => [$x, 0, $c],
        default => [$c, 0, $x],
    };

    return array_map(fn (float $canal) => $canal + $m, $rgb);
}

function luminancia(array $rgb): float
{
    $lineal = array_map(
        fn (float $v) => $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4,
        $rgb,
    );

    return 0.2126 * $lineal[0] + 0.7152 * $lineal[1] + 0.0722 * $lineal[2];
}

function contraste(array $frente, array $fondo): float
{
    $a = luminancia(aRgb($frente));
    $b = luminancia(aRgb($fondo));

    return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
}

/** @return array<int, array{0: string, 1: string}> */
function paresDeTexto(): array
{
    return [
        // Texto de la interfaz sobre el lienzo.
        ['foreground', 'background'],
        ['muted-foreground', 'background'],
        ['muted-foreground', 'card'],

        // Etiquetas e iconos semánticos sobre su fondo suave.
        ['brand-strong', 'brand-soft'],
        ['success', 'success-soft'],
        ['warning', 'warning-soft'],
        ['info', 'info-soft'],
        ['destructive', 'destructive-soft'],
        ['accent-foreground', 'primary-soft'],

        // Rellenos sólidos con el texto que llevan encima.
        ['primary-foreground', 'primary'],
        ['brand-foreground', 'brand'],
        ['success-foreground', 'success'],
        ['warning-foreground', 'warning'],
        ['info-foreground', 'info'],
        ['destructive-foreground', 'destructive'],
    ];
}

test('el tema claro cumple el contraste AA en todos sus pares', function () {
    $tokens = tokensDeTema(':root');

    foreach (paresDeTexto() as [$frente, $fondo]) {
        expect($tokens)->toHaveKey($frente);
        expect($tokens)->toHaveKey($fondo);

        $ratio = contraste($tokens[$frente], $tokens[$fondo]);

        expect($ratio)->toBeGreaterThanOrEqual(
            UMBRAL_AA,
            sprintf('%s sobre %s da %.2f:1 y AA exige %s:1', $frente, $fondo, $ratio, UMBRAL_AA),
        );
    }
});

test('el tema oscuro cumple el contraste AA en todos sus pares', function () {
    $claro = tokensDeTema(':root');
    // El tema oscuro solo redefine parte de los tokens; el resto los hereda.
    $tokens = array_merge($claro, tokensDeTema('.dark'));

    foreach (paresDeTexto() as [$frente, $fondo]) {
        $ratio = contraste($tokens[$frente], $tokens[$fondo]);

        expect($ratio)->toBeGreaterThanOrEqual(
            UMBRAL_AA,
            sprintf('%s sobre %s da %.2f:1 y AA exige %s:1', $frente, $fondo, $ratio, UMBRAL_AA),
        );
    }
});

test('el coral conserva su viveza en vez de apagarse para contrastar', function () {
    $tokens = tokensDeTema(':root');

    // Si alguien "arregla" el contraste oscureciendo el coral en vez de usar
    // texto oscuro encima, la identidad visual se pierde en silencio.
    expect($tokens['brand'][2])->toBeGreaterThanOrEqual(55.0);
    expect($tokens['brand'][1])->toBeGreaterThanOrEqual(70.0);
});
