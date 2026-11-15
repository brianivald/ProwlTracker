<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Alert;
use App\Services\AlertNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AlertController extends Controller
{
    /** Formatos de imagen aceptados => extensión con la que se guardan. */
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(Request $request): JsonResponse
    {
        Cache::put('prowl:node-last-seen', now()->timestamp, now()->addDays(7));

        $request->validate([
            'device_id' => 'required|string|max:100',
            'event_type' => 'required|string|max:100',
            'confidence' => 'required|numeric|min:0|max:100',
            'image_base64' => 'required|string',
        ]);

        $parts = explode(';base64,', $request->image_base64);
        $binary = base64_decode(end($parts), true);

        $maxBytes = (int) config('prowltracker.evidence_max_bytes');
        if ($binary === false || $binary === '' || strlen($binary) > $maxBytes) {
            return response()->json(['status' => 'error', 'message' => 'Imagen inválida o demasiado grande.'], 422);
        }

        // Verifica que el contenido sea realmente una imagen permitida.
        $info = @getimagesizefromstring($binary);
        $extension = $info ? (self::ALLOWED_MIME[$info['mime']] ?? null) : null;
        if ($extension === null) {
            return response()->json(['status' => 'error', 'message' => 'Formato de imagen no permitido.'], 422);
        }

        $disk = Storage::disk(config('prowltracker.evidence_disk'));
        $fileName = 'alerts/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;

        try {
            if (! $disk->put($fileName, $binary)) {
                throw new \RuntimeException('No se pudo escribir la evidencia en el disco.');
            }

            $alert = Alert::create([
                'device_id' => $request->device_id,
                'event_type' => $request->event_type,
                'confidence' => $request->confidence,
                'image_path' => $fileName,
            ]);

            app(AlertNotificationService::class)->notifyIfEligible($alert, $binary);

            return response()->json(['status' => 'success', 'data' => $alert], 201);
        } catch (\Throwable $e) {
            Log::error('Error al registrar alerta del nodo', ['error' => $e->getMessage()]);

            if ($disk->exists($fileName)) {
                $disk->delete($fileName);
            }

            return response()->json(['status' => 'error', 'message' => 'Error al procesar la imagen'], 500);
        }
    }
}
