<?php

namespace App\Http\Controllers\Medico;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $dashboardService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('medico/dashboard', [
            ...$this->dashboardService->forDoctor(Auth::user()),
            'doctorName' => Auth::user()->name,
        ]);
    }
}
