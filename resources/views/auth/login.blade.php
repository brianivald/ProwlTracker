<x-guest-layout>
    <div class="mb-5">
        <h1 class="text-base font-semibold text-pt-carbon">Acceso al sistema</h1>
        <p class="text-xs text-pt-muted mt-0.5">Ingresa tus credenciales para acceder a la consola de monitoreo.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Correo electrónico -->
        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" class="block mt-1.5 w-full text-xs rounded-sm" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5" />
        </div>

        <!-- Contraseña -->
        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input
                id="password"
                class="block mt-1.5 w-full text-xs rounded-sm"
                type="password"
                name="password"
                required
                autocomplete="current-password"
            />
            <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
        </div>

        <!-- Recordarme -->
        <div class="pt-1">
            <label for="remember_me" class="inline-flex items-center cursor-pointer">
                <input id="remember_me" type="checkbox" class="rounded-sm border-slate-300 text-pt-carbon focus:ring-pt-graphite" name="remember">
                <span class="ms-2 text-xs text-pt-muted">Mantener sesión iniciada</span>
            </label>
        </div>

        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
            @if (Route::has('password.request'))
                <a class="underline text-xs text-pt-muted hover:text-pt-carbon focus:outline-none" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @else
                <span></span>
            @endif

            <x-primary-button class="ms-3">
                Iniciar sesión
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
