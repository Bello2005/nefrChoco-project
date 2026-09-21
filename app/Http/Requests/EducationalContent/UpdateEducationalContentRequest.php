<?php

namespace App\Http\Requests\EducationalContent;

use App\Enums\EcntCategory;
use App\Models\EducationalContent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEducationalContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'type' => ['required', Rule::in([
                EducationalContent::TYPE_VIDEO,
                EducationalContent::TYPE_PDF,
                EducationalContent::TYPE_ARTICLE,
            ])],
            // Cuerpo propio o enlace: uno de los dos, nunca ninguno. Es la
            // decisión que separa un material que viaja con la aplicación de uno
            // que solo existe mientras haya señal.
            'body' => ['nullable', 'string', 'max:'.config('nefrochoco.educational_content.max_body_characters'), 'required_without:url_or_path'],
            'url_or_path' => ['nullable', 'url', 'max:2048', 'required_without:body'],
            'available_offline' => ['boolean'],
            'ecnt_category' => ['required', Rule::enum(EcntCategory::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descripción',
            'type' => 'tipo de contenido',
            'body' => 'contenido',
            'url_or_path' => 'enlace',
            'available_offline' => 'disponible sin conexión',
            'ecnt_category' => 'categoría',
        ];
    }
}
