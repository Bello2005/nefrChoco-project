<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'canChangeEmail' => ! $request->user()->isStaff(),
            'status' => $request->session()->get('status'),
            // Sin "Eliminar cuenta": borrarla arrastraba lo que la persona
            // registró. El paciente conserva su derecho a pedir que se
            // corrijan o eliminen sus datos (Ley 1581 de 2012), y lo ejerce
            // ante la IPS, que es la responsable del tratamiento.
            // TODO: validar con el área jurídica de la IPS
            'dataRequestEmail' => $request->user()->hasRole(Role::Paciente->value)
                ? config('privacy.contact_email')
                : null,
        ]);
    }

    /**
     * Update the user's profile settings.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }
}
