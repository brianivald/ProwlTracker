# Documentación Técnica del Sistema — ProwlTracker Web

Documento técnico de referencia sobre la arquitectura, configuración en la nube, seguridad, servicios integrados, estándares de diseño del frontend y procedimientos de extensión para **ProwlTracker Web**.

---

## 1. Arquitectura General del Sistema

ProwlTracker opera bajo un modelo híbrido:
1. **Nodo Edge de Visión Artificial (Local / On-Premise):** Procesa flujos de video con YOLO / OpenCV. Detecta presencia humana, evalúa tiempos de permanencia (merodeo / intrusión) y genera capturas de evidencia.
2. **Consola Web y Servidor Central (Cloud / Render):** Gestiona la programación horaria, el aprendizaje de ocupación, los registros de eventos de seguridad, la administración de almacenamiento y el envío de notificaciones externas al operador.

```
+-------------------------------------------------------------+
|             NODO EDGE (Python / YOLO / OpenCV)              |
|                     (Máquina Local)                         |
+-------------------------------------------------------------+
         |                                           ^
         | POST /api/alerts (Firma HMAC)             | GET /api/schedule
         | POST /api/activity (Firma HMAC)           | (Firma HMAC)
         v                                           v
+-------------------------------------------------------------+
|               PROWLTRACKER WEB (Laravel 11)                 |
|            (Docker Web Service en Render.com)               |
+-------------------------------------------------------------+
    |                 |                   |                |
    v                 v                   v                v
+----------+   +--------------+    +------------+   +------------+
| Neon DB  |   | Cloudflare   |    |   Resend   |   |  Telegram  |
| (Postgre |   |      R2      |    | (Email API |   |  Bot API   |
|   SQL)   |   | (S3 Storage) |    |    443)    |   |  (Foto/443)|
+----------+   +--------------+    +------------+   +------------+
```

---

## 2. Pila Tecnológica

- **Backend:** Laravel 11 / 13 (PHP 8.3 / Apache runtime en contenedor Debian slim).
- **Frontend:** Blade, Tailwind CSS 3, Alpine.js 3, Vite.
- **Base de Datos:** PostgreSQL en Neon.tech (driver `pgsql` con PDO emulado para pooler y SSL obligatorio).
- **Almacenamiento de Objetos:** Cloudflare R2 (compatible con API S3 mediante `league/flysystem-aws-s3-v3`).
- **Notificaciones Externas:** Resend HTTP API (correos vía HTTPS) y Telegram Bot API (mensajería directa con imágenes vía HTTPS).
- **Infraestructura de Despliegue:** Render Web Service (Docker multi-etapa, puerto dinámico `$PORT`, health check `/up`).

---

## 3. Seguridad de API y Firma Criptográfica (HMAC-SHA256)

Para proteger los endpoints del nodo (`/api/alerts`, `/api/schedule`, `/api/activity`) en internet abierto sin recurrir a tokens estáticos vulnerables, se implementó firma criptográfica por solicitud en el middleware `App\Http\Middleware\VerifyNodeSignature`.

### 3.1. Cadena Canónica
Cada solicitud HTTP entrante es validada construyendo una cadena canónica unívoca:

$$\text{Canonical} = \text{METHOD} + \text{"\n"} + \text{/path} + \text{"\n"} + \text{query\_ordenada} + \text{"\n"} + \text{timestamp} + \text{"\n"} + \text{nonce} + \text{"\n"} + \text{sha256(body)}$$

La firma esperada es calculada mediante:
$$\text{Signature} = \text{HMAC-SHA256}(\text{Canonical}, \text{NODE\_API\_SECRET})$$

### 3.2. Encabezados HTTP Requeridos
- `X-PT-Timestamp`: Timestamp UNIX en segundos.
- `X-PT-Nonce`: Cadena aleatoria única de 16 a 64 caracteres.
- `X-PT-Signature`: Firma resultante en hexadecimal (minúsculas).

### 3.3. Mecanismos de Protección
1. **Anti-Tampering:** Cualquier alteración del cuerpo (JSON / base64), método o query string invalida la firma.
2. **Anti-Clock-Skew:** Se rechazan marcas de tiempo con desfase mayor a `NODE_API_MAX_SKEW` (300 segundos por defecto).
3. **Anti-Replay:** Cada `nonce` se guarda en caché durante $2 \times \text{max\_skew}$; solicitudes con nonce repetido son rechazadas con HTTP 401.
4. **Comportamiento por Entorno:**
   - En `APP_ENV=production`: Falla cerrado con HTTP 503 si `NODE_API_SECRET` no está configurado.
   - En `APP_ENV=local` o `testing`: Si el secreto está vacío, permite paso sin firma para facilitar desarrollo en LAN.

