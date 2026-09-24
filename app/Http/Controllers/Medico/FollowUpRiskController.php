<?php

namespace App\Http\Controllers\Medico;

use App\Enums\FollowUpRiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * El médico asigna el nivel de riesgo para el seguimiento remoto.
 *
 * Vive en la zona médica (role:medico): ni el admin ni el paciente lo tocan,
 * porque es un criterio clínico. El cambio queda en la auditoría por
 * LogsChangedFields, con el nombre del campo y sin el valor.
 */
class FollowUpRiskController extends Controller
{
    public function update(Request $request, Patient $patient): RedirectResponse
    {
        $validated = $request->validate([
            'follow_up_risk_level' => ['nullable', Rule::enum(FollowUpRiskLevel::class)],
        ], [], ['follow_up_risk_level' => 'nivel de riesgo de seguimiento']);

        $patient->forceFill(['follow_up_risk_level' => $validated['follow_up_risk_level'] ?? null])->save();

        return back()->with('success', 'Nivel de seguimiento actualizado.');
    }
}
