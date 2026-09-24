<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Alergia registrada, o la marca explícita de "sin alergias conocidas".
 *
 * Contenido clínico: cifrado y con LogsChangedFields. No se edita ni se borra
 * después del cierre de la atención.
 */
class PatientAllergy extends Model
{
    use LogsChangedFields;

    protected $fillable = [
        'no_known_allergies',
        'substance',
        'allergy_type',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'no_known_allergies' => 'boolean',
            'substance' => 'encrypted',
            'allergy_type' => 'encrypted',
            'status' => 'encrypted',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
