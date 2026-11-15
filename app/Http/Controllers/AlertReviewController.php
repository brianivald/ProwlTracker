<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use App\Models\ZoneState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AlertReviewController extends Controller
{
    /** Minutos que la zona queda como "personal presente" al confirmar que era personal. */
    private const STAFF_CONFIRM_MINUTES = 60;

    public function update(Request $request, Alert $alert): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['personal', 'intrusion', 'falsa_alarma'])],
        ]);

        $alert->update([
            'status' => $data['status'],
            'reviewed_at' => now(),
        ]);

        // Solo una persona (no el tiempo) puede desarmar la zona: al confirmar
        // que era personal, el nodo deja de alertar durante un rato.
        if ($data['status'] === 'personal') {
            ZoneState::forZone()->setOverride(ZoneState::OCCUPIED, self::STAFF_CONFIRM_MINUTES);
        }

        return redirect()->route('dashboard')->with('status', 'Alerta marcada como "'.$data['status'].'".');
    }
}

