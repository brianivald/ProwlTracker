<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'ProwlTracker') }}</title>

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-pt-bg text-pt-carbon">
        <div class="min-h-screen flex flex-col lg:pl-56">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-pt-carbon text-white border-b border-pt-graphite">
                    <div class="px-5 sm:px-8 py-4">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-1 flex flex-col">
                {{ $slot }}
            </main>
        </div>
    </body>
</html>
