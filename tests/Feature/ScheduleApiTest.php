<?php

namespace Tests\Feature;

use App\Models\NonWorkingDay;
use App\Models\ZoneState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_without_calendar_or_override_reports_a_normal_day(): void
    {
        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJson([
                'zone' => 'lab',
                'is_non_working_day' => false,
                'reason' => null,
                'override' => null,
            ]);
    }

    public function test_reports_today_as_non_working_day_with_reason(): void
    {
        NonWorkingDay::create(['date' => today()->toDateString(), 'reason' => 'Festivo']);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJson([
                'is_non_working_day' => true,
                'reason' => 'Festivo',
            ]);
    }

    public function test_a_future_non_working_day_does_not_affect_today(): void
    {
        NonWorkingDay::create(['date' => today()->addDays(3)->toDateString(), 'reason' => 'Puente']);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJson(['is_non_working_day' => false]);
    }

    public function test_reports_an_active_manual_override(): void
    {
        ZoneState::forZone()->setOverride(ZoneState::OCCUPIED, 30);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJsonPath('override.mode', 'occupied')
            ->assertJsonStructure(['override' => ['mode', 'until']]);
    }

    public function test_an_override_without_expiry_stays_active(): void
    {
        ZoneState::forZone()->setOverride(ZoneState::ARMED);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJsonPath('override.mode', 'armed')
            ->assertJsonPath('override.until', null);
    }

    public function test_an_expired_override_is_ignored(): void
    {
        ZoneState::forZone()->update([
            'mode_override' => ZoneState::OCCUPIED,
            'override_until' => now()->subMinute(),
        ]);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJson(['override' => null]);
    }

    public function test_zones_are_independent(): void
    {
        ZoneState::forZone('lab')->setOverride(ZoneState::ARMED);

        $this->getJson('/api/schedule?zone=otra')
            ->assertOk()
            ->assertJson(['zone' => 'otra', 'override' => null]);
    }

    public function test_schedule_includes_zone_settings(): void
    {
        ZoneState::forZone('lab')->update([
            'work_start_hour' => 9,
            'work_end_hour' => 18,
            'work_days' => [0, 1, 2, 3, 4],
            'loitering_seconds_work' => 20.0,
            'loitering_seconds_off' => 5.0,
            'transit_seconds' => 2.5,
            'alert_cooldown_seconds' => 45.0,
        ]);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJsonPath('settings.work_start_hour', 9)
            ->assertJsonPath('settings.work_end_hour', 18)
            ->assertJsonPath('settings.work_days', [0, 1, 2, 3, 4])
            ->assertJsonPath('settings.loitering_seconds_work', 20)
            ->assertJsonPath('settings.loitering_seconds_off', 5)
            ->assertJsonPath('settings.transit_seconds', 2.5)
            ->assertJsonPath('settings.alert_cooldown_seconds', 45);
    }
}

