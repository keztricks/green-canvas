<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Export History</h2>
            <a href="{{ route('exports.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                Create New Export
            </a>
        </div>

        @if($exports->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-600 dark:text-gray-400 mb-4">No exports created yet.</p>
                <a href="{{ route('exports.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded">
                    Create First Export
                </a>
            </div>
        @else
            <!-- Desktop Table View -->
            <div class="hidden md:block">
                <table class="w-full">
                    <thead class="bg-gray-50 dark:bg-gray-700 border-b-2 border-gray-200 dark:border-gray-600">
                        <tr>
                            <th class="px-3 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Version</th>
                            <th class="px-3 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Created</th>
                            <th class="px-3 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Records</th>
                            <th class="px-3 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Ward</th>
                            <th class="px-3 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Date Range</th>
                            <th class="px-3 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Notes</th>
                            <th class="px-3 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($exports as $export)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-3 py-3">
                                    <span class="inline-block bg-green-100 dark:bg-green-900 text-[#6AB023] dark:text-green-300 px-2 py-1 rounded-full text-sm font-medium">
                                        {{ $export->version }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $export->created_at->format('d M Y H:i') }}
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-700 dark:text-gray-300 font-medium">
                                    {{ number_format($export->record_count) }}
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    @if($export->ward)
                                        {{ $export->ward->name }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">All</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    @if($export->date_from && $export->date_to)
                                        {{ $export->date_from->format('d/m/y') }} - {{ $export->date_to->format('d/m/y') }}
                                    @elseif($export->date_from)
                                        From {{ $export->date_from->format('d/m/y') }}
                                    @elseif($export->date_to)
                                        Until {{ $export->date_to->format('d/m/y') }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">All dates</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $export->notes ?? '-' }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('exports.download', $export) }}" 
                                           class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-3 py-1 rounded text-sm"
                                           title="{{ $export->filename }}">
                                            Download
                                        </a>
                                        <form action="{{ route('exports.destroy', $export) }}" 
                                              method="POST" 
                                              onsubmit="return confirm('Are you sure you want to delete this export?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="bg-red-100 hover:bg-red-200 dark:bg-red-900 dark:hover:bg-red-800 text-red-800 dark:text-red-200 px-3 py-1 rounded text-sm">
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div class="md:hidden space-y-4">
                @foreach($exports as $export)
                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 shadow-sm">
                        <div class="flex justify-between items-start mb-3">
                            <span class="inline-block bg-green-100 dark:bg-green-900 text-[#6AB023] dark:text-green-300 px-3 py-1 rounded-full text-sm font-medium">
                                {{ $export->version }}
                            </span>
                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $export->created_at->format('d M Y H:i') }}
                            </span>
                        </div>
                        
                        <div class="space-y-2 mb-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Records:</span>
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ number_format($export->record_count) }}</span>
                            </div>
                            
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400">Ward:</span>
                                <span class="text-gray-900 dark:text-gray-100">
                                    @if($export->ward)
                                        {{ $export->ward->name }}
                                    @else
                                        <span class="text-gray-400 dark:text-gray-500">All</span>
                                    @endif
                                </span>
                            </div>
                            
                            @if($export->date_from || $export->date_to)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">Date Range:</span>
                                    <span class="text-gray-900 dark:text-gray-100">
                                        @if($export->date_from && $export->date_to)
                                            {{ $export->date_from->format('d/m/y') }} - {{ $export->date_to->format('d/m/y') }}
                                        @elseif($export->date_from)
                                            From {{ $export->date_from->format('d/m/y') }}
                                        @else
                                            Until {{ $export->date_to->format('d/m/y') }}
                                        @endif
                                    </span>
                                </div>
                            @endif
                            
                            @if($export->notes)
                                <div class="text-sm pt-2 border-t border-gray-100 dark:border-gray-700">
                                    <span class="text-gray-600 dark:text-gray-400 block mb-1">Notes:</span>
                                    <span class="text-gray-900 dark:text-gray-100">{{ $export->notes }}</span>
                                </div>
                            @endif
                        </div>
                        
                        <div class="flex gap-2">
                            <a href="{{ route('exports.download', $export) }}" 
                               class="flex-1 bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded text-sm text-center font-medium">
                                Download
                            </a>
                            <form action="{{ route('exports.destroy', $export) }}" 
                                  method="POST" 
                                  class="flex-shrink-0"
                                  onsubmit="return confirm('Are you sure you want to delete this export?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="bg-red-100 hover:bg-red-200 dark:bg-red-900 dark:hover:bg-red-800 text-red-800 dark:text-red-200 px-4 py-2 rounded text-sm font-medium">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>About Versions:</strong> Each export is versioned to help you track changes over time. 
                    Create a new version whenever you want to capture the current state of your canvassing data.
                </p>
            </div>
        @endif
        </div>
    </div>

    <script>
        function toggleFilters(id) {
            const element = document.getElementById('filters-' + id);
            element.classList.toggle('hidden');
        }
    </script>
</x-app-layout>
