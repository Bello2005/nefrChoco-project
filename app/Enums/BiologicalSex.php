<?php

namespace App\Enums;

/**
 * Sexo biológico registrado en la ficha del paciente.
 *
 * Tiene los cuatro valores del ValueSet IHCE-SexoBiologico-VS de la guía FHIR
 * del RDA (paquete co.gov.minsalud.rda 1.0.0), que usa el sistema estándar de
 * HL7 administrative-gender. El código FHIR de cada valor está en
 * config('catalogs.biological_sex_fhir').
 *
 * La fórmula de función renal (CKD-EPI 2021) solo contempla femenino y
 * masculino: con indeterminado o desconocido la TFGe no se calcula, igual que
 * cuando falta el dato, y la ficha explica por qué. No es un campo de
 * identidad de género (ese va aparte, en gender_identity).
 *
 * TODO: validar con la médica de la IPS el uso de indeterminado y desconocido.
 */
enum BiologicalSex: string
{
    case Female = 'femenino';
    case Male = 'masculino';
    case Indeterminate = 'indeterminado';
    case Unknown = 'desconocido';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Femenino',
            self::Male => 'Masculino',
            self::Indeterminate => 'Indeterminado / otro',
            self::Unknown => 'Desconocido',
        };
    }

    /** CKD-EPI 2021 solo tiene coeficientes para femenino y masculino. */
    public function supportsEgfr(): bool
    {
        return $this === self::Female || $this === self::Male;
    }

    /** Código del ValueSet IHCE-SexoBiologico-VS (HL7 administrative-gender). */
    public function fhirCode(): ?string
    {
        return config("catalogs.biological_sex_fhir.{$this->value}");
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $sex) => ['value' => $sex->value, 'label' => $sex->label()],
            self::cases(),
        );
    }
}
