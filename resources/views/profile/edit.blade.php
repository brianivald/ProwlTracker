<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-sm sm:text-base font-semibold text-white">
                Cuenta y credenciales de operador
            </h1>
        </div>
    </x-slot>

    <div class="flex-1 bg-pt-surface divide-y divide-slate-300">
        <div class="px-5 sm:px-8 py-6">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="px-5 sm:px-8 py-6 bg-[#EDF1F6]">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="px-5 sm:px-8 py-6">
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</x-app-layout>
