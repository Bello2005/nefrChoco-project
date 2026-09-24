<?php

namespace App\Services\Catalogs;

use App\Models\Code;
use App\Models\CodeSystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consulta de los catálogos oficiales importados.
 *
 * Lo usan la validación (regla ActiveCode), el buscador de los formularios y
 * los módulos que necesitan saber si un catálogo ya está disponible (por
 * ejemplo, el campo CIE-11 solo aparece si ese catálogo se importó).
 */
class CodeCatalog
{
    /** El catálogo existe y tiene al menos un código activo. */
    public function has(string $system): bool
    {
        return Code::query()
            ->whereHas('codeSystem', fn ($query) => $query->where('key', $system))
            ->where('active', true)
            ->exists();
    }

    public function isActive(string $system, ?string $code): bool
    {
        if ($code === null || $code === '') {
            return false;
        }

        return Code::query()
            ->whereHas('codeSystem', fn ($query) => $query->where('key', $system))
            ->where('code', $code)
            ->where('active', true)
            ->exists();
    }

    /** Nombre del código, aunque esté inactivo: los registros viejos lo necesitan. */
    public function display(string $system, ?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return Code::query()
            ->whereHas('codeSystem', fn ($query) => $query->where('key', $system))
            ->where('code', $code)
            ->value('display');
    }

    /**
     * Primeros códigos activos que coinciden con el texto, por código o por nombre.
     *
     * @return Collection<int, array{code: string, display: string}>
     */
    public function search(string $system, string $query, ?int $limit = null): Collection
    {
        $query = trim($query);
        $limit ??= (int) config('catalogs.search_limit', 20);

        $systemId = CodeSystem::where('key', $system)->value('id');

        if ($systemId === null || $query === '') {
            return collect();
        }

        $operator = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $escaped = addcslashes($query, '%_\\');

        return Code::query()
            ->where('code_system_id', $systemId)
            ->where('active', true)
            ->where(fn ($where) => $where
                ->where('code', $operator, $escaped.'%')
                ->orWhere('display', $operator, '%'.$escaped.'%'))
            // Primero lo que empieza por el código escrito: es lo que busca
            // quien ya conoce el código.
            ->orderByRaw('case when code '.$operator.' ? then 0 else 1 end', [$escaped.'%'])
            ->orderBy('code')
            ->limit($limit)
            ->get(['code', 'display'])
            ->map(fn (Code $code) => ['code' => $code->code, 'display' => $code->display]);
    }

    /** 'pg_trgm', 'ilike' o 'like': cómo está buscando el servidor. */
    public function searchMode(): string
    {
        if (DB::getDriverName() !== 'pgsql') {
            return 'like';
        }

        $hasTrigram = DB::table('pg_indexes')
            ->where('tablename', 'codes')
            ->where('indexname', 'codes_display_trgm_index')
            ->exists();

        return $hasTrigram ? 'pg_trgm' : 'ilike';
    }
}
