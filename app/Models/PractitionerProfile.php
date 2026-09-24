<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Datos profesionales de un médico (1:1 con users de rol medico).
 *
 * La verificación en RETHUS la hace una persona en la consulta pública de
 * ReTHUS: aquí solo queda quién la hizo, cuándo y qué anotó. No hay consultas
 * automáticas ni scraping. Esos tres campos quedan fuera de $fillable: solo
 * los escribe PractitionerProfileService.
 */
class PractitionerProfile extends Model
{
    use HasFactory, LogsChangedFields;

    protected $fillable = [
        'document_type',
        'document_number',
        'profession',
        'professional_registration',
        'specialty',
    ];

    protected function casts(): array
    {
        return [
            'profession' => 'encrypted',
            'professional_registration' => 'encrypted',
            'specialty' => 'encrypted',
            'rethus_note' => 'encrypted',
            'rethus_verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rethus_verified_by');
    }

    /** Lo que exige el RDA para identificar al profesional. La especialidad es opcional. */
    public function isComplete(): bool
    {
        return filled($this->document_type)
            && filled($this->document_number)
            && filled($this->profession)
            && filled($this->professional_registration);
    }

    /** @return list<string> */
    public function missingFields(): array
    {
        return collect([
            'document_type' => 'tipo de documento',
            'document_number' => 'número de documento',
            'profession' => 'profesión',
            'professional_registration' => 'registro profesional',
        ])->filter(fn (string $label, string $field) => blank($this->{$field}))->values()->all();
    }
}
