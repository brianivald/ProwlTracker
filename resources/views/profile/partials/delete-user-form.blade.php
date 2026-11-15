<section class="space-y-5">
    <header>
        <h2 class="text-sm font-semibold text-pt-carbon">
            Eliminar cuenta
        </h2>

        <p class="mt-1 text-xs text-pt-muted">
            Al eliminar tu cuenta, todos sus datos asociados se borrarán de forma permanente.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Eliminar cuenta</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-sm font-semibold text-pt-carbon">
                ¿Estás seguro de que deseas eliminar tu cuenta?
            </h2>

            <p class="mt-1 text-xs text-pt-muted">
                Esta acción es permanente. Ingresa tu contraseña actual para confirmar la eliminación de la cuenta.
            </p>

            <div class="mt-5">
                <x-input-label for="password" value="Contraseña" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4 text-xs rounded-sm"
                    placeholder="Contraseña actual"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5" />
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button>
                    Confirmar eliminación
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
