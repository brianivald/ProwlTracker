<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ZoneState extends Model
{
    public const DEFAULT_ZONE = 'lab';

    public const ARMED = 'armed';

    public const OCCUPIED = 'occupied';

    protected $fillable = [
        'zone',
        'mode_override',
        'override_until',
        'work_start_hour',
        'work_end_hour',
        'work_days',
        'loitering_seconds_work',
        'loitering_seconds_off',
        'transit_seconds',
        'alert_cooldown_seconds',
        'adaptive_auto',
        'adaptive_evaluated_on',
        'adaptive_applied_at',
        'adaptive_previous_schedule',
        'notify_enabled',
        'notify_only_armed',
        'notify_cooldown_minutes',
        'notify_email_enabled',
        'notify_email_to',
        'notify_telegram_enabled',
        'notify_telegram_chat_id',
    ];

    protected $attributes = [
        'work_start_hour' => 8,
        'work_end_hour' => 20,
        'work_days' => '[0,1,2,3,4]',
        'loitering_seconds_work' => 15.0,
        'loitering_seconds_off' => 6.0,
        'transit_seconds' => 3.0,
        'alert_cooldown_seconds' => 30.0,
        'adaptive_auto' => false,
        'notify_enabled' => false,
        'notify_only_armed' => true,
        'notify_cooldown_minutes' => 15,
        'notify_email_enabled' => false,
        'notify_telegram_enabled' => false,
    ];

    protected function casts(): array
    {
        return [
            'override_until' => 'datetime',
            'work_start_hour' => 'integer',
            'work_end_hour' => 'integer',
            'work_days' => 'array',
            'loitering_seconds_work' => 'float',
            'loitering_seconds_off' => 'float',
            'transit_seconds' => 'float',
            'alert_cooldown_seconds' => 'float',
            'adaptive_auto' => 'boolean',
            'adaptive_evaluated_on' => 'date',
            'adaptive_applied_at' => 'datetime',
            'notify_enabled' => 'boolean',
            'notify_only_armed' => 'boolean',
            'notify_cooldown_minutes' => 'integer',
            'notify_email_enabled' => 'boolean',
            'notify_telegram_enabled' => 'boolean',
        ];
    }

    /**
     * Regresa la configuración de horario y tiempos lista para el nodo Python.
     */
    public function settingsPayload(): array
    {
        $days = $this->work_days;
        if (! is_array($days) || empty($days)) {
            $days = [0, 1, 2, 3, 4];
        }

        return [
            'work_start_hour' => (int) ($this->work_start_hour ?? 8),
            'work_end_hour' => (int) ($this->work_end_hour ?? 20),
            'work_days' => array_values(array_map('intval', $days)),
            'loitering_seconds_work' => (float) ($this->loitering_seconds_work ?? 15.0),
            'loitering_seconds_off' => (float) ($this->loitering_seconds_off ?? 6.0),
            'transit_seconds' => (float) ($this->transit_seconds ?? 3.0),
            'alert_cooldown_seconds' => (float) ($this->alert_cooldown_seconds ?? 30.0),
        ];
    }

    public static function forZone(string $zone = self::DEFAULT_ZONE): self
    {
        return static::firstOrCreate(['zone' => $zone]);
    }

    /**
     * Modo manual vigente ('armed' | 'occupied'), o null si la zona sigue
     * el horario automático o el modo manual ya venció.
     */
    public function activeOverride(): ?string
    {
        if ($this->mode_override === null) {
            return null;
        }

        if ($this->override_until !== null && $this->override_until->isPast()) {
            return null;
        }

        return $this->mode_override;
    }

    /**
     * Fuerza un modo (o null para volver a automático). Con $minutes el
     * modo vence solo; sin $minutes se mantiene hasta que alguien lo cambie.
     */
    public function setOverride(?string $mode, ?int $minutes = null): void
    {
        $this->update([
            'mode_override' => $mode,
            'override_until' => ($mode !== null && $minutes !== null) ? now()->addMinutes($minutes) : null,
        ]);
    }

    /**
     * Determina si la zona se encuentra actualmente en estado armado (Vigilancia activa).
     */
    public function isCurrentlyArmed(): bool
    {
        $override = $this->activeOverride();
        if ($override === self::ARMED) {
            return true;
        }
        if ($override === self::OCCUPIED) {
            return false;
        }

        if (NonWorkingDay::whereDate('date', today())->exists()) {
            return true;
        }

        $settings = $this->settingsPayload();
        $currentWeekday = now()->dayOfWeekIso - 1; // 0 = Lunes ... 6 = Domingo
        $isWorkDay = in_array($currentWeekday, $settings['work_days'], true);
        if (! $isWorkDay) {
            return true;
        }

        $hour = now()->hour;
        $isWithinWorkHours = $hour >= $settings['work_start_hour'] && $hour < $settings['work_end_hour'];

        return ! $isWithinWorkHours;
    }
}

