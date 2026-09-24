<?php

namespace App\Models;

use App\Enums\BiologicalSex;
use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, LogsChangedFields, SoftDeletes;

    protected $fillable = [
        'user_id',
        'consent_accepted_at',
        'consent_version',
        'teleconsultation_consent_accepted_at',
        'teleconsultation_consent_version',
        'full_name',
        'document_type',
        'document_number',
        'birth_date',
        'biological_sex',
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
            'biological_sex' => BiologicalSex::class,
            'consent_accepted_at' => 'datetime',
            'teleconsultation_consent_accepted_at' => 'datetime',
            'teleconsultation_consent_revoked_at' => 'datetime',
            'phone' => 'encrypted',
            'emergency_contact_name' => 'encrypted',
            'emergency_contact_phone' => 'encrypted',
        ];
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

    /**
     * Autorización para ser atendido por videollamada (Resolución 1644 de 2026).
     *
     * Va aparte del consentimiento de datos: aceptar que traten tu información
     * no es lo mismo que aceptar que te atiendan sin examen físico y con una
     * conexión que puede cortarse a mitad de la consulta.
     */
    public function hasCurrentTeleconsultationConsent(): bool
    {
        // Retirada, no cuenta aunque la versión sea la vigente: la persona
        // tiene que volver a aceptar para entrar a una sala (art. 7).
        return $this->teleconsultation_consent_accepted_at !== null
            && $this->teleconsultation_consent_revoked_at === null
            && $this->teleconsultation_consent_version === config('privacy.teleconsultation_consent_version');
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
