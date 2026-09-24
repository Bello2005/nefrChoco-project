<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'programada';

    public const STATUS_COMPLETED = 'completada';

    public const STATUS_CANCELLED = 'cancelada';

    public const STATUS_NO_SHOW = 'no_asistio';

    public const TYPE_IN_PERSON = 'presencial';

    public const TYPE_TELECONSULTATION = 'teleconsulta';

    /** Niveles de "Probar mi conexión" (resources/js/lib/connection-check.ts). */
    public const CONNECTION_VIDEO = 'video';

    public const CONNECTION_AUDIO_ONLY = 'solo_audio';

    public const CONNECTION_INSUFFICIENT = 'insuficiente';

    public const CONNECTION_LEVELS = [
        self::CONNECTION_VIDEO,
        self::CONNECTION_AUDIO_ONLY,
        self::CONNECTION_INSUFFICIENT,
    ];

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'scheduled_at',
        'status',
        'type',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'connection_check_at' => 'datetime',
            // Registro de la atención: contenido clínico, cifrado.
            'consultation_reason' => 'encrypted',
            'purpose' => 'encrypted',
            'external_cause' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function teleconsultation(): HasOne
    {
        return $this->hasOne(Teleconsultation::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(AppointmentDiagnosis::class)->oldest('id');
    }

    public function procedures(): HasMany
    {
        return $this->hasMany(AppointmentProcedure::class)->oldest('id');
    }

    public function medications(): HasMany
    {
        return $this->hasMany(AppointmentMedication::class)->oldest('id');
    }

    /**
     * La atención ya ocurrió y la cita queda fija.
     *
     * Se mira también la teleconsulta porque hay datos viejos en los que la
     * sala se cerró con notas pero la cita quedó en 'programada': por el
     * estado solo, esa cita se podría editar y sus notas cambiarían de dueño.
     */
    public function isAttended(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            || $this->teleconsultation?->status === Teleconsultation::STATUS_FINISHED;
    }
}
