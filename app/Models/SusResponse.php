<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SusResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'answers',
        'score',
        'comments',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'score' => 'float',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