---

## 4. Configuración de Servicios en la Nube

### 4.1. Base de Datos PostgreSQL (Neon.tech)
1. Crear proyecto en Neon y obtener el URI de conexión.
2. **Configuración en Render:**
   - Variable `DB_URL`: `postgresql://usuario:password@ep-xxxx.us-east-2.aws.neon.tech/neondb?sslmode=require`
   - Variable `DB_CONNECTION`: `pgsql`
   - Variable `DB_SSLMODE`: `require`
   - *Nota Técnica:* Durante migraciones iniciales o cambios estructurales (`ALTER TABLE`), utilizar la conexión directa sin `-pooler` o mantener `\PDO::ATTR_EMULATE_PREPARES => true` en `config/database.php`.

### 4.2. Almacenamiento de Evidencias (Cloudflare R2)
Cloudflare R2 almacena las capturas fotográficas sin costo por transferencia saliente (Egress).

1. Crear bucket en Cloudflare R2 (ejemplo: `prowltracker-evidencias`).
2. Generar un API Token con permisos **Object Read & Write** sobre el bucket.
3. **Variables requeridas en Render:**
   ```env
   EVIDENCE_DISK=s3
   AWS_ACCESS_KEY_ID=<Access_Key_ID_R2>
   AWS_SECRET_ACCESS_KEY=<Secret_Access_Key_R2>
   AWS_DEFAULT_REGION=auto
   AWS_BUCKET=prowltracker-evidencias
   AWS_ENDPOINT=https://<ACCOUNT_ID>.r2.cloudflarestorage.com
   AWS_USE_PATH_STYLE_ENDPOINT=false
   ```
4. **Entrega Segura de Evidencias:**
   Las imágenes nunca se exponen públicamente. Se sirven a través del controlador `App\Http\Controllers\EvidenceController` mediante la ruta autenticada `GET /evidence/{alert}` con encabezados `Cache-Control: private` y `X-Content-Type-Options: nosniff`.

### 4.3. Notificaciones por Correo (Resend HTTP API)
Evita el bloqueo de puertos SMTP (`25`, `465`, `587`) en planes gratuitos al operar completamente sobre HTTPS (puerto `443`).

- **Variables:**
  ```env
  RESEND_API_KEY=re_xxxxxxxxxxxxxx
  RESEND_FROM_ADDRESS="ProwlTracker <onboarding@resend.dev>"
  ```
- **Funcionamiento (`App\Services\AlertNotificationService`):**
  - Genera una llamada REST a `https://api.resend.com/emails`.
  - Adjunta el binario decodificado de la fotografía como `evidencia-alerta-{id}.jpg`.
  - En plan gratuito de Resend sin dominio personalizado, el destinatario debe ser el correo registrado de la cuenta.

### 4.4. Notificaciones por Telegram (Telegram Bot API)
Envío instantáneo de notificaciones push al dispositivo móvil del operador con imagen fotográfica embebida.

- **Variables:**
  ```env
  TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrSTUvwxYZ
  ```
- **Funcionamiento:**
  - Envía la imagen mediante multipart a `https://api.telegram.org/bot<TOKEN>/sendPhoto`.
  - El campo `Chat ID` se almacena en la tabla `zone_states` (columna `notify_telegram_chat_id`).
  - El servicio cuenta con autodetección de Chat ID consultando `/getUpdates` si el operador no lo conoce.

### 4.5. Plataforma de Hosting y Despliegue (Render)
ProwlTracker Web se aloja en **Render** como un **Web Service basado en Docker**.

- **Modalidad:** Plan gratuito (*Free Tier*) de Render ejecutando la imagen Docker construida desde el repositorio.
- **Configuración de Red y Puertos:** Render expone externamente la app en HTTPS y asigna un puerto dinámico mediante la variable de entorno `$PORT` (10000 por defecto). Apache se reconfigura en el arranque para escuchar en dicho puerto.
- **Monitoreo de Salud:** Render monitorea el estado del contenedor a través de la ruta `/up` (`healthCheckPath: /up` en `render.yaml`).
- **Persistencia y Sesiones:** Al contar con un sistema de archivos efímero en el plan gratuito, las sesiones, caché y colas se delegan a la base de datos PostgreSQL (`SESSION_DRIVER=database`, `CACHE_STORE=database`), y las evidencias se delegan a Cloudflare R2 (`EVIDENCE_DISK=s3`).

