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
                ->get(),
            'categories' => EcntCategory::options(),
            'filters' => ['categoria' => $category],
        ]);
    }
}
