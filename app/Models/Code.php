<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function codeSystem(): BelongsTo
    {
        return $this->belongsTo(CodeSystem::class);
    }
}
