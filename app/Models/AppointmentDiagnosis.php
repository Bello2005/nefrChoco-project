<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Diagnóstico codificado de una atención (CIE-10 y, en la transición, CIE-11).
 *
 * Contenido clínico: cifrado y con LogsChangedFields. No se edita ni se borra
 * después del cierre de la atención.
 */
class AppointmentDiagnosis extends Model
{
    use LogsChangedFields;

    public const ROLE_PRINCIPAL = 'principal';

    public const ROLE_RELATED = 'relacionado';

    protected $table = 'appointment_diagnoses';

    /** appointment_id, author_id, replaces_id y la aclaración los fija AttentionRecordService. */
    protected $fillable = [
        'cie10_code',
        'cie11_code',
        'role',
        'diagnosis_type',
    ];

    protected function casts(): array
    {
        return [
            'cie10_code' => 'encrypted',
            'cie11_code' => 'encrypted',
            'diagnosis_type' => 'encrypted',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function replaces(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_id');
    }
}
