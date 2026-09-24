<?php

namespace App\Services\Catalogs;

/** Un código leído de un archivo oficial, antes de guardarlo. */
final class ParsedCode
{
    /** @param  array<string, mixed>  $extra */
    public function __construct(
        public readonly string $code,
        public readonly string $display,
        public readonly ?string $parentCode = null,
        public readonly array $extra = [],
        public readonly bool $active = true,
    ) {}
}
