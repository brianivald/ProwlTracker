<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-sm sm:text-base font-semibold text-white">
                    Almacenamiento y depuración de evidencias
                </h1>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-300">
                <span>
                    Unidad activa:
                    <strong class="text-white font-medium">
                        {{ config('prowltracker.evidence_disk') === 's3' ? 'Almacenamiento S3 / R2' : 'Disco del servidor (' . config('prowltracker.evidence_disk') . ')' }}
                    </strong>
                </span>
            </div>
        </div>
    </x-slot>

    @php
        $fmtBytes = fn (int $b): string => \App\Http\Controllers\StorageManagementController::formatBytes($b);

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
            return $eventTranslations[$key] ?? ucfirst(strtolower(str_replace('_', ' ', $key ?: 'Evento detectado')));
        };

        $statusMeta = [
            'falsa_alarma' => ['label' => 'Falsa alarma', 'dot' => 'bg-slate-400', 'bar' => 'bg-slate-400', 'text' => 'text-pt-muted'],
            'personal' => ['label' => 'Personal autorizado', 'dot' => 'bg-pt-green', 'bar' => 'bg-pt-green', 'text' => 'text-pt-carbon font-medium'],
            'nueva' => ['label' => 'Pendiente de revisión', 'dot' => 'bg-pt-amber', 'bar' => 'bg-pt-amber', 'text' => 'text-pt-amber font-medium'],
            'intrusion' => ['label' => 'Intrusión confirmada', 'dot' => 'bg-pt-red', 'bar' => 'bg-pt-red', 'text' => 'text-pt-red font-medium'],
        ];

        $hasActiveFilter = $filters['status'] !== 'all'
            || $filters['older_than'] !== ''
            || $filters['date_from'] !== ''
            || $filters['date_to'] !== ''
            || $filters['device_id'] !== 'all';
    @endphp

    <div
        x-data="{
            previewOpen: false,
            previewUrl: '',
            previewTitle: '',
            protectIntrusions: {{ $filters['status'] === 'intrusion' ? 'false' : 'true' }},
            openPreview(url, title) {
                this.previewUrl = url;
                this.previewTitle = title;
                this.previewOpen = true;
            }
        }"
        @keydown.escape.window="previewOpen = false"
        class="flex-1 flex flex-col bg-pt-bg"
    >

        @if (session('status'))
            <div class="px-5 sm:px-8 py-2.5 bg-pt-graphite text-slate-200 border-b border-white/10 border-l-4 border-l-pt-green flex items-center gap-2.5 text-xs">
                <span class="w-2 h-2 rounded-full bg-pt-green shrink-0"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- =========================================================================
             1. FRANJA DE OCUPACIÓN Y DESGLOSE POR CLASIFICACIÓN
             ========================================================================= -->
        <section class="bg-pt-surface border-b border-slate-300 px-5 sm:px-8 py-4">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                <div class="flex flex-wrap items-baseline gap-x-6 gap-y-1">
                    <div>
                        <span class="text-xs text-pt-muted">Espacio total ocupado:</span>
                        <strong class="ml-1.5 text-base font-semibold text-pt-carbon tabular-nums">{{ $fmtBytes($totalBytes) }}</strong>
                    </div>
                    <span class="hidden sm:inline text-slate-300">•</span>
                    <div class="text-xs text-pt-muted">
                        Total de evidencias:
                        <strong class="text-pt-carbon font-semibold tabular-nums">{{ $totalCount }}</strong>
                        {{ $totalCount === 1 ? 'archivo' : 'archivos' }}
                    </div>
                </div>

                <!-- Desglose rápido seleccionable -->
                <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs">
                    @foreach ($breakdown as $key => $info)
                        @php
                            $meta = $statusMeta[$key] ?? $statusMeta['nueva'];
                            $isSelected = $filters['status'] === $key;
                        @endphp
                        <a
                            href="{{ route('storage.index', array_merge($filters, ['status' => $isSelected ? 'all' : $key])) }}"
                            class="inline-flex items-center gap-1.5 py-0.5 border-b-2 transition-colors {{ $isSelected ? 'border-pt-carbon text-pt-carbon font-semibold' : 'border-transparent text-pt-muted hover:text-pt-carbon' }}"
                            title="Filtrar por {{ $info['label'] }}"
                        >
                            <span class="w-2 h-2 rounded-full {{ $meta['dot'] }}"></span>
                            <span>{{ $info['label'] }}:</span>
                            <span class="text-pt-carbon font-medium tabular-nums">{{ $info['count'] }}</span>
                            <span class="text-pt-muted tabular-nums">({{ $fmtBytes($info['bytes']) }})</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- Barra de proporción de uso -->
            @if ($totalBytes > 0)
                <div class="mt-3 h-1.5 w-full bg-slate-200 rounded-none overflow-hidden flex">
                    @foreach ($breakdown as $key => $info)
                        @php
                            $pct = max(0, min(100, ($info['bytes'] / max(1, $totalBytes)) * 100));
                            $meta = $statusMeta[$key] ?? $statusMeta['nueva'];
                        @endphp
                        @if ($pct > 0)
                            <div
                                class="h-full {{ $meta['bar'] }}"
                                style="width: {{ number_format($pct, 2, '.', '') }}%"
                                title="{{ $info['label'] }}: {{ $fmtBytes($info['bytes']) }}"
                            ></div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>

        <!-- =========================================================================
             2. FILTROS Y DEPURACIÓN POR LOTES
             ========================================================================= -->
        <section class="bg-[#E6EBF1] border-b border-slate-300 px-5 sm:px-8 py-5">
            <form method="GET" action="{{ route('storage.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 items-end">
                <div class="lg:col-span-3">
                    <x-input-label for="status" value="Clasificación" />
                    <select
                        id="status"
                        name="status"
                        class="mt-1 block w-full text-xs rounded-sm border-slate-300 bg-white text-pt-carbon focus:border-pt-graphite focus:ring-pt-graphite py-1.5"
                    >
                        <option value="all" @selected($filters['status'] === 'all')>Todas las clasificaciones</option>
                        <option value="non_intrusion" @selected($filters['status'] === 'non_intrusion')>Descartables (Falsas alarmas + Personal)</option>
                        <option value="falsa_alarma" @selected($filters['status'] === 'falsa_alarma')>Solo Falsas alarmas</option>
                        <option value="personal" @selected($filters['status'] === 'personal')>Solo Personal autorizado</option>
                        <option value="nueva" @selected($filters['status'] === 'nueva')>Pendientes de revisión</option>
                        <option value="intrusion" @selected($filters['status'] === 'intrusion')>Intrusiones confirmadas</option>
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <x-input-label for="older_than" value="Antigüedad" />
                    <select
                        id="older_than"
                        name="older_than"
                        class="mt-1 block w-full text-xs rounded-sm border-slate-300 bg-white text-pt-carbon focus:border-pt-graphite focus:ring-pt-graphite py-1.5"
                    >
                        <option value="" @selected($filters['older_than'] === '')>Cualquier fecha</option>
                        <option value="7" @selected($filters['older_than'] === '7')>Más de 7 días</option>
                        <option value="15" @selected($filters['older_than'] === '15')>Más de 15 días</option>
                        <option value="30" @selected($filters['older_than'] === '30')>Más de 30 días</option>
                        <option value="60" @selected($filters['older_than'] === '60')>Más de 60 días</option>
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <x-input-label for="device_id" value="Nodo / Cámara" />
                    <select
                        id="device_id"
                        name="device_id"
                        class="mt-1 block w-full text-xs rounded-sm border-slate-300 bg-white text-pt-carbon focus:border-pt-graphite focus:ring-pt-graphite py-1.5"
                    >
                        <option value="all" @selected($filters['device_id'] === 'all')>Todos los nodos</option>
                        @foreach ($devices as $dev)
                            <option value="{{ $dev }}" @selected($filters['device_id'] === $dev)>{{ $dev }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="lg:col-span-2">
                    <x-input-label for="date_from" value="Desde fecha" />
                    <x-text-input
                        id="date_from"
                        name="date_from"
                        type="date"
                        class="mt-1 block w-full text-xs rounded-sm tabular-nums py-1.5"
                        :value="$filters['date_from']"
                    />
                </div>

                <div class="lg:col-span-2">
                    <x-input-label for="date_to" value="Hasta fecha" />
                    <x-text-input
                        id="date_to"
                        name="date_to"
                        type="date"
                        class="mt-1 block w-full text-xs rounded-sm tabular-nums py-1.5"
                        :value="$filters['date_to']"
                    />
                </div>

                <div class="lg:col-span-1 flex items-center gap-1.5">
                    <button
                        type="submit"
                        class="w-full px-3 py-1.5 rounded-sm bg-pt-carbon hover:bg-pt-graphite text-white text-xs font-medium transition-colors"
                    >
                        Filtrar
                    </button>
                </div>
            </form>

            <!-- Barra de acción de depuración sobre el filtro actual -->
            <div class="mt-4 pt-3.5 border-t border-slate-300/90 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 text-xs">
                <div class="flex flex-wrap items-center gap-3 text-pt-carbon">
                    <span>
                        Registros que coinciden con el filtro:
                        <strong class="font-semibold tabular-nums">{{ $filteredCount }}</strong>
                        <span class="text-pt-muted tabular-nums">({{ $fmtBytes($filteredBytes) }})</span>
                    </span>

                    @if ($hasActiveFilter)
                        <span class="text-slate-300">•</span>
                        <a href="{{ route('storage.index') }}" class="text-pt-muted hover:text-pt-carbon underline">
                            Limpiar filtros
                        </a>
                    @endif
                </div>

                @if ($filteredCount > 0)
                    <form
                        method="POST"
                        action="{{ route('storage.bulk-destroy') }}"
                        class="flex flex-wrap items-center gap-4"
                        onsubmit="return confirm('¿Confirmas la eliminación permanente de los registros seleccionados y sus archivos físicos de evidencia? Esta acción no se puede deshacer.');"
                    >
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="status" value="{{ $filters['status'] }}">
                        <input type="hidden" name="older_than" value="{{ $filters['older_than'] }}">
                        <input type="hidden" name="date_from" value="{{ $filters['date_from'] }}">
                        <input type="hidden" name="date_to" value="{{ $filters['date_to'] }}">
                        <input type="hidden" name="device_id" value="{{ $filters['device_id'] }}">

                        @if ($filters['status'] !== 'intrusion')
                            <label class="inline-flex items-center gap-2 cursor-pointer text-pt-carbon">
                                <input
                                    type="checkbox"
                                    name="protect_intrusions"
                                    value="1"
                                    x-model="protectIntrusions"
                                    class="rounded-sm border-slate-400 text-pt-carbon focus:ring-pt-graphite"
                                />
                                <span>Conservar intrusiones confirmadas</span>
                            </label>
                        @endif

                        <button
                            type="submit"
                            class="px-3.5 py-1.5 rounded-sm bg-transparent border border-pt-red/50 text-pt-red hover:bg-pt-red hover:border-pt-red hover:text-white text-xs font-medium transition-colors tabular-nums"
                        >
                            @if ($filters['status'] !== 'intrusion')
                                <span x-show="protectIntrusions">
                                    Eliminar lote ({{ $filteredNonIntrusionCount }} archivos · {{ $fmtBytes($filteredNonIntrusionBytes) }})
                                </span>
                                <span x-show="!protectIntrusions" style="display: none;">
                                    Eliminar lote completo ({{ $filteredCount }} archivos · {{ $fmtBytes($filteredBytes) }})
                                </span>
                            @else
                                Eliminar intrusiones filtradas ({{ $filteredCount }} archivos · {{ $fmtBytes($filteredBytes) }})
                            @endif
                        </button>
                    </form>
                @endif
            </div>
        </section>

        <!-- =========================================================================
             3. LISTADO TÉCNICO DE EVIDENCIAS Y BORRADO INDIVIDUAL
             ========================================================================= -->
        <section class="flex-1 bg-pt-surface flex flex-col justify-between">
            <div>
                <div class="hidden md:grid md:grid-cols-12 gap-4 px-5 sm:px-8 py-2.5 bg-[#E9EEF4] border-b border-slate-300/80 text-xs font-medium text-pt-muted">
                    <div class="col-span-2">Evidencia</div>
                    <div class="col-span-2">Fecha y hora (24h)</div>
                    <div class="col-span-2">Nodo / Evento</div>
                    <div class="col-span-3">Clasificación</div>
                    <div class="col-span-1 text-right">Tamaño</div>
                    <div class="col-span-2 text-right">Acción</div>
                </div>

                <div class="divide-y divide-slate-200">
                    @forelse ($alerts as $alert)
                        @php
                            $meta = $statusMeta[$alert->status] ?? $statusMeta['nueva'];
                            $sizeBytes = $alert->evidenceSizeBytes();
                            $imageUrl = route('alerts.evidence', $alert);
                            $eventLabel = $translateEvent($alert->event_type);
                        @endphp

                        <div class="group px-5 sm:px-8 py-3.5 hover:bg-slate-50/90 transition-colors border-l-4 {{ $alert->status === 'intrusion' ? 'border-l-pt-red' : ($alert->status === 'nueva' ? 'border-l-pt-amber' : 'border-l-transparent') }}">
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-center text-xs">

                                <!-- Miniatura (mismo tamaño y estilo que Panel en Vivo) -->
                                <div class="md:col-span-2 flex items-center">
                                    <button
                                        type="button"
                                        @click="openPreview(@js($imageUrl), @js('#' . $alert->id . ' — ' . $eventLabel . ' (' . $alert->created_at->format('d/m/Y H:i:s') . ')'))"
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

                                <!-- Fecha y hora -->
                                <div class="md:col-span-2 tabular-nums text-pt-carbon">
                                    <div class="font-medium">{{ $alert->created_at->format('d/m/Y') }}</div>
                                    <div class="text-pt-muted">{{ $alert->created_at->format('H:i:s') }} h</div>
                                </div>

                                <!-- Nodo / Evento -->
                                <div class="md:col-span-2">
                                    <div class="font-medium text-pt-carbon">{{ $eventLabel }}</div>
                                    <div class="text-pt-muted">Nodo: {{ $alert->device_id }}</div>
                                </div>

                                <!-- Clasificación -->
                                <div class="md:col-span-3 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $meta['dot'] }} shrink-0"></span>
                                    <span class="{{ $meta['text'] }}">{{ $meta['label'] }}</span>
                                </div>

                                <!-- Tamaño del archivo -->
                                <div class="md:col-span-1 md:text-right tabular-nums text-pt-muted">
                                    {{ $sizeBytes > 0 ? $fmtBytes($sizeBytes) : 'Sin archivo' }}
                                </div>

                                <!-- Eliminar individual -->
                                <div class="md:col-span-2 flex items-center md:justify-end">
                                    <form
                                        method="POST"
                                        action="{{ route('alerts.destroy', $alert) }}"
                                        onsubmit="return confirm('¿Eliminar permanentemente la evidencia #{{ $alert->id }} y su archivo físico?');"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="px-2.5 py-1 rounded-sm bg-transparent border border-pt-red/40 text-pt-red hover:bg-pt-red hover:border-pt-red hover:text-white text-xs font-medium transition-colors"
                                        >
                                            Eliminar
                                        </button>
                                    </form>
                                </div>

                            </div>
                        </div>
                    @empty
                        <div class="px-5 sm:px-8 py-12 text-center text-xs text-pt-muted">
                            No hay evidencias almacenadas que coincidan con los filtros seleccionados.
                        </div>
                    @endforelse
                </div>
            </div>

            @if ($alerts->hasPages())
                <div class="px-5 sm:px-8 py-3.5 bg-[#E9EEF4] border-t border-slate-300">
                    {{ $alerts->links() }}
                </div>
            @endif
        </section>

        <!-- Modal simple para previsualizar captura antes de eliminar -->
        <div
            x-show="previewOpen"
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-pt-carbon/85"
            style="display: none;"
        >
            <div @click.outside="previewOpen = false" class="w-full max-w-3xl bg-pt-carbon border border-slate-700 rounded-sm overflow-hidden">
                <div class="px-4 py-2.5 bg-[#101723] border-b border-white/10 flex items-center justify-between text-xs text-slate-200">
                    <span class="font-medium tabular-nums" x-text="previewTitle"></span>
                    <button type="button" @click="previewOpen = false" class="text-slate-400 hover:text-white px-2 py-0.5">
                        Cerrar (Esc)
                    </button>
                </div>
                <div class="p-4 flex items-center justify-center bg-[#0B101B]">
                    <img :src="previewUrl" alt="Vista previa" class="max-h-[70vh] w-auto object-contain rounded-sm border border-white/10" />
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
