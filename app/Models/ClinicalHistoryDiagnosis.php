<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diagnóstico CIE-10 opcional de una entrada de historia (además de ecnt_diagnosis).
 *
 * Contenido clínico: cifrado y con LogsChangedFields. No se edita ni se borra
 * después del cierre de la atención.
 */
class ClinicalHistoryDiagnosis extends Model
{
    use LogsChangedFields;

    protected $fillable = [
        'cie10_code',
    ];

    protected function casts(): array
    {
        return [
            'cie10_code' => 'encrypted',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
