<?php

namespace App\Http\Controllers;

use App\Models\NonWorkingDay;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    private const MAX_RANGE_DAYS = 120;

    public function index(): View
    {
        $days = NonWorkingDay::whereDate('date', '>=', today()->subDays(30))
            ->orderBy('date')
            ->get();

        return view('calendar.index', compact('days'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'until_date' => ['nullable', 'date', 'after_or_equal:date'],
            'reason' => ['nullable', 'string', 'max:120'],
        ]);

        $start = Carbon::parse($data['date'])->startOfDay();
        $end = isset($data['until_date'])
            ? Carbon::parse($data['until_date'])->startOfDay()
            : $start->copy();

        if ($start->diffInDays($end) > self::MAX_RANGE_DAYS) {
            return back()
                ->withErrors(['until_date' => 'El rango máximo es de '.self::MAX_RANGE_DAYS.' días.'])
                ->withInput();
        }

        foreach (CarbonPeriod::create($start, $end) as $day) {
            NonWorkingDay::updateOrCreate(
                ['date' => $day->toDateString()],
                ['reason' => $data['reason'] ?? null],
            );
        }

        return redirect()->route('calendar.index')->with('status', 'Días no laborables guardados.');
    }

    public function destroy(Request $request, NonWorkingDay $nonWorkingDay): RedirectResponse
    {
        $ids = $request->input('ids');

        if (is_array($ids) && ! empty($ids)) {
            NonWorkingDay::whereIn('id', array_map('intval', $ids))->delete();

            return redirect()->route('calendar.index')->with('status', 'Periodo eliminado del calendario.');
        }

        $nonWorkingDay->delete();

        return redirect()->route('calendar.index')->with('status', 'Día eliminado del calendario.');
    }
}