---

## 5. Módulos Implementados

### 5.1. Gestión y Depuración de Almacenamiento
Ubicado en `App\Http\Controllers\StorageManagementController` y vista `resources/views/storage/index.blade.php`.

- **Métricas Globales:** Agrupa evidencias en memoria o por fragmentos calculando archivos existentes y bytes en disco (local o S3) clasificados por estado: `falsa_alarma`, `personal`, `nueva`, `intrusion`.
- **Filtros Soportados:**
  - Por clasificación / estado (`all`, `non_intrusion`, `falsa_alarma`, `personal`, `nueva`, `intrusion`).
  - Por antigüedad (`older_than`: 7, 15, 30, 60 días).
  - Por fechas exactas (`date_from`, `date_to`).
  - Por nodo de origen (`device_id`).
- **Eliminación Segura con Limpieza Física:**
  - Hook `deleting` en el modelo `App\Models\Alert` asegura que antes de borrar la fila de la base de datos se ejecute `Storage::disk(...)->delete($alert->image_path)`.
  - Acción masiva `DELETE /storage-management/bulk` procesa registros en chunks (`chunkById(100)`), contabiliza espacio liberado y ofrece protección por defecto para no eliminar intrusiones confirmadas (`protect_intrusions=true`).

### 5.2. Control de Notificaciones y Lógica de Cooldown
Ubicado en `App\Services\AlertNotificationService`.

- **Condición de Disparo:** Se evalúa en `AlertController::store` tras registrar con éxito la alerta y guardar la imagen.
- **Validación de Estado:**
  - Si `notify_only_armed` está activo, solo notifica si `ZoneState::isCurrentlyArmed()` es `true`.
- **Tiempo de Enfriamiento (Cooldown):**
  - Utiliza la llave de caché `prowl:notify-cooldown:{zone}` con TTL en minutos (`notify_cooldown_minutes`).
  - Si una alerta ocurre dentro de la ventana de cooldown, la alerta se almacena normalmente en el panel, pero se suprime el envío de correo y Telegram para evitar saturación.
- **Prueba Operativa:**
  - Método `sendTestNotification` accesible desde Configuración mediante `POST /settings/test-notification` para verificar canales sin activar el cooldown.

### 5.3. Monitoreo Reactivo de Conexión del Nodo
- Endpoint ligero: `GET /node-status` en `routes/web.php`.
- Cada solicitud válida a `/api/schedule`, `/api/activity` o `/api/alerts` actualiza la marca de tiempo `prowl:node-last-seen` en caché.
- Las vistas `dashboard.blade.php` y `navigation.blade.php` consultan `/node-status` cada 10 segundos con `fetch()` en Alpine.js.
- Si la última señal supera `NODE_ONLINE_TTL` (120 segundos por defecto en `config/prowltracker.php`), el estado cambia dinámicamente de **Nodo conectado / Sincronizado** (verde) a **Nodo desconectado / En espera** (gris con hora del último contacto).

---

## 6. Estándares Visuales y Guía de Frontend

La interfaz de ProwlTracker sigue una línea de diseño sobria, técnica e industrial orientada a consolas NOC/SOC. Se prohíben efectos brillantes o elementos decorativos genéricos tipo IA.

### 6.1. Paleta de Colores Semántica
Definida en `tailwind.config.js`:
- `pt-carbon` (`#101827`): Fondos de barra lateral, cabeceras oscuras y botones principales de acción neutra.
- `pt-graphite` (`#202B3B`): Contenedores secundarios oscuros, estados activos en navegación y subpaneles.
- `pt-surface` (`#FFFFFF`): Superficie principal de contenido y tarjetas de datos.
- `pt-bg` (`#F4F6F8`): Fondo neutro general de la aplicación.
- `pt-muted` (`#64748B`): Texto secundario, descripciones y leyendas técnicas.
- `pt-red` (`#B24C4C`): **Color reservado.** Solo para estado `ARMED`, intrusiones confirmadas y alertas críticas pendientes.
- `pt-green` (`#3D8467`): **Color reservado.** Solo para estado de conexión activa del nodo y confirmación de personal autorizado.
- `pt-amber` (`#AD7A34`): Advertencias operativas y estado vigente de descanso en calendario.

