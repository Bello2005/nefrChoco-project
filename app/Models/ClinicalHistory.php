<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class ClinicalHistory extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

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
}
