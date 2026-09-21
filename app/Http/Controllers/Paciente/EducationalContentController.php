<?php

namespace App\Http\Controllers\Paciente;

use App\Enums\EcntCategory;
use App\Http\Controllers\Controller;
use App\Models\EducationalContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EducationalContentController extends Controller
{
    public function index(Request $request): Response
    {
        $category = $request->string('categoria')->toString();

        return Inertia::render('paciente/educativo/index', [
            'contents' => EducationalContent::query()
                ->when($category, fn ($query) => $query->where('ecnt_category', $category))
                ->latest()
                ->get()
                ->map(fn (EducationalContent $content) => [
                    'id' => $content->id,
                    'title' => $content->title,
                    'description' => $content->description,
                    'type' => $content->type,
                    'ecnt_category' => $content->ecnt_category,
                    'url_or_path' => $content->url_or_path,
                    'hasOwnBody' => $content->hasOwnBody(),
                    'availableOffline' => $content->available_offline,
                ]),
            'categories' => EcntCategory::options(),
            'filters' => ['categoria' => $category],
        ]);
    }

    public function show(EducationalContent $educativo): Response
    {
        abort_unless($educativo->hasOwnBody(), 404);

        return Inertia::render('paciente/educativo/show', [
            'content' => [
                'id' => $educativo->id,
                'title' => $educativo->title,
                'description' => $educativo->description,
                'ecnt_category' => $educativo->ecnt_category,
                'bodyHtml' => $educativo->body_html,
                'availableOffline' => $educativo->available_offline,
            ],
            'categoryLabel' => EcntCategory::tryFrom($educativo->ecnt_category)?->label() ?? $educativo->ecnt_category,
        ]);
    }
}
