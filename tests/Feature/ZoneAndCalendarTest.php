<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\NonWorkingDay;
use App\Models\User;
use App\Models\ZoneState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneAndCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function makeAlert(): Alert
    {
        return Alert::create([
            'device_id' => 'nodo1',
            'event_type' => 'loitering_detected',
            'confidence' => 0.9,
            'image_path' => 'alerts/prueba.jpg',
        ]);
    }

    public function test_guests_cannot_change_the_zone_mode(): void
    {
        $this->post('/zone/mode', ['mode' => 'armed'])->assertRedirect('/login');

        $this->assertNull(ZoneState::forZone()->mode_override);
    }

    public function test_arm_now_keeps_the_zone_armed_without_expiry(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/zone/mode', ['mode' => 'armed'])
            ->assertRedirect(route('dashboard'));

        $state = ZoneState::forZone();
        $this->assertSame('armed', $state->activeOverride());
        $this->assertNull($state->override_until);
    }

    public function test_staff_present_expires_automatically(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/zone/mode', ['mode' => 'occupied', 'minutes' => 120]);

        $state = ZoneState::forZone();
        $this->assertSame('occupied', $state->activeOverride());
        $this->assertNotNull($state->override_until);

        $this->travel(121)->minutes();
        $this->assertNull($state->fresh()->activeOverride());
    }

    public function test_auto_clears_the_manual_override(): void
    {
        ZoneState::forZone()->setOverride(ZoneState::ARMED);

        $this->actingAs(User::factory()->create())
            ->post('/zone/mode', ['mode' => 'auto']);

        $this->assertNull(ZoneState::forZone()->activeOverride());
    }

    public function test_invalid_mode_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/zone/mode', ['mode' => 'invalido'])
            ->assertSessionHasErrors('mode');
    }

    public function test_confirming_an_alert_as_staff_sets_the_zone_occupied(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs(User::factory()->create())
            ->patch(route('alerts.review', $alert), ['status' => 'personal'])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('personal', $alert->fresh()->status);
        $this->assertNotNull($alert->fresh()->reviewed_at);
        $this->assertSame('occupied', ZoneState::forZone()->activeOverride());
    }

    public function test_marking_an_intrusion_does_not_disarm_the_zone(): void
    {
        $alert = $this->makeAlert();

        $this->actingAs(User::factory()->create())
            ->patch(route('alerts.review', $alert), ['status' => 'intrusion']);

        $this->assertSame('intrusion', $alert->fresh()->status);
        $this->assertNull(ZoneState::forZone()->activeOverride());
    }

    public function test_new_alerts_start_with_status_nueva(): void
    {
        $this->assertSame('nueva', $this->makeAlert()->fresh()->status);
    }

    public function test_calendar_stores_a_single_day(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/calendar', ['date' => '2026-11-16', 'reason' => 'Revolución'])
            ->assertRedirect(route('calendar.index'));

        $this->assertDatabaseHas('non_working_days', ['reason' => 'Revolución']);
        $this->assertSame(1, NonWorkingDay::count());
    }

    public function test_calendar_stores_every_day_of_a_range(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/calendar', ['date' => '2026-12-21', 'until_date' => '2026-12-25', 'reason' => 'Vacaciones']);

        $this->assertSame(5, NonWorkingDay::count());
    }

    public function test_calendar_does_not_duplicate_existing_days(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/calendar', ['date' => '2026-11-16', 'reason' => 'A']);
        $this->actingAs($user)->post('/calendar', ['date' => '2026-11-16', 'reason' => 'B']);

        $this->assertSame(1, NonWorkingDay::count());
        $this->assertSame('B', NonWorkingDay::first()->reason);
    }

    public function test_calendar_rejects_an_end_date_before_the_start(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/calendar', ['date' => '2026-12-25', 'until_date' => '2026-12-21'])
            ->assertSessionHasErrors('until_date');
    }

    public function test_calendar_rejects_overly_long_ranges(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/calendar', ['date' => '2026-01-01', 'until_date' => '2026-12-31'])
            ->assertSessionHasErrors('until_date');

        $this->assertSame(0, NonWorkingDay::count());
    }

    public function test_calendar_day_can_be_removed(): void
    {
        $day = NonWorkingDay::create(['date' => '2026-11-16']);

        $this->actingAs(User::factory()->create())
            ->delete(route('calendar.destroy', $day))
            ->assertRedirect(route('calendar.index'));

        $this->assertSame(0, NonWorkingDay::count());
    }

    public function test_dashboard_and_calendar_pages_render(): void
    {
        $this->makeAlert();
        NonWorkingDay::create(['date' => today()->toDateString(), 'reason' => 'Festivo']);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertSee('Estado de la zona')
            ->assertSee('Es personal');

        $this->actingAs($user)->get('/calendar')
            ->assertOk()
            ->assertSee('Festivo');

        $this->actingAs($user)->get('/settings')
            ->assertOk()
            ->assertSee('Configuración del Sistema');
    }

    public function test_zone_settings_can_be_updated_from_web_panel(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', [
            'work_start_hour' => 10,
            'work_end_hour' => 19,
            'work_days' => [0, 1, 2, 3, 4, 5],
            'loitering_seconds_work' => 18,
            'loitering_seconds_off' => 7.5,
            'transit_seconds' => 4,
            'alert_cooldown_seconds' => 60,
        ])->assertRedirect(route('settings.edit'));

        $settings = ZoneState::forZone()->settingsPayload();
        $this->assertSame(10, $settings['work_start_hour']);
        $this->assertSame(19, $settings['work_end_hour']);
        $this->assertSame([0, 1, 2, 3, 4, 5], $settings['work_days']);
        $this->assertSame(18.0, $settings['loitering_seconds_work']);
        $this->assertSame(7.5, $settings['loitering_seconds_off']);
        $this->assertSame(4.0, $settings['transit_seconds']);
        $this->assertSame(60.0, $settings['alert_cooldown_seconds']);
    }

    public function test_zone_settings_reject_end_hour_not_greater_than_start_hour(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->put('/settings', [
            'work_start_hour' => 18,
            'work_end_hour' => 9,
            'work_days' => [0, 1, 2],
            'loitering_seconds_work' => 15,
            'loitering_seconds_off' => 6,
            'transit_seconds' => 3,
            'alert_cooldown_seconds' => 30,
        ])->assertSessionHasErrors('work_end_hour');
    }
}

