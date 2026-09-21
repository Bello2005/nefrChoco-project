<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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
        'body',
        'available_offline',
        'url_or_path',
        'ecnt_category',
    ];

    protected function casts(): array
    {
        return [
            'available_offline' => 'boolean',
        ];
    }

    /**
     * El cuerpo se escribe en Markdown y se convierte aquí, con el HTML crudo
     * descartado (`html_input: strip`).
     *
     * Es la diferencia entre aceptar texto y aceptar código: sin esa opción, un
     * contenido guardado desde el panel podría inyectar <script> en la pantalla
     * de todos los pacientes. Markdown da negritas, listas y títulos, que es
     * todo lo que necesita una guía de salud.
     */
    protected function bodyHtml(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->body === null
            ? null
            : Str::markdown($this->body, [
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]));
    }

    /** Un contenido propio se lee dentro de la aplicación; un enlace se va a otro sitio. */
    public function hasOwnBody(): bool
    {
        return filled($this->body);
    }
}
