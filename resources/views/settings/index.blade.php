<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-sm sm:text-base font-semibold text-white">
                    Configuración del sistema — Zona «{{ $zone->zone }}»
                </h1>
            </div>
            <span class="text-xs text-slate-300">
                Sincronización automática con el nodo de cámara
            </span>
        </div>
    </x-slot>

    @php
        $shortDayLabels = [
            0 => 'LUN',
            1 => 'MAR',
            2 => 'MIE',
            3 => 'JUE',
            4 => 'VIE',
            5 => 'SAB',
            6 => 'DOM',
        ];
    @endphp

    <div class="flex-1 flex flex-col bg-pt-bg">

        @if (session('status'))
            <div class="px-5 sm:px-8 py-2.5 bg-pt-graphite text-slate-200 border-b border-white/10 border-l-4 border-l-pt-green flex items-center gap-2.5 text-xs">
                <span class="w-2 h-2 rounded-full bg-pt-green shrink-0"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('settings.update') }}" class="divide-y divide-slate-300">
            @csrf
            @method('PUT')

            <!-- Sección 1: Horario Laboral y Días Hábiles -->
            <section class="bg-pt-surface px-5 sm:px-8 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-4">
                        <span class="text-xs font-medium text-pt-muted block">
                            Ciclo operativo
                        </span>
                        <h2 class="text-sm font-semibold text-pt-carbon mt-0.5">
                            Horario laboral y días hábiles
                        </h2>
                        <p class="mt-1.5 text-xs text-pt-muted leading-relaxed">
                            Dentro de este horario y en los días seleccionados, la zona opera con <strong>Personal presente</strong> (sin emitir alertas por el flujo habitual). Fuera de este rango cambia automáticamente a <strong>Zona armada (Vigilancia activa)</strong>.
                        </p>
                    </div>

                    <div class="lg:col-span-8 space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="work_start_hour" value="Hora de inicio de jornada (0 - 23 h)" />
                                <x-text-input
                                    id="work_start_hour"
                                    name="work_start_hour"
                                    type="number"
                                    min="0"
                                    max="23"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('work_start_hour', $settings['work_start_hour'])"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Formato 24 h (ejemplo: 8 = 08:00).</p>
                                <x-input-error :messages="$errors->get('work_start_hour')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="work_end_hour" value="Hora de cierre de jornada (1 - 24 h)" />
                                <x-text-input
                                    id="work_end_hour"
                                    name="work_end_hour"
                                    type="number"
                                    min="1"
                                    max="24"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('work_end_hour', $settings['work_end_hour'])"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Formato 24 h (ejemplo: 20 = 20:00).</p>
                                <x-input-error :messages="$errors->get('work_end_hour')" class="mt-1" />
                            </div>
                        </div>

                        <div>
                            <x-input-label value="Días laborables de la semana" />
                            @php
                                $selectedDays = array_map('intval', old('work_days', $settings['work_days']));
                            @endphp
                            <div class="mt-2 grid grid-cols-2 sm:grid-cols-7 gap-2">
                                @foreach ($dayNames as $dayIndex => $dayLabel)
                                    <label
                                        title="{{ $dayLabel }}"
                                        class="flex items-center justify-center gap-2 px-3 py-2 rounded-sm border border-slate-300 bg-pt-bg text-xs text-pt-carbon cursor-pointer hover:bg-slate-200/60 transition-colors"
                                    >
                                        <input
                                            type="checkbox"
                                            name="work_days[]"
                                            value="{{ $dayIndex }}"
                                            class="rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                            @checked(in_array($dayIndex, $selectedDays, true))
                                        />
                                        <span class="text-xs font-semibold">{{ $shortDayLabels[$dayIndex] ?? $dayLabel }}</span>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('work_days')" class="mt-1" />
                            <x-input-error :messages="$errors->get('work_days.*')" class="mt-1" />
                        </div>

                        <div class="pt-4 border-t border-slate-200">
                            <label class="inline-flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="adaptive_auto"
                                    value="1"
                                    class="mt-0.5 rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                    @checked(old('adaptive_auto', $zone->adaptive_auto))
                                />
                                <span class="text-xs text-pt-carbon leading-relaxed">
                                    <strong>Ajuste automático de horario (Aprendizaje por actividad):</strong>
                                    actualiza automáticamente la hora de entrada y salida cuando el patrón de personas detectadas por visión artificial supere el 60% de consistencia.
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección 2: Umbrales de Detección de Merodeo -->
            <section class="bg-[#EDF1F6] px-5 sm:px-8 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-4">
                        <span class="text-xs font-medium text-pt-muted block">
                            Sensibilidad de visión
                        </span>
                        <h2 class="text-sm font-semibold text-pt-carbon mt-0.5">
                            Tiempos de detección y espera entre alertas
                        </h2>
                        <p class="mt-1.5 text-xs text-pt-muted leading-relaxed">
                            Controla cuántos segundos de permanencia exige el nodo de visión artificial antes de emitir una alerta de merodeo.
                        </p>
                    </div>

                    <div class="lg:col-span-8 space-y-5">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label for="loitering_seconds_off" value="Umbral con zona armada / fuera de horario (segundos)" />
                                <x-text-input
                                    id="loitering_seconds_off"
                                    name="loitering_seconds_off"
                                    type="number"
                                    step="0.5"
                                    min="1"
                                    max="300"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('loitering_seconds_off', $settings['loitering_seconds_off'])"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Mayor sensibilidad cuando no hay personal (ej. 6 s).</p>
                                <x-input-error :messages="$errors->get('loitering_seconds_off')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="loitering_seconds_work" value="Umbral en horario laboral / personal presente (segundos)" />
                                <x-text-input
                                    id="loitering_seconds_work"
                                    name="loitering_seconds_work"
                                    type="number"
                                    step="0.5"
                                    min="1"
                                    max="300"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('loitering_seconds_work', $settings['loitering_seconds_work'])"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Tolerancia durante horario laboral (ej. 15 s).</p>
                                <x-input-error :messages="$errors->get('loitering_seconds_work')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="transit_seconds" value="Tiempo máximo de paso o tránsito (segundos)" />
                                <x-text-input
                                    id="transit_seconds"
                                    name="transit_seconds"
                                    type="number"
                                    step="0.5"
                                    min="0.5"
                                    max="60"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('transit_seconds', $settings['transit_seconds'])"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Cruces breves descartados como tránsito normal (ej. 3 s).</p>
                                <x-input-error :messages="$errors->get('transit_seconds')" class="mt-1" />
                            </div>

                            <div>
                                <x-input-label for="alert_cooldown_seconds" value="Tiempo de espera entre alertas consecutivas (segundos)" />
                                <x-text-input
                                    id="alert_cooldown_seconds"
                                    name="alert_cooldown_seconds"
                                    type="number"
                                    step="1"
                                    min="5"
                                    max="3600"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('alert_cooldown_seconds', $settings['alert_cooldown_seconds'])"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Intervalo mínimo antes de enviar otra alerta (ej. 30 s).</p>
                                <x-input-error :messages="$errors->get('alert_cooldown_seconds')" class="mt-1" />
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Sección 3: Notificaciones Externas al Operador (Correo / Telegram con Cooldown) -->
            @php
                $resendConfigured = filled(config('prowltracker.notifications.resend_api_key'));
                $telegramConfigured = filled(config('prowltracker.notifications.telegram_bot_token'));
            @endphp
            <section class="bg-pt-surface px-5 sm:px-8 py-6">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                    <div class="lg:col-span-4">
                        <span class="text-xs font-medium text-pt-muted block">
                            Avisos remotos
                        </span>
                        <h2 class="text-sm font-semibold text-pt-carbon mt-0.5">
                            Notificaciones por Correo (Resend) y Telegram
                        </h2>
                        <p class="mt-1.5 text-xs text-pt-muted leading-relaxed">
                            Envía avisos automáticos con la captura de evidencia cuando no estás frente al panel. Incluye un tiempo de enfriamiento (<em>cooldown</em>) para evitar saturar tu bandeja si ocurren varias detecciones seguidas.
                        </p>

                        <div class="mt-4 pt-3 border-t border-slate-200 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-pt-muted">Clave API Resend (.env):</span>
                                <span class="inline-flex items-center gap-1.5 font-medium {{ $resendConfigured ? 'text-pt-carbon' : 'text-pt-muted' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $resendConfigured ? 'bg-pt-green' : 'bg-slate-400' }}"></span>
                                    {{ $resendConfigured ? 'Configurada' : 'No definida' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-pt-muted">Token Bot Telegram (.env):</span>
                                <span class="inline-flex items-center gap-1.5 font-medium {{ $telegramConfigured ? 'text-pt-carbon' : 'text-pt-muted' }}">
                                    <span class="w-1.5 h-1.5 rounded-full {{ $telegramConfigured ? 'bg-pt-green' : 'bg-slate-400' }}"></span>
                                    {{ $telegramConfigured ? 'Configurado' : 'No definido' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-8 space-y-5">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200">
                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="notify_enabled"
                                    value="1"
                                    class="rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                    @checked(old('notify_enabled', $zone->notify_enabled))
                                />
                                <span class="text-xs font-semibold text-pt-carbon">
                                    Activar notificaciones externas al operador
                                </span>
                            </label>

                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="notify_only_armed"
                                    value="1"
                                    class="rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                    @checked(old('notify_only_armed', $zone->notify_only_armed ?? true))
                                />
                                <span class="text-xs text-pt-carbon">
                                    Notificar únicamente cuando la zona esté armada
                                </span>
                            </label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-12">
                            <!-- Canal 1: Correo electrónico (Resend) -->
                            <div class="sm:col-span-6 p-3.5 bg-pt-bg border border-slate-200 rounded-sm space-y-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="notify_email_enabled"
                                        value="1"
                                        class="rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                        @checked(old('notify_email_enabled', $zone->notify_email_enabled))
                                    />
                                    <span class="text-xs font-semibold text-pt-carbon">Correo electrónico (Resend HTTP API)</span>
                                </label>

                                <div>
                                    <x-input-label for="notify_email_to" value="Correo destino del administrador" />
                                    <x-text-input
                                        id="notify_email_to"
                                        name="notify_email_to"
                                        type="email"
                                        maxlength="180"
                                        placeholder="gomezesquivelbrian@gmail.com"
                                        class="mt-1 block w-full text-xs rounded-sm"
                                        :value="old('notify_email_to', $zone->notify_email_to ?: 'gomezesquivelbrian@gmail.com')"
                                    />
                                    <p class="mt-1 text-[11px] text-pt-muted">Recibe resumen del incidente y evidencia adjunta.</p>
                                    <x-input-error :messages="$errors->get('notify_email_to')" class="mt-1" />
                                </div>
                            </div>

                            <!-- Canal 2: Telegram Bot -->
                            <div class="sm:col-span-6 p-3.5 bg-pt-bg border border-slate-200 rounded-sm space-y-3">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        name="notify_telegram_enabled"
                                        value="1"
                                        class="rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                        @checked(old('notify_telegram_enabled', $zone->notify_telegram_enabled))
                                    />
                                    <span class="text-xs font-semibold text-pt-carbon">Mensajería instantánea (Telegram Bot)</span>
                                </label>

                                <div>
                                    <x-input-label for="notify_telegram_chat_id" value="Chat ID de Telegram" />
                                    <x-text-input
                                        id="notify_telegram_chat_id"
                                        name="notify_telegram_chat_id"
                                        type="text"
                                        maxlength="100"
                                        placeholder="Ej. 123456789"
                                        class="mt-1 block w-full text-xs rounded-sm tabular-nums"
                                        :value="old('notify_telegram_chat_id', $zone->notify_telegram_chat_id)"
                                    />
                                    <p class="mt-1 text-[11px] text-pt-muted">Envía la fotografía de evidencia al instante al celular.</p>
                                    <x-input-error :messages="$errors->get('notify_telegram_chat_id')" class="mt-1" />
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2 items-end pt-1">
                            <div>
                                <x-input-label for="notify_cooldown_minutes" value="Tiempo de espera entre notificaciones externas (minutos)" />
                                <x-text-input
                                    id="notify_cooldown_minutes"
                                    name="notify_cooldown_minutes"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    class="mt-1.5 block w-full text-xs rounded-sm tabular-nums"
                                    :value="old('notify_cooldown_minutes', $zone->notify_cooldown_minutes ?? 15)"
                                    required
                                />
                                <p class="mt-1 text-[11px] text-pt-muted">Tras enviar un aviso, no se enviarán más correos/mensajes durante este lapso (ej. 15 min).</p>
                                <x-input-error :messages="$errors->get('notify_cooldown_minutes')" class="mt-1" />
                            </div>

                            <div class="flex flex-wrap items-center sm:justify-end gap-2.5 pt-2">
                                <button
                                    type="submit"
                                    name="after_save"
                                    value="test"
                                    class="px-3.5 py-2 rounded-sm bg-white border border-slate-300 hover:bg-slate-100 text-pt-carbon text-xs font-medium transition-colors"
                                >
                                    Guardar y probar canales
                                </button>

                                <button
                                    type="submit"
                                    name="after_save"
                                    value="save"
                                    class="px-5 py-2 rounded-sm bg-pt-carbon hover:bg-pt-graphite text-white text-xs font-medium transition-colors"
                                >
                                    Guardar configuración
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </form>

        <!-- Sección 3: Aprendizaje Horario -->
        <section class="bg-pt-surface border-t border-slate-300 px-5 sm:px-8 py-6 flex-1">
            <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div>
                    <span class="text-xs font-medium text-pt-muted block">
                        Perfil de actividad registrada
                    </span>
                    <h2 class="text-sm font-semibold text-pt-carbon mt-0.5">
                        Distribución horaria de presencia de personas
                    </h2>
                </div>

                @if ($analysis['differs'] && $analysis['start'] !== null)
                    <form method="POST" action="{{ route('settings.apply-suggestion') }}">
                        @csrf
                        <button
                            type="submit"
                            class="px-3.5 py-1.5 rounded-sm bg-pt-amber hover:bg-[#96682a] text-white text-xs font-medium transition-colors"
                        >
                            Aplicar sugerencia {{ sprintf('%02d:00 – %02d:00', $analysis['start'], $analysis['end']) }}
                        </button>
                    </form>
                @endif
            </div>

            <div class="text-xs text-pt-carbon mb-4">
                @if (! $analysis['ready'])
                    <p class="text-pt-muted">
                        Recopilando muestras: <strong>{{ $analysis['days_observed'] }} / {{ $analysis['min_days'] }}</strong> días hábiles observados.
                    </p>
                @elseif ($analysis['start'] === null)
                    <p class="text-pt-muted">
                        Se observaron {{ $analysis['days_observed'] }} días hábiles, pero ninguna franja supera aún el 50% de ocupación.
                    </p>
                @else
                    <div class="flex flex-wrap items-center gap-3">
                        <span>Franja detectada: <strong class="tabular-nums">{{ sprintf('%02d:00 – %02d:00', $analysis['start'], $analysis['end']) }}</strong></span>
                        <span class="text-slate-300">•</span>
                        <span>Consistencia: <strong class="tabular-nums">{{ (int) round(($analysis['confidence'] ?? 0) * 100) }}%</strong></span>
                        <span class="text-slate-300">•</span>
                        <span>Base: <strong>{{ $analysis['days_observed'] }} días hábiles</strong></span>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-12 sm:grid-cols-24 gap-1 pt-2 items-end h-28 bg-pt-bg p-3 rounded-sm border border-slate-200">
                @foreach ($analysis['hourly'] as $hour => $ratio)
                    @php
                        $pct = (int) round($ratio * 100);
                        $isSuggested = $analysis['start'] !== null && $hour >= $analysis['start'] && $hour < $analysis['end'];
                    @endphp
                    <div class="flex flex-col items-center justify-end h-full" title="{{ sprintf('%02d:00', $hour) }}: {{ $pct }}% de los días">
                        <div class="w-full bg-slate-200/80 flex items-end h-16 overflow-hidden">
                            <div
                                class="w-full {{ $isSuggested ? 'bg-pt-carbon' : 'bg-slate-400' }}"
                                style="height: {{ max(4, $pct) }}%"
                            ></div>
                        </div>
                        <span class="text-[10px] text-pt-muted tabular-nums mt-1">{{ sprintf('%02d', $hour) }}</span>
                    </div>
                @endforeach
            </div>
        </section>

    </div>
</x-app-layout>
