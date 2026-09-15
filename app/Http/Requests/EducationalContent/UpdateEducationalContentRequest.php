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
            'url_or_path' => ['required', 'url', 'max:2048'],
            'ecnt_category' => ['required', Rule::enum(EcntCategory::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'title' => 'título',
            'description' => 'descripción',
            'type' => 'tipo de contenido',
            'url_or_path' => 'enlace',
            'ecnt_category' => 'categoría',
        ];
    }
}
