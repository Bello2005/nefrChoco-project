<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Teleconsultation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pendiente';

    public const STATUS_IN_PROGRESS = 'en_curso';

    public const STATUS_FINISHED = 'finalizada';

    protected $fillable = [
        'appointment_id',
        'room_name',
        'status',
        'notes',
    ];

    /** Las notas de la consulta son contenido clínico: van cifradas. */
    protected function casts(): array
    {
        return [
            'notes' => 'encrypted',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
