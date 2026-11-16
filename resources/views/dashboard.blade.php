<x-app-layout>
    @php
        $zoneSettings = $zone->settingsPayload();
        $workStart = sprintf('%02d:00', $zoneSettings['work_start_hour']);
        $workEnd = sprintf('%02d:00', $zoneSettings['work_end_hour']);
        $sentrySeconds = rtrim(rtrim(number_format($zoneSettings['loitering_seconds_off'], 1), '0'), '.');
        $workSeconds = rtrim(rtrim(number_format($zoneSettings['loitering_seconds_work'], 1), '0'), '.');
        $transitSeconds = rtrim(rtrim(number_format($zoneSettings['transit_seconds'], 1), '0'), '.');

        $currentWeekday = now()->dayOfWeekIso - 1; // 0 = Lunes ... 6 = Domingo
        $isWorkDay = in_array($currentWeekday, $zoneSettings['work_days'], true) && ! $nonWorkingToday;
        $isWithinWorkHours = now()->hour >= $zoneSettings['work_start_hour'] && now()->hour < $zoneSettings['work_end_hour'];

        if ($override === 'armed') {
            $effectiveMode = 'ARMED';
            $statusSummary = 'Vigilancia activa • Zona desocupada';
            $occupancyHeadline = 'Zona armada — Detección de merodeo e intrusión activa';
            $originLabel = 'Manual (Activado por el operador)';
        } elseif ($override === 'occupied') {
            $effectiveMode = 'OCCUPIED';
            $statusSummary = 'Pausa operativa • Personal en zona';
            $occupancyHeadline = 'Personal presente — Alertas en pausa temporal';
            $originLabel = 'Manual (Confirmado por el operador)';
        } elseif ($nonWorkingToday) {
            $effectiveMode = 'ARMED';
            $statusSummary = 'Vigilancia activa • Día no laborable';
            $occupancyHeadline = 'Zona armada — Vigilancia continua por día de descanso';
            $originLabel = 'Calendario (Día no laborable' . ($nonWorkingToday->reason ? ': ' . $nonWorkingToday->reason : '') . ')';
        } elseif (! $isWorkDay) {
            $effectiveMode = 'ARMED';
            $statusSummary = 'Vigilancia activa • Zona desocupada';
            $occupancyHeadline = 'Zona armada — Fuera de días laborables';
            $originLabel = 'Automático (Día fuera de jornada laboral)';
        } elseif ($isWithinWorkHours) {
            $effectiveMode = 'OCCUPIED';
            $statusSummary = 'Jornada laboral • Personal en zona';
            $occupancyHeadline = 'Personal presente — Horario laboral en curso';
            $originLabel = "Automático (Horario laboral {$workStart} – {$workEnd})";
        } else {
            $effectiveMode = 'ARMED';
            $statusSummary = 'Vigilancia activa • Zona desocupada';
            $occupancyHeadline = 'Zona armada — Vigilancia fuera de horario laboral';
            $originLabel = "Automático (Fuera de horario {$workStart} – {$workEnd})";
        }

        $overrideUntilIso = ($override !== null && $zone->override_until)
            ? $zone->override_until->toIso8601String()
            : null;

        $alerts24hCount = $alerts24hCount ?? $alerts->where('created_at', '>=', now()->subHours(24))->count();
        $pendingAlertsCount = $pendingAlertsCount ?? $alerts->where('status', 'nueva')->count();
        $totalAlertsCount = $totalAlertsCount ?? (method_exists($alerts, 'total') ? $alerts->total() : $alerts->count());
        $devices = $devices ?? $alerts->pluck('device_id')->unique()->sort()->values();
        $todayMaxPersons = $todayMaxPersons ?? 0;
        $todaySamples = $todaySamples ?? 0;
        $todayActiveHours = $todayActiveHours ?? 0;

        $eventTranslations = [
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

        $translateEvent = function (?string $rawType) use ($eventTranslations): string {
            $key = strtolower(trim((string) $rawType));
            if (isset($eventTranslations[$key])) {
                return $eventTranslations[$key];
            }
            return ucfirst(strtolower(str_replace('_', ' ', $key ?: 'Evento detectado')));
        };

        $statusConfig = [
            'nueva' => [
                'state' => 'Pendiente de revisión',
                'classification' => 'Sin clasificar',
                'dot' => 'bg-pt-red',
                'text' => 'text-pt-red font-medium',
            ],
            'intrusion' => [
                'state' => 'Revisada',
                'classification' => 'Intrusión confirmada',
                'dot' => 'bg-pt-red',
                'text' => 'text-pt-red font-medium',
            ],
            'personal' => [
                'state' => 'Revisada',
                'classification' => 'Personal autorizado',
                'dot' => 'bg-pt-green',
                'text' => 'text-pt-carbon font-medium',
            ],
            'falsa_alarma' => [
                'state' => 'Revisada',
                'classification' => 'Falsa alarma',
                'dot' => 'bg-slate-400',
                'text' => 'text-pt-muted',
            ],
        ];

        $lightboxItems = $alerts->map(function ($alert) use ($statusConfig, $translateEvent) {
            $cfg = $statusConfig[$alert->status] ?? $statusConfig['nueva'];
            $confPct = $alert->confidence <= 1
                ? (int) round($alert->confidence * 100)
                : (int) round($alert->confidence);
            $confPct = max(0, min(100, $confPct));
            $requiresAttention = in_array($alert->status, ['nueva', 'intrusion'], true);

            return [
                'id' => $alert->id,
                'imageUrl' => route('alerts.evidence', $alert),
                'date' => $alert->created_at->format('d/m/Y H:i:s'),
                'reviewedAt' => $alert->reviewed_at ? $alert->reviewed_at->format('d/m/Y H:i') : null,
                'device' => $alert->device_id,
                'event' => $translateEvent($alert->event_type),
                'confidence' => $confPct,
                'status' => $alert->status,
                'stateLabel' => $cfg['state'],
                'classificationLabel' => $cfg['classification'],
                'requiresAttention' => $requiresAttention,
                'reviewUrl' => route('alerts.review', $alert),
                'destroyUrl' => route('alerts.destroy', $alert),
            ];
        })->values();
    @endphp

    <div
        x-data="{
            statusFilter: 'all',
            deviceFilter: 'all',
            currentTime: '{{ now()->format('d/m/Y H:i:s') }}',
            overrideUntilIso: @js($overrideUntilIso),
            remainingTime: '',
            alertsList: @js($lightboxItems),
            lightboxOpen: false,
            activeIndex: 0,
            get activeAlert() {
                return this.alertsList[this.activeIndex] || null;
            },
            init() {
                const pad = (n) => String(n).padStart(2, '0');
                const updateClock = () => {
                    const now = new Date();
                    this.currentTime = `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;

                    if (this.overrideUntilIso) {
                        const target = new Date(this.overrideUntilIso);
                        const diffSec = Math.max(0, Math.floor((target - now) / 1000));
                        if (diffSec > 0) {
                            const hrs = Math.floor(diffSec / 3600);
                            const mins = Math.floor((diffSec % 3600) / 60);
                            const secs = diffSec % 60;
                            this.remainingTime = hrs > 0
                                ? `${hrs} h ${pad(mins)} m ${pad(secs)} s restantes`
                                : `${mins} m ${pad(secs)} s restantes`;
                        } else {
                            this.remainingTime = 'Tiempo finalizado';
                        }
                    }
                };
                updateClock();
                setInterval(updateClock, 1000);
            },
            matchesFilter(device, status) {
                if (this.statusFilter !== 'all' && status !== this.statusFilter) {
                    return false;
                }
                if (this.deviceFilter !== 'all' && device !== this.deviceFilter) {
                    return false;
                }
                return true;
            },
            openLightboxById(id) {
                const idx = this.alertsList.findIndex(item => item.id === id);
                if (idx !== -1) {
                    this.activeIndex = idx;
                    this.lightboxOpen = true;
                }
            },
            prevAlert() {
                if (this.alertsList.length > 0) {
                    this.activeIndex = (this.activeIndex - 1 + this.alertsList.length) % this.alertsList.length;
                }
            },
            nextAlert() {
                if (this.alertsList.length > 0) {
                    this.activeIndex = (this.activeIndex + 1) % this.alertsList.length;
                }
            },
            closeLightbox() {
                this.lightboxOpen = false;
            },
            resetFilters() {
                this.statusFilter = 'all';
                this.deviceFilter = 'all';
            }
        }"
        @keydown.escape.window="closeLightbox()"
        @keydown.arrow-left.window="if (lightboxOpen) prevAlert()"
        @keydown.arrow-right.window="if (lightboxOpen) nextAlert()"
        class="flex-1 flex flex-col bg-pt-bg"
    >
        <!-- =========================================================================
             PRIMER NIVEL: ESTADO DE LA ZONA Y CONTROLES PRINCIPALES
             ========================================================================= -->
        <section class="bg-pt-carbon text-white border-b border-pt-graphite">

            <!-- Barra Superior Limpia -->
            <div class="px-5 sm:px-8 h-12 bg-[#101723] border-b border-white/10 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <h1 class="text-sm font-semibold text-white truncate">
                        Panel de monitoreo — Zona «{{ $zone->zone }}»
                    </h1>
                    <span class="hidden md:inline-block text-white/20">•</span>
                    <p class="hidden md:block text-xs text-slate-400 truncate">
                        Detección de merodeo y gestión de evidencias
                    </p>
                </div>

                <div class="flex items-center gap-4">
                    @php
                        $nodeLastSeenTs = once(fn () => \Illuminate\Support\Facades\Cache::get('prowl:node-last-seen'));
                        $nodeOnline = $nodeLastSeenTs && (now()->timestamp - (int) $nodeLastSeenTs) <= (int) config('prowltracker.node_api.online_ttl', 120);
                        $nodeLastSeenHm = $nodeLastSeenTs ? \Carbon\Carbon::createFromTimestamp((int) $nodeLastSeenTs)->format('H:i') : null;
                    @endphp
                    <div
                        x-data="{
                            online: @js((bool) $nodeOnline),
                            lastSeenHm: @js($nodeLastSeenHm),
                            currentTopAlertId: @js((int) ($alerts->first()?->id ?? 0)),
                            init() {
                                const check = () => {
                                    fetch('{{ route('node.status') }}', { headers: { 'Accept': 'application/json' } })
                                        .then(r => r.ok ? r.json() : null)
                                        .then(d => {
                                            if (d) {
                                                this.online = !!d.online;
                                                this.lastSeenHm = d.last_seen_hm;
                                                if (d.latest_alert_id && this.currentTopAlertId > 0 && d.latest_alert_id > this.currentTopAlertId) {
                                                    window.location.reload();
                                                }
                                            }
                                        })
                                        .catch(() => {});
                                };
                                setInterval(check, 10000);
                            }
                        }"
                        class="hidden sm:flex items-center gap-2 text-xs text-slate-300"
                    >
                        <span class="w-2 h-2 rounded-full" :class="online ? 'bg-pt-green' : 'bg-slate-500'"></span>
                        <span x-text="online ? 'Nodo sincronizado' : 'Nodo desconectado'">{{ $nodeOnline ? 'Nodo sincronizado' : 'Nodo desconectado' }}</span>
                        <span
                            x-show="!online && lastSeenHm"
                            class="text-slate-500 tabular-nums"
                            x-text="lastSeenHm ? '(últ. ' + lastSeenHm + ')' : ''"
                            style="display: none;"
                        ></span>
                    </div>

                    <span class="hidden sm:inline-block h-3.5 w-px bg-white/15"></span>

                    <div class="text-xs text-slate-300 tabular-nums" x-text="currentTime">
                        {{ now()->format('d/m/Y H:i:s') }}
                    </div>
                </div>
            </div>

            <!-- Confirmación de acción operativa -->
            @if (session('status'))
                <div class="px-5 sm:px-8 py-2.5 bg-pt-graphite border-b border-white/10 flex items-center justify-between text-xs">
                    <div class="flex items-center gap-2.5">
                        <span class="w-2 h-2 rounded-full bg-pt-green shrink-0"></span>
                        <span class="text-slate-200">{{ session('status') }}</span>
                    </div>
                </div>
            @endif

            <!-- Bloque Principal Nivel 1: Estado de la Zona (Sin etiquetas neón) + Controles Rápidos -->
            <div class="px-5 sm:px-8 py-6 border-l-4 {{ $effectiveMode === 'ARMED' ? 'border-l-pt-red' : 'border-l-pt-green' }}">
                <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-6">

                    <!-- Estado General de la Zona (Tipografía limpia y sobria, sin recuadros brillantes) -->
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-300">
                            <span class="inline-flex items-center gap-2 font-medium text-slate-200">
                                <span class="w-2 h-2 rounded-full {{ $effectiveMode === 'ARMED' ? 'bg-pt-red' : 'bg-pt-green' }}"></span>
                                {{ $statusSummary }}
                            </span>

                            @if ($pendingAlertsCount > 0)
                                <span class="text-white/25">•</span>
                                <span class="text-slate-300">
                                    <strong class="text-white font-semibold tabular-nums">{{ $pendingAlertsCount }}</strong>
                                    {{ $pendingAlertsCount === 1 ? 'incidente pendiente de revisión' : 'incidentes pendientes de revisión' }}
                                </span>
                            @endif
                        </div>

                        <h2 class="text-xl sm:text-2xl font-semibold text-white tracking-tight">
                            {{ $occupancyHeadline }}
                        </h2>

                        <!-- Metadatos del estado en texto plano legible (sin cajas de colores) -->
                        <div class="flex flex-wrap items-center gap-y-1 gap-x-4 text-xs text-slate-400 pt-0.5">
                            <div>
                                <span>Origen:</span>
                                <span class="text-slate-200 ml-1">{{ $originLabel }}</span>
                            </div>

                            @if ($override !== null && $zone->override_until)
                                <span class="text-white/20">•</span>
                                <div class="flex items-center gap-1.5">
                                    <span>Temporizador:</span>
                                    <span class="text-slate-200 font-medium tabular-nums">hasta las {{ $zone->override_until->format('H:i') }}</span>
                                    <span
                                        x-show="remainingTime !== ''"
                                        class="text-slate-400 tabular-nums"
                                        x-text="'(' + remainingTime + ')'"
                                    ></span>
                                </div>
                            @elseif ($override !== null)
                                <span class="text-white/20">•</span>
                                <div>
                                    <span>Duración:</span>
                                    <span class="text-slate-200 ml-1">Indefinido hasta cambio manual</span>
                                </div>
                            @else
                                <span class="text-white/20">•</span>
                                <div>
                                    <span>Horario laboral configurado:</span>
                                    <span class="text-slate-200 ml-1 tabular-nums">{{ $workStart }} – {{ $workEnd }}</span>
                                </div>
                            @endif

                            @if ($nonWorkingToday)
                                <span class="text-white/20">•</span>
                                <span class="text-slate-300">
                                    Día no laborable{{ $nonWorkingToday->reason ? ' (' . $nonWorkingToday->reason . ')' : '' }}
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Controles Rápidos de Zona -->
                    <div class="bg-pt-graphite/70 border border-white/10 rounded-sm p-3 shrink-0">
                        <div class="flex items-center justify-between mb-2 px-0.5 text-xs">
                            <span class="text-slate-400">Control de zona</span>
                            <span class="text-slate-300">
                                Modo actual:
                                <strong class="text-white font-medium">
                                    {{ $override === 'armed' ? 'Armado manual' : ($override === 'occupied' ? 'Personal en zona' : 'Automático') }}
                                </strong>
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <!-- Armar ahora -->
                            <form method="POST" action="{{ route('zone.mode') }}">
                                @csrf
                                <input type="hidden" name="mode" value="armed">
                                <button
                                    type="submit"
                                    @disabled($override === 'armed')
                                    class="w-full px-3.5 py-2 rounded-sm text-xs font-medium transition-colors flex items-center justify-center {{ $override === 'armed' ? 'bg-pt-red text-white cursor-default opacity-90' : 'bg-pt-red hover:bg-[#9e4242] text-white' }}"
                                >
                                    <span>Armar ahora</span>
                                </button>
                            </form>

                            <!-- Personal presente (2 h) -->
                            <form method="POST" action="{{ route('zone.mode') }}">
                                @csrf
                                <input type="hidden" name="mode" value="occupied">
                                <input type="hidden" name="minutes" value="120">
                                <button
                                    type="submit"
                                    class="w-full px-3.5 py-2 rounded-sm text-xs font-medium transition-colors flex items-center justify-center {{ $override === 'occupied' ? 'bg-slate-200 text-pt-carbon' : 'bg-pt-carbon hover:bg-slate-800 text-slate-200 border border-white/15' }}"
                                >
                                    <span>Personal presente (2 h)</span>
                                </button>
                            </form>

                            <!-- Volver a automático -->
                            <form method="POST" action="{{ route('zone.mode') }}">
                                @csrf
                                <input type="hidden" name="mode" value="auto">
                                <button
                                    type="submit"
                                    @disabled($override === null)
                                    class="w-full px-3.5 py-2 rounded-sm text-xs font-medium transition-colors flex items-center justify-center {{ $override === null ? 'bg-white/5 text-slate-400 border border-white/10 cursor-default' : 'bg-pt-carbon hover:bg-slate-800 text-slate-200 border border-white/15' }}"
                                >
                                    <span>Modo automático</span>
                                </button>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- =========================================================================
             SEGUNDO NIVEL: ESTADÍSTICAS Y PARÁMETROS DE MONITOREO
             ========================================================================= -->
        <section class="bg-[#E6EBF1] border-b border-slate-300/90">
            <div class="grid grid-cols-2 lg:grid-cols-4 divide-y sm:divide-y-0 sm:divide-x divide-slate-300/90">

                <!-- Métrica 1: Alertas Pendientes -->
                <div class="px-5 sm:px-8 py-3.5 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-pt-muted block">
                            Pendientes de revisión
                        </span>
                        <div class="mt-0.5 flex items-baseline gap-2">
                            <span class="text-2xl font-semibold tabular-nums {{ $pendingAlertsCount > 0 ? 'text-pt-red' : 'text-pt-carbon' }}">
                                {{ $pendingAlertsCount }}
                            </span>
                            <span class="text-xs text-pt-muted">
                                sin clasificar
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Métrica 2: Incidentes 24h / Acumulado -->
                <div class="px-5 sm:px-8 py-3.5 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-pt-muted block">
                            Alertas registradas (24 h)
                        </span>
                        <div class="mt-0.5 flex items-baseline gap-2.5">
                            <span class="text-2xl font-semibold text-pt-carbon tabular-nums">
                                {{ $alerts24hCount }}
                            </span>
                            <span class="text-xs text-pt-muted tabular-nums">
                                de {{ $totalAlertsCount }} acumuladas
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Métrica 3: Actividad de Personas (Hoy) -->
                <div class="px-5 sm:px-8 py-3.5 flex items-center justify-between">
                    <div>
                        <span class="text-xs text-pt-muted block">
                            Detección de personas (Hoy)
                        </span>
                        <div class="mt-0.5 flex items-baseline gap-2.5">
                            <div>
                                <span class="text-2xl font-semibold text-pt-carbon tabular-nums">{{ $todayMaxPersons }}</span>
                                <span class="text-xs text-pt-muted ml-1">máximo hoy</span>
                            </div>
                            <span class="text-slate-400">•</span>
                            <span class="text-xs text-pt-carbon tabular-nums">
                                {{ $todaySamples }} {{ $todaySamples === 1 ? 'registro' : 'registros' }} ({{ $todayActiveHours }} h)
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Métrica 4: Umbrales de Merodeo -->
                <div class="px-5 sm:px-8 py-3.5 flex items-center justify-between gap-3">
                    <div>
                        <span class="text-xs text-pt-muted block">
                            Tiempos de detección
                        </span>
                        <div class="mt-1 flex flex-wrap items-center gap-x-2.5 gap-y-0.5 text-xs text-pt-carbon tabular-nums">
                            <span>Armado: <strong>{{ $sentrySeconds }}s</strong></span>
                            <span class="text-slate-400">•</span>
                            <span>Laboral: <strong>{{ $workSeconds }}s</strong></span>
                            <span class="text-slate-400">•</span>
                            <span>Tránsito: <strong>{{ $transitSeconds }}s</strong></span>
                        </div>
                    </div>
                    <a
                        href="{{ route('settings.edit') }}"
                        class="px-2.5 py-1 bg-white/80 hover:bg-white border border-slate-300 rounded-sm text-xs font-medium text-pt-carbon shrink-0 transition-colors"
                    >
                        Ajustar
                    </a>
                </div>

            </div>

            <!-- Franja de Sugerencia de Horario Adaptativo -->
            @if (! empty($analysis['differs']) && $analysis['start'] !== null)
                <div class="px-5 sm:px-8 py-2.5 bg-pt-graphite text-slate-200 border-t border-white/10 border-l-4 border-l-pt-amber flex flex-wrap items-center justify-between gap-4 text-xs">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-white">
                            Sugerencia de horario laboral:
                        </span>
                        <span class="text-slate-300">
                            En los últimos {{ $analysis['days_observed'] }} días hábiles observados, la presencia habitual fue de
                            <strong class="text-white tabular-nums">{{ sprintf('%02d:00 – %02d:00', $analysis['start'], $analysis['end']) }}</strong>
                            (consistencia del {{ (int) round(($analysis['confidence'] ?? 0) * 100) }}%).
                        </span>
                    </div>
                    <form method="POST" action="{{ route('settings.apply-suggestion') }}">
                        @csrf
                        <button
                            type="submit"
                            class="px-3 py-1 rounded-sm bg-white/10 hover:bg-white/20 border border-white/20 text-white text-xs font-medium transition-colors"
                        >
                            Aplicar horario {{ sprintf('%02d:00 – %02d:00', $analysis['start'], $analysis['end']) }}
                        </button>
                    </form>
                </div>
            @endif
        </section>

        <!-- =========================================================================
             TERCER NIVEL: HISTORIAL DE INCIDENTES Y EVIDENCIAS FOTOGRÁFICAS
             ========================================================================= -->
        <section class="flex-1 flex flex-col">

            <!-- Barra de Control del Historial: Pestañas de Estado + Filtro por Dispositivo -->
            <div class="bg-pt-surface border-b border-slate-300/90 px-5 sm:px-8 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-1 overflow-x-auto -mb-px">
                    <button
                        type="button"
                        @click="statusFilter = 'all'"
                        :class="statusFilter === 'all' ? 'border-pt-carbon text-pt-carbon font-semibold bg-slate-50/70' : 'border-transparent text-pt-muted hover:text-pt-carbon'"
                        class="px-3.5 py-3 border-b-2 text-xs transition-colors whitespace-nowrap"
                    >
                        Todas ({{ $alerts->count() }})
                    </button>

                    <button
                        type="button"
                        @click="statusFilter = 'nueva'"
                        :class="statusFilter === 'nueva' ? 'border-pt-red text-pt-red font-semibold bg-pt-red/[0.03]' : 'border-transparent text-pt-muted hover:text-pt-carbon'"
                        class="px-3.5 py-3 border-b-2 text-xs transition-colors flex items-center gap-2 whitespace-nowrap"
                    >
                        <span class="w-1.5 h-1.5 rounded-full {{ $pendingAlertsCount > 0 ? 'bg-pt-red' : 'bg-slate-400' }}"></span>
                        <span>Pendientes ({{ $alerts->where('status', 'nueva')->count() }})</span>
                    </button>

                    <button
                        type="button"
                        @click="statusFilter = 'intrusion'"
                        :class="statusFilter === 'intrusion' ? 'border-pt-red text-pt-red font-semibold bg-pt-red/[0.03]' : 'border-transparent text-pt-muted hover:text-pt-carbon'"
                        class="px-3.5 py-3 border-b-2 text-xs transition-colors whitespace-nowrap"
                    >
                        Intrusiones ({{ $alerts->where('status', 'intrusion')->count() }})
                    </button>

                    <button
                        type="button"
                        @click="statusFilter = 'personal'"
                        :class="statusFilter === 'personal' ? 'border-pt-carbon text-pt-carbon font-semibold bg-slate-50/70' : 'border-transparent text-pt-muted hover:text-pt-carbon'"
                        class="px-3.5 py-3 border-b-2 text-xs transition-colors whitespace-nowrap"
                    >
                        Personal ({{ $alerts->where('status', 'personal')->count() }})
                    </button>

                    <button
                        type="button"
                        @click="statusFilter = 'falsa_alarma'"
                        :class="statusFilter === 'falsa_alarma' ? 'border-pt-carbon text-pt-carbon font-semibold bg-slate-50/70' : 'border-transparent text-pt-muted hover:text-pt-carbon'"
                        class="px-3.5 py-3 border-b-2 text-xs transition-colors whitespace-nowrap"
                    >
                        Falsa alarma ({{ $alerts->where('status', 'falsa_alarma')->count() }})
                    </button>
                </div>

                <!-- Selector de Dispositivo -->
                <div class="flex items-center gap-3 py-2">
                    <div class="flex items-center gap-2">
                        <label for="deviceFilter" class="text-xs text-pt-muted">
                            Dispositivo:
                        </label>
                        <select
                            id="deviceFilter"
                            x-model="deviceFilter"
                            class="text-xs bg-pt-bg border border-slate-300 rounded-sm py-1 pl-2.5 pr-7 text-pt-carbon focus:outline-none focus:bg-white focus:border-pt-graphite focus:ring-0"
                        >
                            <option value="all">Todos</option>
                            @foreach ($devices as $deviceId)
                                <option value="{{ $deviceId }}">{{ $deviceId }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button
                        type="button"
                        x-show="statusFilter !== 'all' || deviceFilter !== 'all'"
                        @click="resetFilters()"
                        class="px-2.5 py-1 rounded-sm text-xs text-pt-muted hover:text-pt-carbon bg-pt-bg hover:bg-slate-200/70 border border-slate-300 transition-colors"
                        style="display: none;"
                    >
                        Restablecer
                    </button>
                </div>
            </div>

            <!-- Cabecera de Columnas (Desktop) -->
            <div class="hidden lg:grid lg:grid-cols-12 items-center px-5 sm:px-8 py-2 bg-[#E9EEF4] border-b border-slate-300/80 text-xs font-medium text-pt-muted">
                <div class="col-span-2">Evidencia</div>
                <div class="col-span-3">Evento y fecha</div>
                <div class="col-span-2">Dispositivo</div>
                <div class="col-span-2">Confianza IA</div>
                <div class="col-span-3 text-right">Revisión y acciones</div>
            </div>

            <!-- Flujo de Registros de Incidentes -->
            <div class="divide-y divide-slate-200/90 bg-pt-surface flex-1">
                @forelse ($alerts as $alert)
                    @php
                        $cfg = $statusConfig[$alert->status] ?? $statusConfig['nueva'];
                        $confPct = $alert->confidence <= 1
                            ? (int) round($alert->confidence * 100)
                            : (int) round($alert->confidence);
                        $confPct = max(0, min(100, $confPct));

                        $requiresAttention = in_array($alert->status, ['nueva', 'intrusion'], true);
                        $eventLabel = $translateEvent($alert->event_type);
                        $formattedDate = $alert->created_at->format('d/m/Y H:i:s');
                        $reviewedDate = $alert->reviewed_at ? $alert->reviewed_at->format('d/m/Y H:i') : null;
                        $imageUrl = route('alerts.evidence', $alert);
                        $reviewUrl = route('alerts.review', $alert);
                    @endphp

                    <article
                        x-show="matchesFilter(@js($alert->device_id), @js($alert->status))"
                        class="group relative px-5 sm:px-8 py-3.5 transition-colors border-l-4 {{ $alert->status === 'nueva' ? 'border-l-pt-red bg-pt-red/[0.015] hover:bg-pt-red/[0.03]' : ($alert->status === 'intrusion' ? 'border-l-pt-red bg-white hover:bg-slate-50/90' : ($alert->status === 'personal' ? 'border-l-pt-green bg-white hover:bg-slate-50/90' : 'border-l-slate-300 bg-slate-50/40 hover:bg-slate-100/60')) }}"
                    >
                        <div class="grid grid-cols-1 lg:grid-cols-12 items-center gap-4">

                            <!-- 1. Fotografía de Evidencia -->
                            <div class="lg:col-span-2 flex items-center">
                                <button
                                    type="button"
                                    @click="openLightboxById({{ $alert->id }})"
                                    class="relative w-36 h-20 sm:w-40 sm:h-22 rounded-sm bg-pt-carbon border border-slate-300 overflow-hidden focus:outline-none focus:ring-1 focus:ring-pt-carbon shrink-0"
                                    title="Ampliar evidencia #{{ $alert->id }}"
                                >
                                    <img
                                        src="{{ $imageUrl }}"
                                        alt="Evidencia #{{ $alert->id }}"
                                        loading="lazy"
                                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                                    />
                                    <span class="absolute top-0 left-0 px-1.5 py-0.5 bg-pt-carbon/80 text-[11px] text-slate-200 tabular-nums">
                                        #{{ $alert->id }}
                                    </span>
                                    <span class="absolute inset-x-0 bottom-0 py-0.5 bg-pt-carbon/80 text-[11px] text-white opacity-0 group-hover:opacity-100 transition-opacity text-center">
                                        Ver evidencia
                                    </span>
                                </button>
                            </div>

                            <!-- 2. Tipo de Evento y Marca Temporal (24h) -->
                            <div class="lg:col-span-3 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $requiresAttention ? 'bg-pt-red' : 'bg-slate-400' }}"></span>
                                    <h3 class="text-sm font-semibold truncate {{ $requiresAttention ? 'text-pt-red' : 'text-pt-carbon' }}">
                                        {{ $eventLabel }}
                                    </h3>
                                </div>
                                <div class="mt-1 flex items-center gap-2 text-xs text-pt-carbon tabular-nums">
                                    <span>{{ $formattedDate }}</span>
                                    <span class="text-slate-300">•</span>
                                    <span class="text-pt-muted">{{ $alert->created_at->locale('es')->diffForHumans() }}</span>
                                </div>
                            </div>

                            <!-- 3. Dispositivo de Origen -->
                            <div class="lg:col-span-2">
                                <span class="block text-xs text-pt-muted lg:hidden">
                                    Dispositivo
                                </span>
                                <span class="text-xs font-medium text-pt-carbon">
                                    {{ $alert->device_id }}
                                </span>
                                <span class="block text-[11px] text-pt-muted mt-0.5">
                                    Zona {{ $zone->zone }}
                                </span>
                            </div>

                            <!-- 4. Confianza IA -->
                            <div class="lg:col-span-2">
                                <div class="flex items-baseline justify-between max-w-[140px] mb-1">
                                    <span class="text-xs text-pt-muted">Confianza</span>
                                    <span class="text-xs font-semibold tabular-nums {{ $requiresAttention ? 'text-pt-red' : 'text-pt-carbon' }}">
                                        {{ $confPct }}%
                                    </span>
                                </div>
                                <div class="w-full max-w-[140px] bg-slate-200 h-1.5 rounded-sm overflow-hidden">
                                    <div
                                        class="{{ $requiresAttention ? 'bg-pt-red' : 'bg-pt-graphite' }} h-1.5"
                                        style="width: {{ $confPct }}%"
                                    ></div>
                                </div>
                            </div>

                            <!-- 5. Revisión y Acciones -->
                            <div class="lg:col-span-3 flex flex-col lg:items-end justify-center">
                                @if ($alert->status === 'nueva')
                                    <span class="text-[11px] text-pt-muted mb-1.5">
                                        Pendiente de clasificación
                                    </span>

                                    <div class="inline-flex flex-wrap items-center gap-1.5">
                                        <!-- Intrusión: borde rojo sutil por defecto, rojo sólido al pasar el cursor -->
                                        <form method="POST" action="{{ $reviewUrl }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="intrusion">
                                            <button
                                                type="submit"
                                                title="Confirmar intrusión"
                                                class="px-2.5 py-1 rounded-sm text-xs font-medium bg-white text-pt-red border border-pt-red/55 hover:bg-pt-red hover:text-white hover:border-pt-red transition-colors"
                                            >
                                                Intrusión
                                            </button>
                                        </form>

                                        <!-- Es personal -->
                                        <form method="POST" action="{{ $reviewUrl }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="personal">
                                            <button
                                                type="submit"
                                                title="Confirmar personal (pausa alertas por 60 min)"
                                                class="px-2.5 py-1 rounded-sm text-xs font-medium bg-white hover:bg-slate-100 text-pt-carbon border border-slate-300 transition-colors"
                                            >
                                                Es personal
                                            </button>
                                        </form>

                                        <!-- Falsa alarma -->
                                        <form method="POST" action="{{ $reviewUrl }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="status" value="falsa_alarma">
                                            <button
                                                type="submit"
                                                title="Descartar como falsa alarma"
                                                class="px-2.5 py-1 rounded-sm text-xs font-medium bg-pt-bg hover:bg-slate-200 text-pt-muted hover:text-pt-carbon border border-slate-300 transition-colors"
                                            >
                                                Falsa alarma
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <div class="flex items-center lg:justify-end gap-3">
                                        <div class="text-left lg:text-right">
                                            <div class="inline-flex items-center gap-1.5">
                                                <span class="w-1.5 h-1.5 rounded-full {{ $cfg['dot'] }}"></span>
                                                <span class="text-xs {{ $cfg['text'] }}">
                                                    {{ $cfg['classification'] }}
                                                </span>
                                            </div>
                                            @if ($reviewedDate)
                                                <span class="block text-[11px] text-pt-muted tabular-nums mt-0.5">
                                                    Revisada {{ $reviewedDate }}
                                                </span>
                                            @endif
                                        </div>

                                        <button
                                            type="button"
                                            @click="openLightboxById({{ $alert->id }})"
                                            class="px-2.5 py-1 rounded-sm bg-pt-bg hover:bg-slate-200 border border-slate-300 text-xs text-pt-carbon transition-colors"
                                        >
                                            Ver
                                        </button>
                                    </div>
                                @endif
                            </div>

                        </div>
                    </article>
                @empty
                    <div class="px-8 py-14 text-center">
                        <p class="text-xs text-pt-muted">
                            No hay registros de alertas en la base de datos.
                        </p>
                    </div>
                @endforelse
            </div>

            <!-- Controles de Paginación -->
            @if ($alerts instanceof \Illuminate\Contracts\Pagination\Paginator && $alerts->hasPages())
                <div class="px-5 sm:px-8 py-3.5 bg-[#E6EBF1] border-t border-slate-300">
                    {{ $alerts->links() }}
                </div>
            @endif

        </section>

        <!-- =========================================================================
             VISOR DE EVIDENCIAS FOTOGRÁFICAS
             ========================================================================= -->
        <div
            x-show="lightboxOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-6 bg-pt-carbon/85"
            style="display: none;"
        >
            <div
                @click.away="closeLightbox()"
                class="bg-pt-carbon border border-pt-graphite rounded-sm max-w-5xl w-full overflow-hidden shadow-2xl flex flex-col lg:flex-row text-slate-200"
            >
                <!-- Columna Principal: Imagen + Controles de Secuencia -->
                <div class="lg:w-8/12 bg-[#0e1420] flex flex-col justify-between min-h-[320px] max-h-[85vh]">
                    <div class="px-4 py-2.5 bg-pt-carbon border-b border-white/10 flex flex-wrap items-center justify-between gap-2 text-xs">
                        <template x-if="activeAlert">
                            <div class="flex items-center gap-3">
                                <span class="font-semibold text-white" x-text="'Evidencia #' + activeAlert.id"></span>
                                <span class="text-white/20">•</span>
                                <span class="text-slate-300" x-text="'Dispositivo: ' + activeAlert.device"></span>
                                <span class="text-white/20">•</span>
                                <span class="text-slate-300 tabular-nums" x-text="activeAlert.date"></span>
                            </div>
                        </template>

                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                @click="prevAlert()"
                                class="px-2.5 py-1 rounded-sm bg-pt-graphite hover:bg-slate-700 text-xs text-slate-200 border border-white/10"
                            >
                                ← Anterior
                            </button>
                            <button
                                type="button"
                                @click="nextAlert()"
                                class="px-2.5 py-1 rounded-sm bg-pt-graphite hover:bg-slate-700 text-xs text-slate-200 border border-white/10"
                            >
                                Siguiente →
                            </button>
                            <template x-if="activeAlert">
                                <a
                                    :href="activeAlert.imageUrl"
                                    target="_blank"
                                    class="px-2.5 py-1 rounded-sm bg-pt-graphite hover:bg-slate-700 text-xs text-white border border-white/10"
                                >
                                    Abrir original ↗
                                </a>
                            </template>
                        </div>
                    </div>

                    <div class="flex-1 flex items-center justify-center p-4 overflow-hidden">
                        <template x-if="activeAlert">
                            <img
                                :src="activeAlert.imageUrl"
                                :alt="'Evidencia #' + activeAlert.id"
                                class="max-h-[70vh] w-auto object-contain rounded-sm border border-white/10"
                            />
                        </template>
                    </div>
                </div>

                <!-- Panel Secundario: Metadatos y Clasificación -->
                <div class="lg:w-4/12 p-5 sm:p-6 flex flex-col justify-between bg-pt-surface text-pt-carbon border-t lg:border-t-0 lg:border-l border-slate-300">
                    <template x-if="activeAlert">
                        <div class="space-y-5">
                            <div class="flex items-start justify-between gap-2 pb-3 border-b border-slate-200">
                                <div>
                                    <span class="text-xs text-pt-muted block">
                                        Detalle de detección
                                    </span>
                                    <h3
                                        class="text-base font-semibold mt-0.5"
                                        :class="activeAlert.requiresAttention ? 'text-pt-red' : 'text-pt-carbon'"
                                        x-text="activeAlert.event"
                                    ></h3>
                                </div>
                                <button
                                    type="button"
                                    @click="closeLightbox()"
                                    class="p-1 text-pt-muted hover:text-pt-carbon hover:bg-slate-100 rounded-sm"
                                    aria-label="Cerrar"
                                >
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <dl class="divide-y divide-slate-200 text-xs">
                                <div class="py-2.5 flex items-center justify-between">
                                    <dt class="text-pt-muted">Fecha y hora</dt>
                                    <dd class="font-medium text-pt-carbon tabular-nums" x-text="activeAlert.date"></dd>
                                </div>

                                <div class="py-2.5 flex items-center justify-between">
                                    <dt class="text-pt-muted">Dispositivo</dt>
                                    <dd class="font-medium text-pt-carbon" x-text="activeAlert.device"></dd>
                                </div>

                                <div class="py-2.5 flex items-center justify-between">
                                    <dt class="text-pt-muted">Tipo de evento</dt>
                                    <dd class="font-medium text-pt-carbon" x-text="activeAlert.event"></dd>
                                </div>

                                <div class="py-2.5">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <dt class="text-pt-muted">Confianza IA</dt>
                                        <dd class="font-semibold text-pt-carbon tabular-nums" x-text="activeAlert.confidence + '%'"></dd>
                                    </div>
                                    <div class="w-full bg-slate-200 h-1.5 rounded-sm overflow-hidden">
                                        <div
                                            :class="activeAlert.requiresAttention ? 'bg-pt-red' : 'bg-pt-graphite'"
                                            class="h-1.5"
                                            :style="'width: ' + activeAlert.confidence + '%'"
                                        ></div>
                                    </div>
                                </div>

                                <div class="py-2.5 flex items-center justify-between">
                                    <dt class="text-pt-muted">Estado de revisión</dt>
                                    <dd
                                        class="font-medium"
                                        :class="activeAlert.status === 'nueva' ? 'text-pt-red' : 'text-pt-carbon'"
                                        x-text="activeAlert.stateLabel"
                                    ></dd>
                                </div>

                                <div class="py-2.5 flex items-center justify-between">
                                    <dt class="text-pt-muted">Clasificación</dt>
                                    <dd
                                        class="font-medium"
                                        :class="activeAlert.requiresAttention ? 'text-pt-red' : 'text-pt-carbon'"
                                        x-text="activeAlert.classificationLabel"
                                    ></dd>
                                </div>
                            </dl>

                            <!-- Acciones de Clasificación -->
                            <div class="pt-3 border-t border-slate-200 space-y-2">
                                <span class="text-xs font-medium text-pt-muted block">
                                    Clasificar alerta
                                </span>

                                <form method="POST" :action="activeAlert.reviewUrl">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="intrusion">
                                    <button
                                        type="submit"
                                        class="w-full px-3.5 py-2 rounded-sm text-xs font-medium bg-white text-pt-red border border-pt-red/55 hover:bg-pt-red hover:text-white hover:border-pt-red transition-colors flex items-center justify-between group"
                                    >
                                        <span>Intrusión</span>
                                        <span class="text-[11px] text-pt-red/80 group-hover:text-red-100">Confirmar alerta</span>
                                    </button>
                                </form>

                                <form method="POST" :action="activeAlert.reviewUrl">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="personal">
                                    <button
                                        type="submit"
                                        class="w-full px-3.5 py-2 rounded-sm text-xs font-medium bg-white hover:bg-slate-100 text-pt-carbon border border-slate-300 transition-colors flex items-center justify-between"
                                    >
                                        <span>Es personal</span>
                                        <span class="text-[11px] text-pt-muted">Pausa alertas 60 min</span>
                                    </button>
                                </form>

                                <form method="POST" :action="activeAlert.reviewUrl">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="falsa_alarma">
                                    <button
                                        type="submit"
                                        class="w-full px-3.5 py-2 rounded-sm text-xs font-medium bg-pt-bg hover:bg-slate-200 text-pt-carbon border border-slate-300 transition-colors flex items-center justify-between"
                                    >
                                        <span>Falsa alarma</span>
                                        <span class="text-[11px] text-pt-muted">Descartar evento</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </template>

                    <div class="pt-4 mt-4 border-t border-slate-200 flex items-center justify-between gap-2">
                        <template x-if="activeAlert">
                            <form
                                method="POST"
                                :action="activeAlert.destroyUrl"
                                onsubmit="return confirm('¿Eliminar permanentemente esta evidencia y su archivo físico?');"
                            >
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="px-3 py-1.5 rounded-sm bg-transparent border border-pt-red/40 text-pt-red hover:bg-pt-red hover:border-pt-red hover:text-white text-xs font-medium transition-colors"
                                >
                                    Eliminar evidencia
                                </button>
                            </form>
                        </template>

                        <button
                            type="button"
                            @click="closeLightbox()"
                            class="px-4 py-1.5 rounded-sm bg-pt-carbon hover:bg-pt-graphite text-white text-xs font-medium transition-colors"
                        >
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
