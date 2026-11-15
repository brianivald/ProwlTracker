<?php

namespace App\Http\Controllers;

use App\Models\Alert;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve las fotografías de evidencia solo a usuarios autenticados, sin
 * importar si están en disco local o en un bucket S3 privado.
 */
class EvidenceController extends Controller
{
    public function show(Alert $alert): StreamedResponse
    {
        $disk = Storage::disk(config('prowltracker.evidence_disk'));

        abort_if(blank($alert->image_path) || ! $disk->exists($alert->image_path), 404);

        return $disk->response($alert->image_path, basename($alert->image_path), [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
