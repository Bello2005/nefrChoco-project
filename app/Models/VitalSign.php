<?php

namespace App\Models;

use App\Enums\VitalSignType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class VitalSign extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'client_uuid',
        'recorded_by',
        'type',
        'value',
        'unit',
        'recorded_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'recorded_at' => 'datetime',
            // El valor y el tipo se grafican y comparan, así que no se cifran;
            // la nota libre del paciente sí es contenido sensible.
            'notes' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->dontLogEmptyChanges();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function typeEnum(): ?VitalSignType
    {
        return VitalSignType::tryFrom($this->type);
    }

    /** 'normal', 'bajo' o 'alto' según el rango de referencia del tipo de signo. */
    public function status(): string
    {
        return $this->typeEnum()?->evaluate((float) $this->value) ?? 'normal';
    }

    public function isOutOfRange(): bool
    {
        return $this->status() !== 'normal';
    }
}
