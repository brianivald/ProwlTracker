<?php

namespace Tests\Feature;

use App\Models\ActivityHour;
use App\Models\Alert;
use App\Models\User;
use App\Models\ZoneState;
use App\Services\ScheduleLearner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptiveScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function seedWorkdayActivity(array $hours, int $days = 6): void
    {
        $cursor = today()->subDay();
        $seeded = 0;

        while ($seeded < $days) {
            $weekday = $cursor->dayOfWeekIso - 1;
            if (in_array($weekday, [0, 1, 2, 3, 4], true)) {
                foreach ($hours as $h) {
                    ActivityHour::create([
                        'zone' => 'lab',
                        'date' => $cursor->toDateString(),
                        'hour' => $h,
                        'samples' => 4,
                        'armed_samples' => 0,
                        'max_persons' => 2,
                    ]);
                }
                $seeded++;
            }
            $cursor->subDay();
        }
    }

    public function test_activity_endpoint_accumulates_hourly_person_detections(): void
    {
        $this->postJson('/api/activity', [
            'zone' => 'lab',
            'device_id' => 'esp32-cam-lab01',
            'person_count' => 2,
            'zone_mode' => 'OCCUPIED',
        ])->assertCreated()
            ->assertJsonPath('samples', 1);

        $this->postJson('/api/activity', [
            'zone' => 'lab',
            'device_id' => 'esp32-cam-lab01',
            'person_count' => 3,
            'zone_mode' => 'ARMED',
        ])->assertCreated()
            ->assertJsonPath('samples', 2);

        $row = ActivityHour::first();
        $this->assertSame(2, $row->samples);
        $this->assertSame(1, $row->armed_samples);
        $this->assertSame(3, $row->max_persons);
    }

    public function test_learner_suggests_new_schedule_from_consistent_activity(): void
    {
        $this->seedWorkdayActivity(range(11, 20), 6); // 11:00 a 21:00

        $zone = ZoneState::forZone('lab');
        $analysis = app(ScheduleLearner::class)->analyze($zone);

        $this->assertTrue($analysis['ready']);
        $this->assertTrue($analysis['differs']);
        $this->assertSame(11, $analysis['start']);
        $this->assertSame(21, $analysis['end']);
        $this->assertGreaterThanOrEqual(0.8, $analysis['confidence']);
    }

    public function test_intrusion_alerts_do_not_poison_learned_schedule(): void
    {
        $this->seedWorkdayActivity([9, 10, 11, 23], 6);

        // Simulamos que las detecciones a las 23:00 fueron intrusiones
        foreach (ActivityHour::where('hour', 23)->get() as $row) {
            $ts = Carbon::parse($row->date->toDateString().' 23:15:00');
            Alert::forceCreate([
                'device_id' => 'nodo1',
                'event_type' => 'loitering_detected',
                'confidence' => 0.95,
                'image_path' => 'alerts/intruso.jpg',
                'status' => 'intrusion',
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);
        }

        $analysis = app(ScheduleLearner::class)->analyze(ZoneState::forZone('lab'));

        $this->assertSame(9, $analysis['start']);
        $this->assertSame(12, $analysis['end']);
    }

    public function test_user_can_apply_suggested_schedule_from_panel(): void
    {
        $this->seedWorkdayActivity(range(11, 20), 6);

        $user = User::factory()->create();
        $this->actingAs($user)
            ->post(route('settings.apply-suggestion'))
            ->assertRedirect();

        $zone = ZoneState::forZone('lab')->fresh();
        $this->assertSame(11, $zone->work_start_hour);
        $this->assertSame(21, $zone->work_end_hour);
        $this->assertSame('08:00-20:00', $zone->adaptive_previous_schedule);
    }

    public function test_auto_mode_updates_schedule_automatically_when_schedule_api_is_polled(): void
    {
        $this->seedWorkdayActivity(range(11, 20), 6);
        ZoneState::forZone('lab')->update(['adaptive_auto' => true]);

        $this->getJson('/api/schedule')
            ->assertOk()
            ->assertJsonPath('settings.work_start_hour', 11)
            ->assertJsonPath('settings.work_end_hour', 21);
    }
}

