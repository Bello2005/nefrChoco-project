<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Patient extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'user_id',
        'consent_accepted_at',
        'consent_version',
        'full_name',
        'document_type',
        'document_number',
        'birth_date',
        'municipality',
        'phone',
        'emergency_contact_name',
        'emergency_contact_phone',
    ];

    /**
     * Cifrado en reposo de datos sensibles (Ley 1581 de 2012).
     *
     * Quedan sin cifrar `full_name`, `document_number` y `municipality` a
     * propósito: el buscador de pacientes, el índice único del documento y el
     * reporte de cobertura territorial consultan esas columnas en SQL, y cada
     * cifrado usa un IV distinto, así que dos valores iguales no coinciden.
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'consent_accepted_at' => 'datetime',
            'phone' => 'encrypted',
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->logFillable()
            ->dontLogEmptyChanges();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Vigente solo si aceptó la versión de la política que rige hoy. */
    public function hasCurrentConsent(): bool
    {
        return $this->consent_accepted_at !== null
            && $this->consent_version === config('privacy.consent_version');
    }

    public function clinicalHistories(): HasMany
    {
        return $this->hasMany(ClinicalHistory::class);
    }

    public function latestClinicalHistory(): HasOne
    {
        return $this->hasOne(ClinicalHistory::class)->latestOfMany();
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function clinicalForms(): HasMany
    {
        return $this->hasMany(ClinicalForm::class);
    }

    public function vitalSigns(): HasMany
    {
        return $this->hasMany(VitalSign::class);
    }
}
