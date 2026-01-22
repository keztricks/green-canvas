<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Door Knocking App')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'green-party': {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#6AB023',  // Primary Green Party color
                            600: '#5a9620',
                            700: '#4a7c1a',
                            800: '#3a6214',
                            900: '#2a480f',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <nav class="bg-[#6AB023] text-white shadow-lg">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <div class="flex items-center gap-2 md:gap-4">
                    <img src="https://cdn.brandfetch.io/idPY-7lInb/w/1780/h/440/theme/light/logo.png?c=1bxid64Mup7aczewSAYMX&t=1758825917450" 
                         alt="Green Party" 
                         class="h-8 md:h-10 w-auto brightness-0 invert">
                    <h1 class="text-lg md:text-2xl font-bold md:border-l border-white/30 md:pl-4">
                        <a href="{{ route('canvassing.index') }}">Calderdale Green Party</a>
                    </h1>
                </div>
                
                <!-- Mobile menu button -->
                <button id="mobile-menu-button" class="md:hidden p-2 rounded hover:bg-white/10 transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        <path id="close-icon" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>

                <!-- Desktop menu -->
                <div class="hidden md:flex space-x-4">
                    <a href="{{ route('canvassing.index') }}" class="hover:text-green-100 transition">Streets</a>
                    <a href="{{ route('canvassers.index') }}" class="hover:text-green-100 transition">Canvassers</a>
                    <a href="{{ route('exports.index') }}" class="hover:text-green-100 transition">Exports</a>
                    <a href="{{ route('import.index') }}" class="hover:text-green-100 transition">Import</a>
                </div>
            </div>

            <!-- Mobile menu -->
            <div id="mobile-menu" class="hidden md:hidden mt-4 pb-2 space-y-2">
                <a href="{{ route('canvassing.index') }}" class="block py-2 px-4 hover:bg-white/10 rounded transition">Streets</a>
                <a href="{{ route('canvassers.index') }}" class="block py-2 px-4 hover:bg-white/10 rounded transition">Canvassers</a>
                <a href="{{ route('exports.index') }}" class="block py-2 px-4 hover:bg-white/10 rounded transition">Exports</a>
                <a href="{{ route('import.index') }}" class="block py-2 px-4 hover:bg-white/10 rounded transition">Import</a>
            </div>
        </div>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const menuButton = document.getElementById('mobile-menu-button');
            const mobileMenu = document.getElementById('mobile-menu');
            const menuIcon = document.getElementById('menu-icon');
            const closeIcon = document.getElementById('close-icon');

            if (menuButton) {
                menuButton.addEventListener('click', function() {
                    mobileMenu.classList.toggle('hidden');
                    menuIcon.classList.toggle('hidden');
                    closeIcon.classList.toggle('hidden');
                });
            }
        });
    </script>

    <main class="container mx-auto px-4 py-8">
        @if(session('success'))
            <div class="bg-green-50 border border-[#6AB023] text-green-900 px-4 py-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
