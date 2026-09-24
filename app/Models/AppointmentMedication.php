<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Medicamento indicado en una atención. El código queda nullable hasta tener el catálogo oficial (TODO).
 *
 * Contenido clínico: cifrado y con LogsChangedFields. No se edita ni se borra
 * después del cierre de la atención.
 */
class AppointmentMedication extends Model
{
    use LogsChangedFields;

    protected $fillable = [
        'description',
        'dose',
        'frequency',
        'code',
    ];

    protected function casts(): array
    {
        return [
            'description' => 'encrypted',
            'dose' => 'encrypted',
            'frequency' => 'encrypted',
            'code' => 'encrypted',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
