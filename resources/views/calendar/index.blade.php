<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-sm sm:text-base font-semibold text-white">
                    Calendario de días no laborables
                </h1>
            </div>
            <span class="text-xs text-slate-300">
                Modo en fechas programadas: <strong class="text-white font-medium">Zona armada (24 h)</strong>
            </span>
        </div>
    </x-slot>

    @php
        // Agrupar días consecutivos que compartan el mismo motivo (reason)
        $groupedRanges = collect();

        foreach ($days as $day) {
            $lastGroup = $groupedRanges->last();

            if (
                $lastGroup !== null
                && (string) $lastGroup['reason'] === (string) $day->reason
                && $lastGroup['end']->copy()->addDay()->isSameDay($day->date)
            ) {
                $lastGroup['end'] = $day->date;
                $lastGroup['days']->push($day);
                $groupedRanges->put($groupedRanges->count() - 1, $lastGroup);
            } else {
                $groupedRanges->push([
                    'first_model' => $day,
                    'start' => $day->date,
                    'end' => $day->date,
                    'reason' => $day->reason,
                    'days' => collect([$day]),
                ]);
            }
        }
    @endphp

    <div class="flex-1 flex flex-col bg-pt-bg">

        @if (session('status'))
            <div class="px-5 sm:px-8 py-2.5 bg-pt-graphite text-slate-200 border-b border-white/10 border-l-4 border-l-pt-green flex items-center gap-2.5 text-xs">
                <span class="w-2 h-2 rounded-full bg-pt-green shrink-0"></span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <!-- Formulario superior para programar día o rango -->
        <section class="bg-[#E6EBF1] border-b border-slate-300 px-5 sm:px-8 py-5">
            <div class="mb-3">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-pt-carbon">
                    Programar día de descanso o periodo vacacional
                </h2>
                <p class="mt-0.5 text-xs text-pt-muted">
                    En las fechas registradas la zona permanece <strong>armada con vigilancia activa</strong> todo el día. Para un solo día deja «Hasta» vacío; para varios días selecciona el rango.
                </p>
            </div>

            <form method="POST" action="{{ route('calendar.store') }}" class="grid gap-4 sm:grid-cols-12 items-end">
                @csrf

                <div class="sm:col-span-3">
                    <x-input-label for="date" value="Desde" />
                    <x-text-input id="date" name="date" type="date" class="mt-1 block w-full text-xs rounded-sm tabular-nums" :value="old('date')" required />
                    <x-input-error :messages="$errors->get('date')" class="mt-1" />
                </div>

                <div class="sm:col-span-3">
                    <x-input-label for="until_date" value="Hasta (opcional)" />
                    <x-text-input id="until_date" name="until_date" type="date" class="mt-1 block w-full text-xs rounded-sm tabular-nums" :value="old('until_date')" />
                    <x-input-error :messages="$errors->get('until_date')" class="mt-1" />
                </div>

                <div class="sm:col-span-4">
                    <x-input-label for="reason" value="Motivo (opcional)" />
                    <x-text-input id="reason" name="reason" type="text" maxlength="120" class="mt-1 block w-full text-xs rounded-sm" :value="old('reason')" placeholder="Ej. Periodo vacacional o día festivo" />
                    <x-input-error :messages="$errors->get('reason')" class="mt-1" />
                </div>

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        class="w-full px-4 py-2 rounded-sm bg-pt-carbon hover:bg-pt-graphite text-white text-xs font-medium transition-colors"
                    >
                        Guardar
                    </button>
                </div>
            </form>
        </section>

        <!-- Listado agrupado de fechas y rangos -->
        <section class="flex-1 bg-pt-surface">
            <div class="px-5 sm:px-8 py-2.5 bg-[#E9EEF4] border-b border-slate-300/80 flex items-center justify-between text-xs font-medium text-pt-muted">
                <span>Fechas y periodos registrados</span>
                <span class="tabular-nums">
                    {{ $groupedRanges->count() }} {{ $groupedRanges->count() === 1 ? 'bloque' : 'bloques' }} ({{ $days->count() }} {{ $days->count() === 1 ? 'día' : 'días' }})
                </span>
            </div>

            <div class="divide-y divide-slate-200">
                @forelse ($groupedRanges as $group)
                    @php
                        $isRange = $group['days']->count() > 1;
                        $countDays = $group['days']->count();
                        $containsToday = $group['days']->contains(fn ($d) => $d->date->isToday());
                        $isAllPast = $group['end']->isPast() && ! $group['end']->isToday();
                    @endphp

                    <div
                        x-data="{ expanded: false }"
                        class="px-5 sm:px-8 py-3.5 text-xs border-l-4 transition-colors {{ $containsToday ? 'border-l-pt-amber bg-pt-amber/[0.04]' : ($isAllPast ? 'border-l-slate-200 opacity-60 bg-slate-50/50' : 'border-l-pt-carbon hover:bg-slate-50/80') }}"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <div class="flex flex-wrap items-center gap-3">
                                @if ($isRange)
                                    <!-- Rango de fechas agrupado -->
                                    <span class="font-semibold text-pt-carbon tabular-nums">
                                        {{ $group['start']->format('d/m/Y') }} – {{ $group['end']->format('d/m/Y') }}
                                    </span>

                                    <span class="px-2 py-0.5 rounded-sm bg-slate-100 border border-slate-200 text-pt-carbon font-medium tabular-nums">
                                        {{ $countDays }} días
                                    </span>

                                    <span class="text-pt-muted">
                                        Del {{ $group['start']->locale('es')->translatedFormat('l d \d\e F') }}
                                        al {{ $group['end']->locale('es')->translatedFormat('l d \d\e F \d\e Y') }}
                                    </span>
                                @else
                                    <!-- Día único -->
                                    <span class="font-semibold text-pt-carbon tabular-nums w-24">
                                        {{ $group['start']->format('d/m/Y') }}
                                    </span>

                                    <span class="text-pt-carbon capitalize">
                                        {{ $group['start']->locale('es')->translatedFormat('l d \d\e F \d\e Y') }}
                                    </span>
                                @endif

                                @if ($group['reason'])
                                    <span class="text-slate-300">•</span>
                                    <span class="text-pt-muted font-medium">{{ $group['reason'] }}</span>
                                @endif

                                @if ($containsToday)
                                    <span class="px-2 py-0.5 rounded-sm bg-pt-amber/15 border border-pt-amber/30 text-pt-amber font-medium">
                                        Vigente hoy
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @if ($isRange)
                                    <button
                                        type="button"
                                        @click="expanded = !expanded"
                                        class="px-2.5 py-1 rounded-sm bg-white hover:bg-slate-100 text-pt-muted hover:text-pt-carbon border border-slate-300 transition-colors"
                                    >
                                        <span x-text="expanded ? 'Ocultar días' : 'Desglosar ({{ $countDays }})'"></span>
                                    </button>
                                @endif

                                <form method="POST" action="{{ route('calendar.destroy', $group['first_model']) }}">
                                    @csrf
                                    @method('DELETE')
                                    @foreach ($group['days'] as $d)
                                        <input type="hidden" name="ids[]" value="{{ $d->id }}">
                                    @endforeach
                                    <button
                                        type="submit"
                                        class="px-2.5 py-1 rounded-sm bg-pt-bg hover:bg-pt-red hover:text-white text-pt-red border border-slate-300 transition-colors"
                                    >
                                        {{ $isRange ? 'Quitar periodo' : 'Quitar' }}
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Desglose opcional de días individuales dentro del rango -->
                        @if ($isRange)
                            <div
                                x-show="expanded"
                                x-transition
                                class="mt-3 pt-3 border-t border-slate-200/80 flex flex-wrap gap-2"
                                style="display: none;"
                            >
                                @foreach ($group['days'] as $singleDay)
                                    <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-sm bg-slate-50 border border-slate-200 text-[11px]">
                                        <span class="tabular-nums font-medium text-pt-carbon">{{ $singleDay->date->format('d/m/Y') }}</span>
                                        <span class="text-pt-muted capitalize">{{ $singleDay->date->locale('es')->translatedFormat('D') }}</span>
                                        <form method="POST" action="{{ route('calendar.destroy', $singleDay) }}" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                title="Quitar solo este día"
                                                class="text-pt-muted hover:text-pt-red ml-1 font-bold"
                                            >
                                                ×
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="px-8 py-14 text-center text-xs text-pt-muted">
                        No hay fechas no laborables registradas en el calendario.
                    </div>
                @endforelse
            </div>
        </section>

    </div>
</x-app-layout>
