<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NodeSecurityTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = '0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef';

    /**
     * Genera las cabeceras HMAC-SHA256 válidas para una petición del nodo.
     */
    private function signHeaders(string $method, string $path, string $query = '', string $body = '', ?int $timestamp = null, ?string $nonce = null): array
    {
        $timestamp = (string) ($timestamp ?? time());
        $nonce = $nonce ?? bin2hex(random_bytes(16));
        $bodyHash = hash('sha256', $body);

        $canonical = implode("\n", [
            strtoupper($method),
            $path,
            $query,
            $timestamp,
            $nonce,
            $bodyHash,
        ]);

        $signature = hash_hmac('sha256', $canonical, $this->secret);

        return [
            'X-PT-Timestamp' => $timestamp,
            'X-PT-Nonce'     => $nonce,
            'X-PT-Signature' => $signature,
        ];
    }

    public function test_unsigned_request_is_rejected_when_secret_is_configured(): void
    {
        config(['prowltracker.node_api.secret' => $this->secret]);

        $response = $this->getJson('/api/schedule?zone=lab');

        $response->assertStatus(401);
    }

    public function test_signed_get_schedule_succeeds_and_replay_is_blocked(): void
    {
        config(['prowltracker.node_api.secret' => $this->secret]);
        Cache::flush();

        $headers = $this->signHeaders('GET', '/api/schedule', 'zone=lab');

        $first = $this->withHeaders($headers)->getJson('/api/schedule?zone=lab');
        $first->assertStatus(200)
            ->assertJsonStructure(['status', 'zone', 'mode']);

        // Reutilizar el mismo nonce debe ser rechazado (anti-replay)
        $replay = $this->withHeaders($headers)->getJson('/api/schedule?zone=lab');
        $replay->assertStatus(401);
    }

    public function test_expired_timestamp_is_rejected(): void
    {
        config([
            'prowltracker.node_api.secret' => $this->secret,
            'prowltracker.node_api.max_clock_skew' => 300,
        ]);

        $headers = $this->signHeaders('GET', '/api/schedule', 'zone=lab', '', time() - 1000);

        $response = $this->withHeaders($headers)->getJson('/api/schedule?zone=lab');
        $response->assertStatus(401);
    }

    public function test_signed_post_alert_stores_image_and_serves_only_to_authenticated_users(): void
    {
        Storage::fake('public');
        config([
            'prowltracker.node_api.secret' => $this->secret,
            'prowltracker.evidence_disk' => 'public',
        ]);

        // Crear un JPEG mínimo válido de 1x1 píxel en memoria
        $im = imagecreatetruecolor(1, 1);
        ob_start();
        imagejpeg($im);
        $jpegBytes = ob_get_clean();
        imagedestroy($im);

        $payload = json_encode([
            'device_id'   => 'nodo1',
            'event_type'  => 'loitering',
            'dwell_time'  => 14.2,
            'confidence'  => 0.91,
            'detected_at' => '2026-11-14 22:00:00',
            'image_b64'   => base64_encode($jpegBytes),
        ]);

        $headers = array_merge(
            ['Content-Type' => 'application/json', 'Accept' => 'application/json'],
            $this->signHeaders('POST', '/api/alerts', '', $payload)
        );

        $response = $this->call('POST', '/api/alerts', [], [], [], $this->transformHeadersToServerVars($headers), $payload);
        $response->assertStatus(201);

        $alert = Alert::firstOrFail();
        Storage::disk('public')->assertExists($alert->image_path);

        // Un visitante no autenticado no puede acceder a la evidencia
        $this->get(route('alerts.evidence', $alert))->assertRedirect(route('login'));

        // Un operador autenticado sí puede ver la evidencia con cabeceras privadas
        $user = User::factory()->create();
        $evidenceResponse = $this->actingAs($user)->get(route('alerts.evidence', $alert));
        $evidenceResponse->assertStatus(200);
        $evidenceResponse->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
