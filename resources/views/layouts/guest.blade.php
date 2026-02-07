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
        <!-- Theme Script -->
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
            document.documentElement.classList.remove('dark');
            localStorage.removeItem('theme-preference');
        </script>
        @endif

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 dark:text-gray-100 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-green-50 to-green-100 dark:from-gray-900 dark:to-gray-800">
            <div class="text-center mb-6">
                <a href="/">
                    <img src="https://cdn.brandfetch.io/idPY-7lInb/w/1780/h/440/theme/dark/logo.png" 
                         alt="Green Party" 
                         class="h-16 mb-4 mx-auto theme-logo">
                </a>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Calderdale Greens</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Canvassing Reporting System</p>
            </div>

            <div class="w-full sm:max-w-md px-6 py-8 bg-white dark:bg-gray-800 shadow-lg overflow-hidden sm:rounded-lg border-t-4 border-[#6AB023]">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <footer class="mt-8">
                <div class="text-center text-sm text-gray-600 dark:text-gray-400">
                    <div>v{{ config('app.version') }}</div>
                    <div class="text-xs mt-1">Made in Halifax</div>
                </div>
            </footer>
        </div>
    </body>
</html>
