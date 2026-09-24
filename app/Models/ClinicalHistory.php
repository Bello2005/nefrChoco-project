<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalHistory extends Model
{
    use HasFactory, LogsChangedFields, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'medical_history',
        'ecnt_diagnosis',
        'allergies',
        'current_medication',
    ];

    /** Todo el contenido clínico va cifrado en reposo (Ley 1581 de 2012). */
    protected function casts(): array
    {
        return [
            'ecnt_diagnosis' => 'encrypted',
            'medical_history' => 'encrypted',
            'allergies' => 'encrypted',
            'current_medication' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Quién escribió la entrada. Queda fuera de $fillable: lo fija
     * ClinicalHistoryService con el usuario autenticado, nunca lo que llegue
     * en el formulario. Es null en entradas anteriores a este campo cuyo
     * autor no se pudo recuperar de la auditoría.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
