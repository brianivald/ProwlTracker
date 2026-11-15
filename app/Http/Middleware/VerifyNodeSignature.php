<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifica que las solicitudes a /api/* provengan del nodo de visión.
 *
 * El nodo firma cada solicitud con HMAC-SHA256 sobre la cadena canónica:
 *
 *     METHOD \n /ruta \n query_ordenada \n timestamp \n nonce \n sha256(body)
 *
 * y la envía en los encabezados:
 *
 *     X-PT-Timestamp: segundos Unix
 *     X-PT-Nonce:     valor aleatorio único (16-64 caracteres)
 *     X-PT-Signature: firma en hexadecimal
 *
 * Esto garantiza que (1) solo quien conoce el secreto puede llamar a la API,
 * (2) el cuerpo (imagen, conteos) no fue alterado en tránsito y (3) una
 * solicitud capturada no puede reenviarse (marca de tiempo + nonce de un uso).
 */
class VerifyNodeSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) config('prowltracker.node_api.secret');

        if ($secret === '') {
            if (app()->environment('production')) {
                return $this->reject('La API del nodo no está configurada en el servidor.', 503);
            }

            // Desarrollo local / pruebas: se permite sin firma.
            Cache::put('prowl:node-last-seen', now()->timestamp, now()->addDays(7));

            return $next($request);
        }

        $timestamp = (string) $request->header('X-PT-Timestamp', '');
        $nonce = (string) $request->header('X-PT-Nonce', '');
        $signature = strtolower((string) $request->header('X-PT-Signature', ''));

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            return $this->reject('Solicitud sin firmar.');
        }

        if (! ctype_digit($timestamp) || ! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $nonce)) {
            return $this->reject('Encabezados de firma con formato inválido.');
        }

        $maxSkew = max(30, (int) config('prowltracker.node_api.max_clock_skew', 300));

        if (abs(time() - (int) $timestamp) > $maxSkew) {
            return $this->reject('Marca de tiempo fuera de rango. Verifica el reloj del nodo.');
        }

        $expected = hash_hmac('sha256', self::canonicalString($request, $timestamp, $nonce), $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->reject('Firma inválida.');
        }

        // El nonce solo puede usarse una vez dentro de la ventana de validez.
        if (! Cache::add('prowl:node-nonce:'.$nonce, true, $maxSkew * 2)) {
            return $this->reject('Solicitud repetida.');
        }

        Cache::put('prowl:node-last-seen', now()->timestamp, now()->addDays(7));

        return $next($request);
    }

    /**
     * Cadena canónica que firman ambos extremos.
     */
    public static function canonicalString(Request $request, string $timestamp, string $nonce): string
    {
        $query = $request->query();
        ksort($query);

        return implode("\n", [
            strtoupper($request->getMethod()),
            '/'.ltrim($request->path(), '/'),
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            $timestamp,
            $nonce,
            hash('sha256', (string) $request->getContent()),
        ]);
    }

    private function reject(string $message, int $status = 401): JsonResponse
    {
        return response()->json(['status' => 'error', 'message' => $message], $status);
    }
}
