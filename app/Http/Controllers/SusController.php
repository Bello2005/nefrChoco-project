<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sus\StoreSusResponseRequest;
use App\Models\SusResponse;
use App\Support\SusInstrument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SusController extends Controller
{
    public function create(): Response
    {
        $user = Auth::user();

        return Inertia::render('usabilidad/index', [
            'statements' => SusInstrument::statements(),
            'alreadyAnswered' => SusResponse::where('user_id', $user->id)->exists(),
        ]);
    }

    public function store(StoreSusResponseRequest $request): RedirectResponse
    {
        $user = Auth::user();

        // Una respuesta por persona: dos del mismo usuario moverían el promedio
        // sin que haya más gente evaluando.
        if (SusResponse::where('user_id', $user->id)->exists()) {
            return back()->with('error', 'Ya registraste tu respuesta al cuestionario.');
        }

        $answers = array_map('intval', $request->validated()['answers']);

        SusResponse::create([
            'user_id' => $user->id,
            'role' => $user->getRoleNames()->first() ?? 'sin_rol',
            'answers' => $answers,
            'score' => SusInstrument::score($answers),
            'comments' => $request->validated()['comments'] ?? null,
        ]);

        return to_route('usabilidad.create')->with('success', 'Gracias. Tu respuesta quedó registrada.');
    }
}
