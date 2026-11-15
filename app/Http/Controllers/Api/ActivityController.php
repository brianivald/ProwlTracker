<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityHour;
use App\Models\ZoneState;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    /**
     * El nodo de visión avisa que YOLO confirmó personas en la zona. Se usa
     * la hora del servidor (los reportes llegan en tiempo real), así no
     * dependemos del reloj del contenedor.
     */
    public function store(Request $request): JsonResponse
    {
        Cache::put('prowl:node-last-seen', now()->timestamp, now()->addDays(7));

        $data = $request->validate([
            'zone' => ['sometimes', 'string', 'max:50'],
            'device_id' => ['sometimes', 'nullable', 'string', 'max:100'],
            'person_count' => ['required', 'integer', 'min:1', 'max:100'],
            'zone_mode' => ['sometimes', 'nullable', Rule::in(['ARMED', 'OCCUPIED'])],
        ]);

        $zone = $data['zone'] ?? ZoneState::DEFAULT_ZONE;
        $now = now();

        $row = ActivityHour::where('zone', $zone)
            ->whereDate('date', $now->toDateString())
            ->where('hour', $now->hour)
            ->first();

        if ($row === null) {
            $row = new ActivityHour([
                'zone' => $zone,
                'date' => $now->toDateString(),
                'hour' => $now->hour,
                'samples' => 0,
                'armed_samples' => 0,
                'max_persons' => 0,
            ]);
        }

        $row->samples += 1;
        if (($data['zone_mode'] ?? null) === 'ARMED') {
            $row->armed_samples += 1;
        }
        $row->max_persons = max($row->max_persons, (int) $data['person_count']);
        $row->save();

        return response()->json([
            'zone' => $zone,
            'date' => $row->date->toDateString(),
            'hour' => $row->hour,
            'samples' => $row->samples,
        ], 201);
    }
}

