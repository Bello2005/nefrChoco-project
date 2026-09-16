<?php

namespace App\Services;

use App\Enums\BiologicalSex;

/**
 * Tasa de filtración glomerular estimada (TFGe) y clasificación KDIGO.
 *
 * Servicio puro: no conoce Eloquent ni la base de datos. Recibe cifras y
 * devuelve cifras, para poder verificarlo contra la calculadora oficial.
 *
 * Ecuación: CKD-EPI 2021 de creatinina, sin coeficiente de raza.
 *
 *   TFGe = 142 × min(Scr/κ, 1)^α × max(Scr/κ, 1)^−1.200 × 0.9938^edad × 1.012 [si es mujer]
 *   κ = 0.7 (femenino) / 0.9 (masculino), con la creatinina en mg/dL
 *   α = −0.241 (femenino) / −0.302 (masculino)
 *
 * Fuente primaria: Inker LA et al., "New Creatinine- and Cystatin C–Based
 * Equations to Estimate GFR without Race", N Engl J Med 2021. Los coeficientes
 * se contrastaron además contra la página de la National Kidney Foundation
 * (kidney.org/ckd-epi-creatinine-equation-2021) y la del NIDDK, que coinciden.
 *
 * El resultado se redondea a entero porque así lo reporta la calculadora
 * oficial de la NKF y así lo informan los laboratorios: si la aplicación
 * mostrara 89,7 y clasificara como G2 mientras el laboratorio informa 90,
 * el profesional vería una contradicción sin explicación.
 */
final class EgfrCalculator
{
    private const MULTIPLICADOR = 142;

    private const EXPONENTE_SUPERIOR = -1.200;

    private const FACTOR_EDAD = 0.9938;

    private const FACTOR_FEMENINO = 1.012;

    /** κ de la ecuación, con la creatinina en mg/dL. */
    private const KAPPA = [
        BiologicalSex::Female->value => 0.7,
        BiologicalSex::Male->value => 0.9,
    ];

    /** α de la ecuación. */
    private const ALFA = [
        BiologicalSex::Female->value => -0.241,
        BiologicalSex::Male->value => -0.302,
    ];

    /** TFGe en mL/min/1,73 m². */
    public function estimate(float $serumCreatinine, int $ageInYears, BiologicalSex $sex): int
    {
        $kappa = self::KAPPA[$sex->value];
        $alfa = self::ALFA[$sex->value];

        $razon = $serumCreatinine / $kappa;

        $tfg = self::MULTIPLICADOR
            * pow(min($razon, 1), $alfa)
            * pow(max($razon, 1), self::EXPONENTE_SUPERIOR)
            * pow(self::FACTOR_EDAD, $ageInYears);

        if ($sex === BiologicalSex::Female) {
            $tfg *= self::FACTOR_FEMENINO;
        }

        return (int) round($tfg);
    }

    /** Categoría G de KDIGO según la TFGe. */
    public function gCategory(int $egfr): string
    {
        foreach (config('clinical_support.kidney.gfr_categories') as $categoria) {
            if ($egfr >= $categoria['min']) {
                return $categoria['category'];
            }
        }

        // El último tramo abre en cero, así que este punto no se alcanza salvo
        // que alguien deje la tabla de config incompleta.
        return 'G5';
    }

    /**
     * Categoría A de KDIGO según la relación albúmina/creatinina en mg/g.
     *
     * Devuelve null cuando no hay dato: en zona rural el examen de albuminuria
     * no siempre está disponible, y ausencia de dato no es lo mismo que A1.
     */
    public function aCategory(?float $albuminCreatinineRatio): ?string
    {
        if ($albuminCreatinineRatio === null) {
            return null;
        }

        $cortes = config('clinical_support.kidney.albuminuria_categories');

        if ($albuminCreatinineRatio < $cortes['a1_below']) {
            return 'A1';
        }

        if ($albuminCreatinineRatio <= $cortes['a2_up_to']) {
            return 'A2';
        }

        return 'A3';
    }
}
