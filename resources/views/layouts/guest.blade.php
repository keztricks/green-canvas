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

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gradient-to-br from-green-50 to-green-100">
            <div class="text-center mb-6">
                <a href="/">
                    <img src="https://cdn.brandfetch.io/idPY-7lInb/w/1780/h/440/theme/dark/logo.png" 
                         alt="Green Party" 
                         class="h-16 mb-4 mx-auto">
                </a>
                <h1 class="text-2xl font-bold text-gray-800">Calderdale Greens</h1>
                <p class="text-sm text-gray-600">Canvassing Reporting System</p>
            </div>

            <div class="w-full sm:max-w-md px-6 py-8 bg-white shadow-lg overflow-hidden sm:rounded-lg border-t-4 border-[#6AB023]">
                {{ $slot }}
            </div>

            <!-- Footer -->
            <footer class="mt-8">
                <div class="text-center text-sm text-gray-600">
                    <div>v{{ config('app.version') }}</div>
                    <div class="text-xs mt-1">Made in Halifax</div>
                </div>
            </footer>
        </div>
    </body>
</html>
