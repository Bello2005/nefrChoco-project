<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\SusResponse;
use App\Support\SusInstrument;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class SusReportController extends Controller
{
    public function index(): Response
    {
        $responses = SusResponse::orderByDesc('created_at')->get();

        return Inertia::render('admin/usabilidad/index', [
            'total' => $responses->count(),
            'average' => $responses->isEmpty() ? null : round($responses->avg('score'), 1),
            'interpretation' => $responses->isEmpty() ? null : SusInstrument::interpret(round($responses->avg('score'), 1)),
            'referenceAverage' => SusInstrument::REFERENCE_AVERAGE,
            'byRole' => $this->byRole($responses),
            'items' => $this->itemBreakdown($responses),
            'distribution' => $this->distribution($responses),
            // Sin nombres a propósito: el reporte sirve para medir la plataforma,
            // no para saber quién se quejó de ella.
            'comments' => $responses->whereNotNull('comments')->map(fn (SusResponse $response) => [
                'id' => $response->id,
                'role' => $response->role,
                'score' => $response->score,
                'comments' => $response->comments,
                'createdAt' => $response->created_at->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * @param  Collection<int, SusResponse>  $responses
     * @return array<int, array{role: string, label: string, total: int, average: float}>
     */
    private function byRole(Collection $responses): array
    {
        return $responses
            ->groupBy('role')
            ->map(fn (Collection $group, string $role) => [
                'role' => $role,
                'label' => Role::tryFrom($role)?->label() ?? 'Sin rol',
                'total' => $group->count(),
                'average' => round($group->avg('score'), 1),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /**
     * Aporte medio de cada afirmación, de 0 a 4.
     *
     * Es lo que convierte el puntaje en algo accionable: un 62 no dice qué
     * arreglar, pero un ítem 4 con aporte 1.2 señala que la gente siente que
     * necesita ayuda técnica para usar la plataforma.
     *
     * @param  Collection<int, SusResponse>  $responses
     * @return array<int, array{item: int, statement: string, contribution: float|null}>
     */
    private function itemBreakdown(Collection $responses): array
    {
        return collect(SusInstrument::statements())
            ->map(fn (string $statement, int $item) => [
                'item' => $item,
                'statement' => $statement,
                'contribution' => $responses->isEmpty() ? null : round(
                    $responses->avg(fn (SusResponse $response) => $item % 2 === 1
                        ? $response->answers[$item] - SusInstrument::MIN_ANSWER
                        : SusInstrument::MAX_ANSWER - $response->answers[$item]),
                    2,
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SusResponse>  $responses
     * @return array<int, array{label: string, value: int}>
     */
    private function distribution(Collection $responses): array
    {
        $buckets = ['0–20' => 0, '21–40' => 0, '41–60' => 0, '61–80' => 0, '81–100' => 0];

        foreach ($responses as $response) {
            $bucket = match (true) {
                $response->score <= 20 => '0–20',
                $response->score <= 40 => '21–40',
                $response->score <= 60 => '41–60',
                $response->score <= 80 => '61–80',
                default => '81–100',
            };

            $buckets[$bucket]++;
        }

        return collect($buckets)->map(fn (int $value, string $label) => ['label' => $label, 'value' => $value])->values()->all();
    }
}
