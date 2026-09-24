<?php

namespace App\Models;

use App\Models\Concerns\LogsChangedFields;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aclaración a una nota de teleconsulta ya cerrada.
 *
 * Tampoco cambia una vez escrita: no tiene SoftDeletes ni rutas para editarla
 * o borrarla. La teleconsulta y el autor quedan fuera de $fillable porque los
 * fija TeleconsultationService, nunca lo que llegue en la petición.
 */
class TeleconsultationClarification extends Model
{
    use HasFactory, LogsChangedFields;

    protected $fillable = [
        'body',
    ];

    /** Es contenido clínico: va cifrado, igual que la nota que aclara. */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
        ];
    }

    public function teleconsultation(): BelongsTo
    {
        return $this->belongsTo(Teleconsultation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
