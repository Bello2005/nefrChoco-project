<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Procedimiento CUPS de una atención.
 *
 * Contenido clínico: cifrado y con LogsChangedFields. No se edita ni se borra
 * después del cierre de la atención.
 */
class AppointmentProcedure extends Model
{
    use LogsChangedFields;

    protected $fillable = [
        'cups_code',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'cups_code' => 'encrypted',
            'quantity' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
