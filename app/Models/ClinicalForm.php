<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use App\Support\ClinicalFormCatalog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalForm extends Model
{
    use HasFactory, LogsChangedFields, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'recorded_by',
        'form_type',
        'answers',
        'score',
        'risk_level',
        // Resultado del seguimiento renal, congelado al guardar el formulario.
        'egfr',
        'kdigo_g',
        'kdigo_a',
    ];

    protected function casts(): array
    {
        return [
            // Texto narrativo del paciente (síntomas, plan, barreras): ver la
            // migración 2026_09_21_130000_encrypt_clinical_forms_answers.
            'answers' => 'encrypted:array',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function templateName(): string
    {
        return ClinicalFormCatalog::find($this->form_type)['name'] ?? $this->form_type;
    }
}
