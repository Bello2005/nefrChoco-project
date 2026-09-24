<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Un código de un catálogo oficial. Nunca se borra: si desaparece de una
 * versión nueva queda inactivo, porque puede haber registros que lo usan.
 */
class Code extends Model
{
    protected $fillable = [
        'code_system_id',
        'code',
        'display',
        'search_text',
        'parent_code',
        'active',
        'extra',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'extra' => 'array',
        ];
    }

    /**
     * Texto de búsqueda: código y nombre sin tildes y en minúsculas.
     *
     * Los archivos oficiales no son parejos: la CIE-10 de SISPRO viene sin
     * tildes ("CRONICA") y los CUPS con tildes ("PUNCIÓN"). Sin esto, quien
     * escribe "crónica" no encuentra la CIE-10 y quien escribe "puncion" no
     * encuentra los CUPS.
     */
    public static function searchText(string $code, ?string $display): string
    {
        return self::normalizeForSearch($code.' '.$display);
    }

    public static function normalizeForSearch(string $text): string
    {
        return Str::of($text)->ascii()->lower()->squish()->toString();
    }

    public function codeSystem(): BelongsTo
    {
        return $this->belongsTo(CodeSystem::class);
    }
}
