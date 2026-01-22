<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-3xl font-bold text-gray-800">Export History</h2>
            <a href="{{ route('exports.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                Create New Export
            </a>
        </div>

        @if($exports->isEmpty())
            <div class="text-center py-12">
                <p class="text-gray-600 mb-4">No exports created yet.</p>
                <a href="{{ route('exports.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded">
                    Create First Export
                </a>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b-2 border-gray-200">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Version</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Created</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Records</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Notes</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Filename</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach($exports as $export)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="inline-block bg-green-100 text-[#6AB023] px-3 py-1 rounded-full text-sm font-medium">
                                        {{ $export->version }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $export->created_at->format('d M Y H:i') }}
                                    <span class="text-xs text-gray-400 block">
                                        {{ $export->created_at->diffForHumans() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700 font-medium">
                                    {{ number_format($export->record_count) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $export->notes ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-500 font-mono">
                                    {{ $export->filename }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex justify-end gap-2">
                                        <a href="{{ route('exports.download', $export) }}" 
                                           class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-3 py-1 rounded text-sm">
                                            Download
                                        </a>
                                        <form action="{{ route('exports.destroy', $export) }}" 
                                              method="POST" 
                                              onsubmit="return confirm('Are you sure you want to delete this export?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="bg-red-100 hover:bg-red-200 text-red-800 px-3 py-1 rounded text-sm">
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

            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
                <p class="text-sm text-blue-800">
                    <strong>About Versions:</strong> Each export is versioned to help you track changes over time. 
                    Create a new version whenever you want to capture the current state of your canvassing data.
                </p>
            </div>
        @endif
        </div>
    </div>
</x-app-layout>
