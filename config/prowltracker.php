<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API del nodo de visión (Python)
    |--------------------------------------------------------------------------
    |
    | Las rutas /api/* solo aceptan solicitudes firmadas con HMAC-SHA256 usando
    | este secreto compartido. Debe ser el mismo valor en Laravel y en el nodo.
    | Genera uno con:  php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    |
    | Si el secreto está vacío:
    |   - en producción la API responde 503 (nunca queda abierta por error);
    |   - en local/testing se permite el acceso sin firma (desarrollo en LAN).
    |
    */

    'node_api' => [
        'secret' => env('NODE_API_SECRET'),

        // Segundos de tolerancia entre el reloj del nodo y el del servidor.
        'max_clock_skew' => (int) env('NODE_API_MAX_CLOCK_SKEW', 300),

        // Solicitudes por minuto permitidas por IP (el nodo consulta cada pocos segundos).
        'rate_limit' => (int) env('NODE_API_RATE_LIMIT', 240),

        // Segundos máximos desde el último latido (/api/schedule, /api/activity, /api/alerts)
        // para considerar al nodo Python como "conectado / sincronizado" en el panel.
        'online_ttl' => (int) env('NODE_ONLINE_TTL', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Almacenamiento de evidencias fotográficas
    |--------------------------------------------------------------------------
    |
    | Disco donde se guardan las capturas. En local se usa "public"; en Render
    | (disco efímero) usa "s3" apuntando a un bucket compatible con S3
    | (Cloudflare R2, Supabase Storage, AWS S3...). Las imágenes se sirven
    | siempre a través de una ruta autenticada, nunca de forma pública.
    |
    */

    'evidence_disk' => env('EVIDENCE_DISK', 'public'),

    // Tamaño máximo de una captura decodificada (bytes).
    'evidence_max_bytes' => (int) env('EVIDENCE_MAX_BYTES', 5 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Cuentas de usuario
    |--------------------------------------------------------------------------
    |
    | En internet el registro público debe estar cerrado. El primer operador
    | se crea al arrancar el contenedor si se definen ADMIN_EMAIL y
    | ADMIN_PASSWORD (comando prowl:ensure-admin).
    |
    */

    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),

    'admin' => [
        'name' => env('ADMIN_NAME', 'Operador'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notificaciones externas (Resend HTTP API y Telegram Bot API)
    |--------------------------------------------------------------------------
    |
    | Ambas integraciones usan HTTPS (puerto 443), por lo que funcionan sin
    | restricciones en el plan gratuito de Render.
    |
    */

    'notifications' => [
        'resend_api_key' => env('RESEND_API_KEY'),
        'resend_from' => env('RESEND_FROM_ADDRESS', 'ProwlTracker <onboarding@resend.dev>'),
        'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
    ],

];
