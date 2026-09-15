<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ClinicalAccessAuditor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->string('tipo')->toString();

        $activities = Activity::query()
            ->with('causer')
            ->when($filter === 'accesos', fn ($query) => $query->where('log_name', ClinicalAccessAuditor::LOG_NAME))
            ->when($filter === 'cambios', fn ($query) => $query->where('log_name', '!=', ClinicalAccessAuditor::LOG_NAME))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Activity $activity) => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'logName' => $activity->log_name,
                'subject' => class_basename($activity->subject_type ?? ''),
                'subjectId' => $activity->subject_id,
                'causer' => $activity->causer?->name ?? 'Sistema',
                'properties' => $activity->properties,
                'createdAt' => $activity->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/auditoria/index', [
            'activities' => $activities,
            'filters' => ['tipo' => $filter],
        ]);
    }
}