### 6.2. Reglas Tipográficas y Estructurales
- **Formato Horario:** Exclusivamente formato de 24 horas (`dd/mm/yyyy HH:mm:ss` o `HH:mm`).
- **Números Técnicos:** Aplicar siempre la clase `tabular-nums` en fechas, horas, contadores, porcentajes y bytes para evitar saltos de línea y desalineación monospaciada.
- **Bordes:** Utilizar radios mínimos (`rounded-sm` o bordes rectos). Evitar cápsulas (`rounded-full`) salvo para los puntos de estado (dots).
- **Separadores:** Fondos diferenciados sutiles (`bg-[#E6EBF1]`, `bg-[#E9EEF4]`) y bordes (`border-slate-200`, `border-slate-300`).

---

## 7. Procedimiento para Agregar Nuevas Pestañas y Vistas

Para mantener la coherencia del sistema al crear una nueva sección (por ejemplo: *Auditoría* o *Dispositivos*):

### Paso 1: Definir Ruta y Controlador
En `routes/web.php` dentro del grupo autenticado:
```php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dispositivos', [DeviceController::class, 'index'])->name('devices.index');
});
```

### Paso 2: Registrar en el Sidebar (`resources/views/layouts/navigation.blade.php`)
Ubicar la categoría adecuada (*Operaciones*, *Gestión*, o *Sistema*) y agregar el enlace manteniendo las clases de estado activo:
```blade
<a
    href="{{ route('devices.index') }}"
    class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium transition-colors border-l-2 {{ request()->routeIs('devices.*') ? 'bg-pt-graphite text-white border-slate-200' : 'text-slate-400 hover:bg-pt-graphite/50 hover:text-slate-200 border-transparent' }}"
>
    <!-- Icono SVG con stroke-width="1.75" -->
    <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('devices.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
        ...
    </svg>
    <span>Dispositivos</span>
</a>
```

### Paso 3: Estructurar la Plantilla Blade (`resources/views/devices/index.blade.php`)
Seguir la estructura estándar de tres capas utilizada en `storage/index.blade.php` y `calendar/index.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-sm sm:text-base font-semibold text-white">
                Título de la Sección
            </h1>
            <span class="text-xs text-slate-300">Metadato contextual</span>
        </div>
    </x-slot>

    <div class="flex-1 flex flex-col bg-pt-bg">
        <!-- 1. Franja de Filtros o Entrada de Datos -->
        <section class="bg-[#E6EBF1] border-b border-slate-300 px-5 sm:px-8 py-4">
            ...
        </section>

        <!-- 2. Listado / Grilla Técnica -->
        <section class="flex-1 bg-pt-surface">
            <div class="px-5 sm:px-8 py-2.5 bg-[#E9EEF4] border-b border-slate-300/80 text-xs font-medium text-pt-muted">
                Encabezados o Resumen
            </div>
            <div class="divide-y divide-slate-200">
                ...
            </div>
        </section>
    </div>
</x-app-layout>
```

---

## 8. Despliegue y Ciclo de Contenedor en Docker

El ciclo de despliegue automatizado está definido en `Dockerfile` y `docker/entrypoint.sh`:

1. **Construcción Multi-Etapa:**
   - `Etapa 1 (Vendor):` PHP 8.3 CLI + Composer (`composer install --no-dev --prefer-dist --optimize-autoloader`).
   - `Etapa 2 (Assets):` Node 22 Alpine (`npm install --ignore-scripts` + `npm run build`).
   - `Etapa 3 (Runtime):` Apache + PHP 8.3 con extensiones compiladas (`pdo_pgsql`, `pdo_mysql`, `opcache`).
2. **Arranque en Contenedor (`docker/entrypoint.sh`):**
   - Vincula Apache al puerto dinámico `$PORT` inyectado por Render (por defecto `10000`).
   - Genera caché de producción: `config:cache`, `route:cache`, `view:cache`.
   - Ejecuta migraciones pendientes: `php artisan migrate --force`.
   - Garantiza el operador inicial de forma idempotente con `php artisan prowl:ensure-admin` utilizando `ADMIN_EMAIL` y `ADMIN_PASSWORD`.
   - Inicia `apache2-foreground`.
