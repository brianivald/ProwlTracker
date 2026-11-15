<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NonWorkingDay;
use App\Models\ZoneState;
use App\Services\ScheduleLearner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ScheduleController extends Controller
{

    public function show(Request $request, ScheduleLearner $learner): JsonResponse
    {
        Cache::put('prowl:node-last-seen', now()->timestamp, now()->addDays(7));

        $data = $request->validate([
            'zone' => ['sometimes', 'string', 'max:50'],
        ]);

        $zone = $data['zone'] ?? ZoneState::DEFAULT_ZONE;
        $state = ZoneState::forZone($zone);
        // Horario adaptativo en modo automático (como máximo una vez al día).
        $learner->autoApplyIfDue($state);
        $override = $state->activeOverride();
        $nonWorkingDay = NonWorkingDay::whereDate('date', today())->first();

        return response()->json([
            'zone' => $zone,
            'date' => today()->toDateString(),
            'is_non_working_day' => $nonWorkingDay !== null,
            'reason' => $nonWorkingDay?->reason,
            'override' => $override === null ? null : [
                'mode' => $override,
                'until' => $state->override_until?->toIso8601String(),
            ],
            'settings' => $state->settingsPayload(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}

