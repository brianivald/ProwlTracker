<div x-data="{ sidebarOpen: false }">
    <!-- Barra superior móvil -->
    <div class="lg:hidden flex items-center justify-between bg-pt-carbon text-slate-200 px-4 h-12 border-b border-white/10">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <span class="flex items-center justify-center w-7 h-7 rounded-sm bg-pt-graphite text-slate-100 border border-white/10">
                <x-application-logo class="w-4 h-4" />
            </span>
            <span class="font-semibold text-white text-sm">ProwlTracker</span>
        </a>

        <button
            type="button"
            @click="sidebarOpen = !sidebarOpen"
            class="p-1.5 text-slate-400 hover:text-white hover:bg-pt-graphite focus:outline-none"
            aria-label="Abrir navegación"
        >
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path :class="{ 'hidden': sidebarOpen, 'inline-flex': !sidebarOpen }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
                <path :class="{ 'hidden': !sidebarOpen, 'inline-flex': sidebarOpen }" class="hidden" stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Overlay móvil -->
    <div
        x-show="sidebarOpen"
        x-transition.opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-pt-carbon/75 lg:hidden"
        style="display: none;"
    ></div>

    <!-- Barra Lateral Principal -->
    <aside
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-40 w-56 bg-pt-carbon text-slate-300 flex flex-col justify-between border-r border-white/10 transition-transform duration-200 ease-in-out lg:translate-x-0 select-none"
    >
        <!-- Bloque Superior: Marca + Navegación -->
        <div class="overflow-y-auto">
            <!-- Cabecera de Marca -->
            <div class="h-14 px-4 flex items-center border-b border-white/10">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
                    <span class="flex items-center justify-center w-7 h-7 rounded-sm bg-pt-graphite text-slate-100 border border-white/10 shrink-0">
                        <x-application-logo class="w-4 h-4" />
                    </span>
                    <div>
                        <span class="block font-semibold text-white text-sm leading-none">ProwlTracker</span>
                        <span class="block text-[11px] text-slate-400 mt-1 leading-none">Monitoreo y seguridad</span>
                    </div>
                </a>
            </div>

            <!-- Categorías de Navegación -->
            <div class="py-4 space-y-5">
                <!-- OPERACIONES -->
                <div>
                    <p class="px-4 mb-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-500">
                        Operaciones
                    </p>
                    <nav>
                        <a
                            href="{{ route('dashboard') }}"
                            class="flex items-center justify-between px-4 py-2 text-xs font-medium transition-colors border-l-2 {{ request()->routeIs('dashboard') ? 'bg-pt-graphite text-white border-slate-200' : 'text-slate-400 hover:bg-pt-graphite/50 hover:text-slate-200 border-transparent' }}"
                        >
                            <span class="flex items-center gap-2.5">
                                <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <rect x="3" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="3" width="7" height="7" rx="1" />
                                    <rect x="14" y="14" width="7" height="7" rx="1" />
                                    <rect x="3" y="14" width="7" height="7" rx="1" />
                                </svg>
                                <span>Panel en vivo</span>
                            </span>
                        </a>
                    </nav>
                </div>

                <!-- GESTIÓN -->
                <div>
                    <p class="px-4 mb-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-500">
                        Gestión
                    </p>
                    <nav class="space-y-0.5">
                        <a
                            href="{{ route('calendar.index') }}"
                            class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium transition-colors border-l-2 {{ request()->routeIs('calendar.*') ? 'bg-pt-graphite text-white border-slate-200' : 'text-slate-400 hover:bg-pt-graphite/50 hover:text-slate-200 border-transparent' }}"
                        >
                            <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('calendar.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <rect x="3" y="4" width="18" height="18" rx="1" />
                                <line x1="16" y1="2" x2="16" y2="6" />
                                <line x1="8" y1="2" x2="8" y2="6" />
                                <line x1="3" y1="10" x2="21" y2="10" />
                            </svg>
                            <span>Calendario</span>
                        </a>

                        <a
                            href="{{ route('storage.index') }}"
                            class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium transition-colors border-l-2 {{ request()->routeIs('storage.*') ? 'bg-pt-graphite text-white border-slate-200' : 'text-slate-400 hover:bg-pt-graphite/50 hover:text-slate-200 border-transparent' }}"
                        >
                            <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('storage.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <ellipse cx="12" cy="5" rx="9" ry="3" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 5v14c0 1.66 4.03 3 9 3s9-1.34 9-3V5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12c0 1.66 4.03 3 9 3s9-1.34 9-3" />
                            </svg>
                            <span>Almacenamiento</span>
                        </a>
                    </nav>
                </div>

                <!-- SISTEMA -->
                <div>
                    <p class="px-4 mb-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-500">
                        Sistema
                    </p>
                    <nav>
                        <a
                            href="{{ route('settings.edit') }}"
                            class="flex items-center gap-2.5 px-4 py-2 text-xs font-medium transition-colors border-l-2 {{ request()->routeIs('settings.*') ? 'bg-pt-graphite text-white border-slate-200' : 'text-slate-400 hover:bg-pt-graphite/50 hover:text-slate-200 border-transparent' }}"
                        >
                            <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('settings.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                            <span>Configuración</span>
                        </a>
                    </nav>
                </div>

                <!-- CUENTA -->
                <div>
                    <p class="px-4 mb-1.5 text-[11px] font-medium uppercase tracking-wider text-slate-500">
                        Cuenta
                    </p>
                    <div>
                        <a
                            href="{{ route('profile.edit') }}"
                            class="flex items-center gap-2.5 px-4 py-2 text-xs transition-colors border-l-2 {{ request()->routeIs('profile.*') ? 'bg-pt-graphite text-white border-slate-200' : 'text-slate-400 hover:bg-pt-graphite/50 hover:text-slate-200 border-transparent' }}"
                        >
                            <svg class="w-4 h-4 shrink-0 {{ request()->routeIs('profile.*') ? 'text-white' : 'text-slate-400' }}" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            <div class="min-w-0 flex-1">
                                <span class="block font-medium text-slate-200 truncate leading-tight">{{ Auth::user()->name }}</span>
                                <span class="block text-[11px] text-slate-400 truncate leading-tight mt-0.5">{{ Auth::user()->email }}</span>
                            </div>
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button
                                type="submit"
                                class="w-full flex items-center gap-2.5 px-4 py-2 text-xs font-medium text-slate-400 hover:bg-pt-graphite/50 hover:text-white border-l-2 border-transparent transition-colors text-left"
                            >
                                <svg class="w-4 h-4 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h6a2 2 0 012 2v1" />
                                </svg>
                                <span>Cerrar sesión</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estado de conexión al pie (Reactivo cada 10s) -->
        @php
            $navNodeLastSeen = once(fn () => \Illuminate\Support\Facades\Cache::get('prowl:node-last-seen'));
            $navNodeOnline = $navNodeLastSeen && (now()->timestamp - (int) $navNodeLastSeen) <= (int) config('prowltracker.node_api.online_ttl', 120);
        @endphp
        <div
            x-data="{
                online: @js((bool) $navNodeOnline),
                init() {
                    const check = () => {
                        fetch('{{ route('node.status') }}', { headers: { 'Accept': 'application/json' } })
                            .then(r => r.ok ? r.json() : null)
                            .then(d => { if (d) this.online = !!d.online; })
                            .catch(() => {});
                    };
                    setInterval(check, 10000);
                }
            }"
            class="border-t border-white/10 bg-[#101723] px-4 py-3"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full shrink-0" :class="online ? 'bg-pt-green' : 'bg-slate-500'"></span>
                    <span class="text-xs text-slate-300" x-text="online ? 'Nodo conectado' : 'Nodo sin conexión'">{{ $navNodeOnline ? 'Nodo conectado' : 'Nodo sin conexión' }}</span>
                </div>
                <span class="text-[11px] text-slate-400" x-text="online ? 'Sincronizado' : 'En espera'">{{ $navNodeOnline ? 'Sincronizado' : 'En espera' }}</span>
            </div>
        </div>
    </aside>
</div>
