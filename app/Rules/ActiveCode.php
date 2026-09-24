<?php

namespace App\Rules;

use App\Services\Catalogs\CodeCatalog;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * El valor debe ser un código activo del catálogo oficial indicado.
 *
 * Los códigos inactivos se rechazan para registros nuevos, aunque sigan
 * existiendo para los registros viejos que ya los usan.
 */
class ActiveCode implements ValidationRule
{
    public function __construct(
        private readonly string $system,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! app(CodeCatalog::class)->isActive($this->system, $value)) {
            $fail('El código elegido no está en el catálogo oficial vigente.');
        }
    }
}
