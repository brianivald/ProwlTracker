<?php

namespace App\Http\Controllers;

use App\Models\ZoneState;
use App\Services\AlertNotificationService;
use App\Services\ScheduleLearner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneSettingsController extends Controller
{
    public const DAY_NAMES = [
        0 => 'Lunes',
        1 => 'Martes',
        2 => 'Miércoles',
        3 => 'Jueves',
        4 => 'Viernes',
        5 => 'Sábado',
        6 => 'Domingo',
    ];

    public function edit(ScheduleLearner $learner): View
    {
        $zone = ZoneState::forZone();
        $settings = $zone->settingsPayload();
        $dayNames = self::DAY_NAMES;
        $analysis = $learner->analyze($zone);

        return view('settings.index', compact('zone', 'settings', 'dayNames', 'analysis'));
    }

    /** Aplica el horario sugerido por la actividad registrada. */
    public function applySuggestion(Request $request, ScheduleLearner $learner): RedirectResponse
    {
        $zone = ZoneState::forZone();
        $analysis = $learner->analyze($zone);

        if (! $analysis['differs'] || $analysis['start'] === null) {
            return back()->with('status', 'No hay un horario sugerido distinto al actual.');
        }

        $learner->apply($zone, $analysis['start'], $analysis['end']);

        return back()->with('status', sprintf(
            'Horario actualizado a %02d:00 – %02d:00 según la actividad registrada.',
            $analysis['start'],
            $analysis['end'],
        ));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_start_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'work_end_hour' => ['required', 'integer', 'min:1', 'max:24', 'gt:work_start_hour'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['integer', 'min:0', 'max:6'],
            'loitering_seconds_work' => ['required', 'numeric', 'min:1', 'max:300'],
            'loitering_seconds_off' => ['required', 'numeric', 'min:1', 'max:300'],
            'transit_seconds' => ['required', 'numeric', 'min:0.5', 'max:60'],
            'alert_cooldown_seconds' => ['required', 'numeric', 'min:5', 'max:3600'],
            'adaptive_auto' => ['sometimes', 'boolean'],
            'notify_enabled' => ['sometimes', 'boolean'],
            'notify_only_armed' => ['sometimes', 'boolean'],
            'notify_cooldown_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
            'notify_email_enabled' => ['sometimes', 'boolean'],
            'notify_email_to' => ['nullable', 'email', 'max:180'],
            'notify_telegram_enabled' => ['sometimes', 'boolean'],
            'notify_telegram_chat_id' => ['nullable', 'string', 'max:100'],
        ]);

        $workDays = array_values(array_unique(array_map('intval', $data['work_days'])));
        sort($workDays);

        $zone = ZoneState::forZone();
        $zone->update([
            'work_start_hour' => (int) $data['work_start_hour'],
            'work_end_hour' => (int) $data['work_end_hour'],
            'work_days' => $workDays,
            'loitering_seconds_work' => (float) $data['loitering_seconds_work'],
            'loitering_seconds_off' => (float) $data['loitering_seconds_off'],
            'transit_seconds' => (float) $data['transit_seconds'],
            'alert_cooldown_seconds' => (float) $data['alert_cooldown_seconds'],
            'adaptive_auto' => $request->boolean('adaptive_auto'),
            'notify_enabled' => $request->boolean('notify_enabled'),
            'notify_only_armed' => $request->boolean('notify_only_armed'),
            'notify_cooldown_minutes' => (int) $data['notify_cooldown_minutes'],
            'notify_email_enabled' => $request->boolean('notify_email_enabled'),
            'notify_email_to' => filled($data['notify_email_to'] ?? null) ? trim((string) $data['notify_email_to']) : null,
            'notify_telegram_enabled' => $request->boolean('notify_telegram_enabled'),
            'notify_telegram_chat_id' => filled($data['notify_telegram_chat_id'] ?? null) ? trim((string) $data['notify_telegram_chat_id']) : null,
        ]);

        return redirect()
            ->route('settings.edit')
            ->with('status', $request->input('after_save') === 'test'
                ? 'Configuración guardada. ' . implode(' ', app(AlertNotificationService::class)->sendTestNotification($zone->fresh()))
                : 'Configuración del sistema actualizada. El nodo Python la aplicará en los próximos segundos.');
    }

    /**
     * Envía una notificación de prueba inmediata por los canales activos (Resend / Telegram).
     */
    public function testNotification(AlertNotificationService $notifier): RedirectResponse
    {
        $zone = ZoneState::forZone();
        $messages = $notifier->sendTestNotification($zone);

        return redirect()
            ->route('settings.edit')
            ->with('status', implode(' ', $messages));
    }
}

