<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') — OSPP CSMS Sandbox</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ospp: {
                            50: '#eff6ff',
                            500: '#2563eb',
                            600: '#1d4ed8',
                            700: '#1e40af',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @stack('head')
</head>
<body class="bg-gray-50 min-h-screen">
    @auth
        <div class="flex h-screen overflow-hidden">
            @include('layouts.sidebar')
            <main class="flex-1 overflow-y-auto p-6">
                @yield('content')
            </main>
        </div>
    @else
        @yield('content')
    @endauth
    <script src="https://cdn.jsdelivr.net/npm/pusher-js@8/dist/web/pusher.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1/dist/echo.iife.js"></script>
    @auth
    <script>
        window.Echo = new Echo({
            broadcaster: 'reverb',
            key: '{{ config("reverb.apps.apps.0.key", config("broadcasting.connections.reverb.key", "sandbox-key")) }}',
            wsHost: window.location.hostname,
            wsPort: {{ config('reverb.servers.reverb.port', 8080) }},
            forceTLS: false,
            enabledTransports: ['ws'],
        });
    </script>
    @endauth
    @stack('scripts')
</body>
</html>
