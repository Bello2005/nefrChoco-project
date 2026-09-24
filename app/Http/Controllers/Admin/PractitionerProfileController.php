<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePractitionerProfileRequest;
use App\Models\User;
use App\Services\PractitionerProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Datos profesionales y verificación RETHUS de los médicos, desde
 * Admin → Usuarios. Solo aplica a cuentas con rol medico.
 */
class PractitionerProfileController extends Controller
{
    public function __construct(
        private readonly PractitionerProfileService $profiles,
    ) {}

    public function update(UpdatePractitionerProfileRequest $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(Role::Medico->value), 404);

        $this->profiles->save($user, $request->validated());

        return back()->with('success', 'Datos profesionales guardados.');
    }

    public function verifyRethus(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->hasRole(Role::Medico->value), 404);

        $validated = $request->validate(
            ['rethus_note' => ['required', 'string', 'max:1000']],
            ['rethus_note.required' => 'Anota qué verificaste en ReTHUS (por ejemplo, la fecha de la consulta y el estado).'],
        );

        try {
            $this->profiles->markRethusVerified($user, $request->user(), $validated['rethus_note']);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Verificación en RETHUS registrada.');
    }
}
