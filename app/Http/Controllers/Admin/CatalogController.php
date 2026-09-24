<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CodeSystem;
use App\Services\Catalogs\CodeCatalog;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Catálogos": qué catálogos oficiales hay importados, de qué versión y
 * cuántos códigos vigentes tienen. Solo lectura: se importan por comando.
 */
class CatalogController extends Controller
{
    public function index(CodeCatalog $catalog): Response
    {
        $systems = CodeSystem::query()
            ->with('importer:id,name')
            ->withCount([
                'codes as active_count' => fn ($query) => $query->where('active', true),
                'codes as inactive_count' => fn ($query) => $query->where('active', false),
            ])
            ->orderBy('key')
            ->get()
            ->map(fn (CodeSystem $system) => [
                'key' => $system->key,
                'name' => $system->name,
                'version' => $system->version,
                'source' => $system->source,
                'sha256' => $system->source_sha256,
                'importedAt' => $system->imported_at,
                'importedBy' => $system->importer?->name,
                'activeCount' => $system->active_count,
                'inactiveCount' => $system->inactive_count,
            ]);

        $known = collect(config('catalogs.systems'))
            ->map(fn (array $system, string $key) => ['key' => $key, 'name' => $system['name']])
            ->values();

        return Inertia::render('admin/catalogos/index', [
            'systems' => $systems,
            'expected' => $known,
            'searchMode' => $catalog->searchMode(),
        ]);
    }
}
