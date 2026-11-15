<?php

use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AlertController;
use App\Http\Controllers\Api\ScheduleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Rutas consumidas por el nodo de visión (Python). Requieren firma HMAC
// (ver App\Http\Middleware\VerifyNodeSignature) y tienen límite de frecuencia.
Route::middleware(['throttle:node-api', 'node.signed'])->group(function () {
    Route::post('/alerts', [AlertController::class, 'store']);

    // Consultado por el nodo para saber si la zona está armada u ocupada.
    Route::get('/schedule', [ScheduleController::class, 'show']);

    // El nodo reporta personas confirmadas (YOLO) para aprender el horario real.
    Route::post('/activity', [ActivityController::class, 'store']);
});
