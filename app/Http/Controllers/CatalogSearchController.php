<?php

namespace App\Http\Controllers;

use App\Services\Catalogs\CodeCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Buscador de los catálogos oficiales para los formularios (médico y admin).
 *
 * Devuelve solo códigos activos y pocos resultados, porque viajan a un
 * teléfono con mala señal. Son datos públicos de referencia: no se audita la
 * lectura, pero sí se limita la frecuencia.
 */
class CatalogSearchController extends Controller
{
    public function __invoke(Request $request, string $sistema, CodeCatalog $catalog): JsonResponse
    {
        $query = (string) $request->query('q', '');

        if (mb_strlen(trim($query)) < 2) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => $catalog->search($sistema, $query)]);
    }
}
