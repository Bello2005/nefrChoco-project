<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EducationalContent extends Model
{
    use HasFactory;

    public const TYPE_VIDEO = 'video';

    public const TYPE_PDF = 'pdf';

    public const TYPE_ARTICLE = 'articulo';

    protected $fillable = [
        'title',
        'description',
        'type',
        'url_or_path',
        'ecnt_category',
    ];
}
