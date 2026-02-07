<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-white">Feature Flags</h1>
                <p class="text-gray-600 dark:text-gray-300 mt-2">Control which features are enabled or disabled in the application.</p>
            </div>

            @if(session('success'))
                <div class="mb-6 p-4 bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow">
                @if($flags->isEmpty())
                    <div class="p-8 text-center text-gray-500 dark:text-gray-400">
                        No feature flags configured yet.
                    </div>
                @else
                    <div class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($flags as $flag)
                            <div class="p-6 flex items-center justify-between hover:bg-gray-50 dark:hover:bg-gray-700">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white">{{ $flag->name }}</h3>
                                        <span class="px-2 py-1 text-xs font-medium rounded {{ $flag->is_enabled ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-400' }}">
                                            {{ $flag->is_enabled ? 'ENABLED' : 'DISABLED' }}
                                        </span>
                                    </div>
                                    @if($flag->description)
                                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $flag->description }}</p>
                                    @endif
                                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Key: <code class="bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">{{ $flag->key }}</code></p>
                                </div>
                                <form action="{{ route('feature-flags.toggle', $flag) }}" method="POST" class="ml-6">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" 
                                            class="px-4 py-2 rounded font-medium transition-colors
                                                {{ $flag->is_enabled 
                                                    ? 'bg-red-100 hover:bg-red-200 text-red-700' 
                                                    : 'bg-[#6AB023] hover:bg-[#5a9620] text-white' }}">
                                        {{ $flag->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
