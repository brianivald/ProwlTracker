<?php

namespace App\Services;

use App\Models\ActivityHour;
use App\Models\Alert;
use App\Models\NonWorkingDay;
use App\Models\ZoneState;
use Carbon\CarbonPeriod;

/**
 * Aprende el horario laboral real a partir de la actividad registrada por el
 * nodo de visión (personas confirmadas por YOLO en activity_hours).
 *
 * Para cada hora del día calcula en qué fracción de los días hábiles recientes
 * hubo personas. Las horas que superan ACTIVE_RATIO se consideran "laborales";
 * el horario sugerido va de la primera a la última de ellas.
 *
 * Seguridad: una hora NO cuenta como actividad legítima si tiene una alerta
 * confirmada como intrusión o una alerta todavía sin revisar. Así un intruso
 * nocturno nunca "enseña" al sistema que la noche es horario laboral.
 */
class ScheduleLearner
{
    /** Días hacia atrás que se analizan. */
    public const LOOKBACK_DAYS = 21;

    /** Días hábiles mínimos con datos para atreverse a sugerir algo. */
    public const MIN_DAYS = 5;

    /** Fracción de días con personas a partir de la cual una hora es "laboral". */
    public const ACTIVE_RATIO = 0.5;

    /** Confianza mínima para que el modo automático aplique el cambio solo. */
    public const AUTO_APPLY_CONFIDENCE = 0.6;

    /**
     * @return array{
     *     ready: bool, days_observed: int, min_days: int, hourly: array<int, float>,
     *     start: ?int, end: ?int, confidence: ?float, differs: bool
     * }
     */
    public function analyze(ZoneState $zone): array
    {
        $settings = $zone->settingsPayload();
        $workDays = $settings['work_days'];
        $from = today()->subDays(self::LOOKBACK_DAYS);
        $to = today()->subDay(); // solo días completos

        $result = [
            'ready' => false,
            'days_observed' => 0,
            'min_days' => self::MIN_DAYS,
            'hourly' => array_fill(0, 24, 0.0),
            'start' => null,
            'end' => null,
            'confidence' => null,
            'differs' => false,
        ];

        $rows = ActivityHour::where('zone', $zone->zone)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->where('samples', '>', 0)
            ->get();

        if ($rows->isEmpty()) {
            return $result;
        }

        // El sistema "existe" desde el primer registro: los días previos no cuentan.
        $firstDate = $rows->min('date')->copy()->startOfDay();
        $start = $firstDate->greaterThan($from) ? $firstDate : $from;

        $holidays = NonWorkingDay::whereDate('date', '>=', $start->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->map(fn ($d) => $d->date->toDateString())
            ->flip();

        $eligibleDates = [];
        foreach (CarbonPeriod::create($start, $to) as $day) {
            $weekday = $day->dayOfWeekIso - 1; // 0 = lunes ... 6 = domingo
            if (in_array($weekday, $workDays, true) && ! isset($holidays[$day->toDateString()])) {
                $eligibleDates[$day->toDateString()] = true;
            }
        }

        $result['days_observed'] = count($eligibleDates);
        if ($result['days_observed'] < self::MIN_DAYS) {
            return $result;
        }

        // Horas "contaminadas": intrusión confirmada o alerta aún sin revisar.
        $blockedHours = Alert::whereIn('status', ['intrusion', 'nueva'])
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['created_at'])
            ->map(fn ($a) => $a->created_at->format('Y-m-d H'))
            ->flip();

        $counts = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $date = $row->date->toDateString();
            if (! isset($eligibleDates[$date])) {
                continue;
            }
            if (isset($blockedHours[$date.' '.str_pad((string) $row->hour, 2, '0', STR_PAD_LEFT)])) {
                continue;
            }
            $counts[$row->hour]++;
        }

        $hourly = array_map(fn ($c) => round($c / $result['days_observed'], 2), $counts);
        $result['hourly'] = $hourly;

        $activeHours = array_keys(array_filter($hourly, fn ($r) => $r >= self::ACTIVE_RATIO));
        if (empty($activeHours)) {
            $result['ready'] = true;

            return $result;
        }

        $suggestedStart = min($activeHours);
        $suggestedEnd = max($activeHours) + 1;
        $span = array_slice($hourly, $suggestedStart, $suggestedEnd - $suggestedStart);

        $result['ready'] = true;
        $result['start'] = $suggestedStart;
        $result['end'] = $suggestedEnd;
        $result['confidence'] = round(array_sum($span) / count($span), 2);
        $result['differs'] = $suggestedStart !== $settings['work_start_hour']
            || $suggestedEnd !== $settings['work_end_hour'];

        return $result;
    }

    /** Aplica un horario y guarda el anterior para poder mostrarlo/revertirlo. */
    public function apply(ZoneState $zone, int $start, int $end): void
    {
        $settings = $zone->settingsPayload();

        $zone->update([
            'adaptive_previous_schedule' => sprintf('%02d:00-%02d:00', $settings['work_start_hour'], $settings['work_end_hour']),
            'work_start_hour' => $start,
            'work_end_hour' => $end,
            'adaptive_applied_at' => now(),
        ]);
    }

    /**
     * Modo automático: se evalúa como máximo una vez al día (se llama desde
     * GET /api/schedule, que el nodo consulta cada pocos segundos, así no
     * hace falta tener corriendo el scheduler de Laravel).
     */
    public function autoApplyIfDue(ZoneState $zone): bool
    {
        if (! $zone->adaptive_auto) {
            return false;
        }
        if ($zone->adaptive_evaluated_on !== null && $zone->adaptive_evaluated_on->isToday()) {
            return false;
        }

        $zone->update(['adaptive_evaluated_on' => today()]);
        $analysis = $this->analyze($zone);

        if (! $analysis['differs'] || $analysis['confidence'] === null
            || $analysis['confidence'] < self::AUTO_APPLY_CONFIDENCE) {
            return false;
        }

        $this->apply($zone, $analysis['start'], $analysis['end']);

        return true;
    }
}

