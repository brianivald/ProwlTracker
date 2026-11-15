<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\ZoneState;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AlertNotificationService
{
    private const EVENT_TRANSLATIONS = [
        'loitering' => 'Merodeo detectado',
        'loitering_detected' => 'Merodeo detectado',
        'merodeo' => 'Merodeo detectado',
        'intrusion' => 'Posible intrusión',
        'intrusion_detected' => 'Posible intrusión',
        'person' => 'Persona detectada',
        'person_detected' => 'Persona detectada',
        'motion' => 'Movimiento en zona',
        'motion_detected' => 'Movimiento en zona',
        'unauthorized_access' => 'Acceso no autorizado',
        'trespassing' => 'Ingreso en zona restringida',
    ];

    private ?string $lastError = null;

    private function httpClient(int $timeout = 8)
    {
        return Http::timeout($timeout)
            ->when(app()->environment('local'), fn ($http) => $http->withoutVerifying());
    }
    public function notifyIfEligible(Alert $alert, ?string $rawBinary = null, string $zoneName = ZoneState::DEFAULT_ZONE): array
    {
        try {
            $zone = ZoneState::forZone($zoneName);

            if (! $zone->notify_enabled) {
                return ['sent' => false, 'reason' => 'disabled'];
            }

            if ($zone->notify_only_armed && ! $zone->isCurrentlyArmed()) {
                return ['sent' => false, 'reason' => 'zone_occupied'];
            }

            $cooldownKey = 'prowl:notify-cooldown:'.$zone->zone;
            if (Cache::has($cooldownKey)) {
                return ['sent' => false, 'reason' => 'cooldown'];
            }

            $channelsSent = [];
            $eventLabel = $this->translateEvent($alert->event_type);
            $confPct = $alert->confidence <= 1
                ? (int) round($alert->confidence * 100)
                : (int) round($alert->confidence);
            $confPct = max(0, min(100, $confPct));
            $detectedAt = ($alert->created_at ?? now())->format('d/m/Y H:i:s');
            $dashboardUrl = url('/dashboard');

            if ($zone->notify_email_enabled && filled($zone->notify_email_to)) {
                if ($this->sendResendEmail(
                    to: $zone->notify_email_to,
                    subject: "[ProwlTracker] Alerta en Zona «{$zone->zone}» — {$eventLabel}",
                    zoneName: $zone->zone,
                    deviceId: $alert->device_id,
                    eventLabel: $eventLabel,
                    confidencePct: $confPct,
                    detectedAt: $detectedAt,
                    dashboardUrl: $dashboardUrl,
                    rawBinary: $rawBinary,
                    alertId: $alert->id,
                )) {
                    $channelsSent[] = 'email';
                }
            }

            if ($zone->notify_telegram_enabled && filled($zone->notify_telegram_chat_id)) {
                $caption = implode("\n", [
                    "🚨 *ProwlTracker — Alerta de Seguridad*",
                    "",
                    "• *Zona:* `{$zone->zone}`",
                    "• *Evento:* {$eventLabel}",
                    "• *Nodo / Cámara:* `{$alert->device_id}`",
                    "• *Confianza:* {$confPct}%",
                    "• *Fecha y hora:* {$detectedAt}",
                    "",
                    "🔗 [Abrir consola de monitoreo]({$dashboardUrl})",
                ]);

                if ($this->sendTelegramNotification(
                    chatId: $zone->notify_telegram_chat_id,
                    caption: $caption,
                    rawBinary: $rawBinary,
                )) {
                    $channelsSent[] = 'telegram';
                }
            }

            if (! empty($channelsSent)) {
                $cooldownMinutes = max(1, (int) ($zone->notify_cooldown_minutes ?: 15));
                Cache::put($cooldownKey, now()->toIso8601String(), now()->addMinutes($cooldownMinutes));

                return ['sent' => true, 'channels' => $channelsSent];
            }

            return ['sent' => false, 'reason' => 'no_channels_succeeded'];
        } catch (\Throwable $e) {
            Log::warning('Error al procesar notificación externa de alerta', [
                'error' => $e->getMessage(),
            ]);

            return ['sent' => false, 'reason' => 'exception'];
        }
    }

    /**
     * Envía una notificación de prueba inmediata (sin aplicar ni bloquear por cooldown).
     */
    public function sendTestNotification(ZoneState $zone): array
    {
        $results = [];
        $nowFormatted = now()->format('d/m/Y H:i:s');
        $dashboardUrl = url('/dashboard');

        if ($zone->notify_email_enabled) {
            $apiKey = (string) config('prowltracker.notifications.resend_api_key');
            if ($apiKey === '') {
                $results[] = 'Correo (Resend): falta configurar RESEND_API_KEY en el archivo .env.';
            } elseif (blank($zone->notify_email_to)) {
                $results[] = 'Correo (Resend): falta indicar el correo electrónico destino.';
            } else {
                $ok = $this->sendResendEmail(
                    to: $zone->notify_email_to,
                    subject: "[ProwlTracker] Prueba de notificación — Zona «{$zone->zone}»",
                    zoneName: $zone->zone,
                    deviceId: 'nodo-prueba',
                    eventLabel: 'Prueba operativa de canal de alertas',
                    confidencePct: 95,
                    detectedAt: $nowFormatted,
                    dashboardUrl: $dashboardUrl,
                    rawBinary: null,
                    alertId: null,
                );
                $results[] = $ok
                    ? "Correo enviado correctamente a {$zone->notify_email_to} vía Resend."
                    : 'Error al enviar correo vía Resend' . ($this->lastError ? " ({$this->lastError})." : '.');
            }
        }

        if ($zone->notify_telegram_enabled) {
            $token = (string) config('prowltracker.notifications.telegram_bot_token');
            if ($token === '') {
                $results[] = 'Telegram: falta configurar TELEGRAM_BOT_TOKEN en el archivo .env.';
            } else {
                // Si el Chat ID está vacío, intentamos detectarlo automáticamente desde getUpdates
                if (blank($zone->notify_telegram_chat_id)) {
                    $detectedChatId = $this->detectTelegramChatId($token);
                    if ($detectedChatId !== null) {
                        $zone->update(['notify_telegram_chat_id' => $detectedChatId]);
                    }
                }

                if (blank($zone->notify_telegram_chat_id)) {
                    $results[] = 'Telegram: abre tu bot en la app de Telegram, presiona "Iniciar" (/start), envíale cualquier mensaje (ej. "Hola") y vuelve a probar.';
                } else {
                    $caption = implode("\n", [
                        "🛡️ *ProwlTracker — Prueba de Conexión*",
                        "",
                        "• *Zona:* `{$zone->zone}`",
                        "• *Estado:* Canal de notificaciones operativo",
                        "• *Fecha y hora:* {$nowFormatted}",
                        "",
                        "🔗 [Abrir consola de monitoreo]({$dashboardUrl})",
                    ]);
                    $ok = $this->sendTelegramNotification(
                        chatId: $zone->notify_telegram_chat_id,
                        caption: $caption,
                        rawBinary: null,
                    );
                    if (! $ok) {
                        // Si el usuario puso por error el ID del bot u otro ID inválido, intentamos detectar el real
                        $detectedChatId = $this->detectTelegramChatId($token);
                        if ($detectedChatId !== null && $detectedChatId !== (string) $zone->notify_telegram_chat_id) {
                            $zone->update(['notify_telegram_chat_id' => $detectedChatId]);
                            $ok = $this->sendTelegramNotification(
                                chatId: $detectedChatId,
                                caption: $caption,
                                rawBinary: null,
                            );
                        }
                    }
                    $results[] = $ok
                        ? "Mensaje de prueba enviado a Telegram (Chat ID: {$zone->notify_telegram_chat_id})."
                        : 'Error al enviar mensaje a Telegram' . ($this->lastError ? " ({$this->lastError})." : '.');
                }
            }
        }

        if (empty($results)) {
            $results[] = 'Activa primero al menos un canal (Correo o Telegram) y guarda la configuración antes de enviar una prueba.';
        }

        return $results;
    }

    private function sendResendEmail(
        string $to,
        string $subject,
        string $zoneName,
        string $deviceId,
        string $eventLabel,
        int $confidencePct,
        string $detectedAt,
        string $dashboardUrl,
        ?string $rawBinary,
        ?int $alertId,
    ): bool {
        $apiKey = (string) config('prowltracker.notifications.resend_api_key');
        if ($apiKey === '') {
            return false;
        }

        $from = (string) config('prowltracker.notifications.resend_from', 'ProwlTracker <onboarding@resend.dev>');

        $html = <<<HTML
        <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #F4F6F8; padding: 24px; color: #101827;">
            <div style="max-width: 560px; margin: 0 auto; background-color: #FFFFFF; border: 1px solid #CBD5E1;">
                <div style="background-color: #101827; color: #FFFFFF; padding: 14px 20px; border-left: 4px solid #B24C4C;">
                    <strong style="font-size: 14px; letter-spacing: 0.02em;">ProwlTracker — Consola de Monitoreo</strong>
                </div>
                <div style="padding: 20px;">
                    <p style="margin: 0 0 12px 0; font-size: 15px; font-weight: 600; color: #101827;">
                        {$eventLabel} en Zona «{$zoneName}»
                    </p>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 18px;">
                        <tr>
                            <td style="padding: 6px 0; color: #64748B; border-bottom: 1px solid #E2E8F0;">Dispositivo / Nodo</td>
                            <td style="padding: 6px 0; font-weight: 600; color: #101827; border-bottom: 1px solid #E2E8F0;">{$deviceId}</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #64748B; border-bottom: 1px solid #E2E8F0;">Nivel de confianza</td>
                            <td style="padding: 6px 0; font-weight: 600; color: #101827; border-bottom: 1px solid #E2E8F0;">{$confidencePct}%</td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; color: #64748B; border-bottom: 1px solid #E2E8F0;">Fecha y hora (24h)</td>
                            <td style="padding: 6px 0; font-weight: 600; color: #101827; border-bottom: 1px solid #E2E8F0;">{$detectedAt}</td>
                        </tr>
                    </table>
                    <div style="margin-top: 18px;">
                        <a href="{$dashboardUrl}" style="display: inline-block; background-color: #101827; color: #FFFFFF; text-decoration: none; padding: 9px 16px; font-size: 12px; font-weight: 600;">
                            Abrir panel de monitoreo
                        </a>
                    </div>
                </div>
            </div>
        </div>
        HTML;

        $payload = [
            'from' => $from,
            'to' => [$to],
            'subject' => $subject,
            'html' => $html,
        ];

        if ($rawBinary !== null && $rawBinary !== '') {
            $filename = $alertId ? "evidencia-alerta-{$alertId}.jpg" : 'evidencia-alerta.jpg';
            $payload['attachments'] = [
                [
                    'filename' => $filename,
                    'content' => base64_encode($rawBinary),
                ],
            ];
        }

        try {
            $this->lastError = null;
            $response = $this->httpClient(8)
                ->withToken($apiKey)
                ->acceptJson()
                ->post('https://api.resend.com/emails', $payload);

            if (! $response->successful()) {
                $this->lastError = (string) ($response->json('message') ?? $response->status());
                Log::warning('Resend API devolvió error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Excepción al enviar correo con Resend', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function sendTelegramNotification(string $chatId, string $caption, ?string $rawBinary): bool
    {
        $token = (string) config('prowltracker.notifications.telegram_bot_token');
        if ($token === '') {
            return false;
        }

        try {
            $this->lastError = null;
            if ($rawBinary !== null && $rawBinary !== '') {
                $response = $this->httpClient(10)
                    ->attach('photo', $rawBinary, 'evidencia.jpg')
                    ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                        'chat_id' => $chatId,
                        'caption' => $caption,
                        'parse_mode' => 'Markdown',
                    ]);
            } else {
                $response = $this->httpClient(8)
                    ->post("https://api.telegram.org/bot{$token}/sendMessage", [
                        'chat_id' => $chatId,
                        'text' => $caption,
                        'parse_mode' => 'Markdown',
                        'disable_web_page_preview' => true,
                    ]);
            }

            if (! $response->successful()) {
                $this->lastError = (string) ($response->json('description') ?? $response->status());
                Log::warning('Telegram API devolvió error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            Log::warning('Excepción al enviar notificación a Telegram', ['error' => $e->getMessage()]);

            return false;
        }
    }

    private function translateEvent(?string $rawType): string
    {
        $key = strtolower(trim((string) $rawType));
        if (isset(self::EVENT_TRANSLATIONS[$key])) {
            return self::EVENT_TRANSLATIONS[$key];
        }

        return ucfirst(strtolower(str_replace('_', ' ', $key ?: 'Evento detectado')));
    }

    /**
     * Consulta getUpdates en Telegram para detectar automáticamente el Chat ID
     * del último mensaje enviado al bot.
     */
    public function detectTelegramChatId(string $token): ?string
    {
        try {
            $response = $this->httpClient(6)->get("https://api.telegram.org/bot{$token}/getUpdates");
            if (! $response->successful()) {
                return null;
            }

            $updates = $response->json('result', []);
            if (! is_array($updates) || empty($updates)) {
                return null;
            }

            $last = end($updates);
            $chatId = $last['message']['chat']['id']
                ?? $last['edited_message']['chat']['id']
                ?? $last['my_chat_member']['chat']['id']
                ?? null;

            return $chatId !== null ? (string) $chatId : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
