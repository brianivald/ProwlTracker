<?php

namespace App\Http\Controllers;

use App\Models\ZoneState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ZoneModeController extends Controller
{
    private const DEFAULT_OCCUPIED_MINUTES = 120;

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['auto', 'armed', 'occupied'])],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'zone' => ['nullable', 'string', 'max:50'],
        ]);

        $state = ZoneState::forZone($data['zone'] ?? ZoneState::DEFAULT_ZONE);

        match ($data['mode']) {
            // Vuelve al horario + calendario.
            'auto' => $state->setOverride(null),
            // "Armar ahora": se mantiene hasta que alguien lo cambie.
            'armed' => $state->setOverride(ZoneState::ARMED),
            // "Personal presente": vence solo para no dejar la zona desarmada por error.
            'occupied' => $state->setOverride(
                ZoneState::OCCUPIED,
                (int) ($data['minutes'] ?? self::DEFAULT_OCCUPIED_MINUTES),
            ),
        };

        return redirect()->route('dashboard')->with('status', 'Modo de la zona actualizado.');
    }
}

