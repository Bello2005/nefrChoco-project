<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EcntCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\EducationalContent\StoreEducationalContentRequest;
use App\Http\Requests\EducationalContent\UpdateEducationalContentRequest;
use App\Models\EducationalContent;
use App\Services\EducationalContentService;
use Inertia\Inertia;
use Inertia\Response;

class EducationalContentController extends Controller
{
    public function __construct(
        private readonly EducationalContentService $educationalContentService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/educativo/index', [
            'contents' => EducationalContent::latest()->get(),
            'categories' => EcntCategory::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/educativo/create', [
            'categories' => EcntCategory::options(),
        ]);
    }

    public function store(StoreEducationalContentRequest $request)
    {
        $this->educationalContentService->create($request->validated());

        return to_route('admin.educativo.index')->with('success', 'Contenido educativo creado correctamente.');
    }

    public function edit(EducationalContent $educativo): Response
    {
        return Inertia::render('admin/educativo/edit', [
            'content' => $educativo,
            'categories' => EcntCategory::options(),
        ]);
    }

    public function update(UpdateEducationalContentRequest $request, EducationalContent $educativo)
    {
        $this->educationalContentService->update($educativo, $request->validated());

        return to_route('admin.educativo.index')->with('success', 'Contenido actualizado correctamente.');
    }

    public function destroy(EducationalContent $educativo)
    {
        $this->educationalContentService->delete($educativo);

        return to_route('admin.educativo.index')->with('success', 'Contenido eliminado correctamente.');
    }
}
