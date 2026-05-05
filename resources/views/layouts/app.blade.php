<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/favicon.svg">
        <link rel="alternate icon" href="/favicon.ico">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @if(\App\Models\FeatureFlag::isEnabled('dark_mode'))
        <!-- Theme Script - Prevents Flash -->
        <script>
            window.darkModeEnabled = true;
            (function() {
                const theme = localStorage.getItem('theme-preference') || 'system';
                const effectiveTheme = theme === 'system'
                    ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
                    : theme;
                if (effectiveTheme === 'dark') {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
        @else
        <!-- Force Light Mode -->
        <script>
            window.darkModeEnabled = false;
            (function() {
                document.documentElement.classList.remove('dark');
                localStorage.removeItem('theme-preference');
            })();
        </script>
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-100 dark:bg-gray-900 flex flex-col">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="flex-grow">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="bg-gray-100 dark:bg-gray-900">
                <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                    <div class="text-center text-sm text-gray-500 dark:text-gray-400">
                        <div>v{{ config('app.version') }}</div>
                        @if($credit = config('canvassing.credit_line'))
                            <div class="text-xs mt-1">{{ $credit }}</div>
                        @endif
                    </div>
                </div>
            </footer>
        </div>
    </body>
</html>
