<?php

namespace App\Http\Controllers;

use App\Models\ActivityHour;
use App\Models\Alert;
use App\Models\NonWorkingDay;
use App\Models\ZoneState;
use App\Services\ScheduleLearner;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(ScheduleLearner $learner): View
    {
        $alerts = Alert::latest()->paginate(15)->withQueryString();
        $zone = ZoneState::forZone();
        $override = $zone->activeOverride();
        $nonWorkingToday = NonWorkingDay::whereDate('date', today())->first();
        $analysis = $learner->analyze($zone);

        $stats = Alert::selectRaw(
            'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as alerts_24h, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_alerts',
            [now()->subHours(24), 'nueva']
        )->first();

        $alerts24hCount = (int) ($stats->alerts_24h ?? 0);
        $pendingAlertsCount = (int) ($stats->pending_alerts ?? 0);
        $totalAlertsCount = $alerts->total();
        $devices = Alert::select('device_id')->distinct()->orderBy('device_id')->pluck('device_id');

        $activityToday = ActivityHour::where('zone', $zone->zone)
            ->whereDate('date', today())
            ->get();

        $todayMaxPersons = (int) ($activityToday->max('max_persons') ?? 0);
        $todaySamples = (int) ($activityToday->sum('samples') ?? 0);
        $todayActiveHours = (int) $activityToday->where('samples', '>', 0)->count();

        return view('dashboard', compact(
            'alerts',
            'zone',
            'override',
            'nonWorkingToday',
            'analysis',
            'alerts24hCount',
            'pendingAlertsCount',
            'totalAlertsCount',
            'devices',
            'todayMaxPersons',
            'todaySamples',
            'todayActiveHours',
        ));
    }
}

