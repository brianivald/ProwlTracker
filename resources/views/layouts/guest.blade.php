<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ProwlTracker') }} — Acceso al sistema</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-pt-carbon antialiased bg-pt-carbon">
        <div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 bg-pt-carbon">
            <div class="w-full sm:max-w-md">

                <!-- Identidad del sistema -->
                <div class="flex flex-col items-center mb-6">
                    <a href="/" class="inline-flex items-center gap-3 group">
                        <span class="flex items-center justify-center
                                     w-12 h-12 rounded-md
                                     bg-pt-graphite
                                     border border-slate-700/70
                                     text-white">
                            <x-application-logo class="w-12 h-12" />
                        </span>

                        <span class="text-2xl font-semibold tracking-tight text-white">
                            ProwlTracker
                        </span>
                    </a>
                </div>
            </div>

            <div class="w-full sm:max-w-md px-6 py-6 bg-pt-surface border border-slate-200 rounded-sm shadow-sm">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
