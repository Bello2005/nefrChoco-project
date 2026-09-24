<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un catálogo oficial importado (CIE-10, CUPS, DIVIPOLA...), con la versión y
 * la huella del archivo del que salió. Dato público de referencia.
 */
class CodeSystem extends Model
{
    protected $fillable = [
        'key',
        'name',
        'version',
        'source',
        'source_sha256',
        'imported_at',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'imported_at' => 'datetime',
        ];
    }

    public function codes(): HasMany
    {
        return $this->hasMany(Code::class);
    }

    public function importer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }
}
