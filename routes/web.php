<?php

use App\Http\Controllers\AlertReviewController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StorageManagementController;
use App\Http\Controllers\ZoneModeController;
use App\Http\Controllers\ZoneSettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::delete('/calendar/{nonWorkingDay}', [CalendarController::class, 'destroy'])->name('calendar.destroy');

    Route::post('/zone/mode', [ZoneModeController::class, 'update'])->name('zone.mode');
    Route::get('/settings', [ZoneSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [ZoneSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/apply-suggestion', [ZoneSettingsController::class, 'applySuggestion'])->name('settings.apply-suggestion');
    Route::post('/settings/test-notification', [ZoneSettingsController::class, 'testNotification'])->name('settings.test-notification');
    Route::patch('/alerts/{alert}/review', [AlertReviewController::class, 'update'])->name('alerts.review');
    Route::delete('/alerts/{alert}', [StorageManagementController::class, 'destroy'])->name('alerts.destroy');
    Route::get('/evidence/{alert}', [EvidenceController::class, 'show'])->name('alerts.evidence');

    Route::get('/storage-management', [StorageManagementController::class, 'index'])->name('storage.index');
    Route::delete('/storage-management/bulk', [StorageManagementController::class, 'bulkDestroy'])->name('storage.bulk-destroy');

    Route::get('/node-status', function () {
        $lastSeenTs = \Illuminate\Support\Facades\Cache::get('prowl:node-last-seen');
        $ttl = (int) config('prowltracker.node_api.online_ttl', 120);
        $online = $lastSeenTs && (now()->timestamp - (int) $lastSeenTs) <= $ttl;

        return response()->json([
            'online' => (bool) $online,
            'last_seen_hm' => $lastSeenTs ? \Carbon\Carbon::createFromTimestamp((int) $lastSeenTs)->format('H:i') : null,
        ]);
    })->name('node.status');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
