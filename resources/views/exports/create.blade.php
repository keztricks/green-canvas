<x-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('exports.index') }}" class="text-[#6AB023] hover:text-[#5a9620]">
            ← Back to Exports
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Create New Export</h2>

        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
            <h3 class="font-semibold text-blue-900 mb-2">Export Information</h3>
            <p class="text-sm text-blue-800">
                This will export <strong>{{ number_format($totalResults) }}</strong> knock results.
                Each export is versioned so you can track changes over time.
            </p>
        </div>

        <form action="{{ route('exports.store') }}" method="POST" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Version <span class="text-red-600">*</span>
                </label>
                <input type="text" 
                       name="version" 
                       value="{{ old('version', $nextVersion) }}" 
                       required
                       class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]"
                       placeholder="e.g., v1, v2, January2026">
                @error('version')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
                <p class="text-xs text-gray-500 mt-1">
                    Version must be unique. Suggested: <strong>{{ $nextVersion }}</strong>
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Format <span class="text-red-600">*</span>
                </label>
                <div class="flex gap-4">
                    @if(auth()->user()->isAdmin())
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="format" value="csv" checked required class="text-[#6AB023]">
                            <span class="text-sm">CSV (Comma-Separated Values)</span>
                        </label>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="radio" name="format" value="xlsx" required class="text-[#6AB023]">
                            <span class="text-sm">XLSX (Excel Spreadsheet)</span>
                        </label>
                    @else
                        <input type="hidden" name="format" value="xlsx">
                        <div class="text-sm text-gray-700 bg-gray-50 px-4 py-2 rounded border border-gray-200">
                            <span class="font-medium">XLSX (Excel Spreadsheet)</span>
                        </div>
                    @endif
                </div>
                @if(auth()->user()->isAdmin())
                    <p class="text-xs text-gray-500 mt-1">
                        CSV is provided only for admin users. CSVs are not password protected. XLSX provides better formatting in Excel.
                    </p>
                @else
                    <p class="text-xs text-gray-500 mt-1">
                        Exports are provided in XLSX format for better formatting and compatibility.
                    </p>
                @endif
            </div>

            <div class="border-t pt-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Optional Filters</h3>
                
                <div class="space-y-4">
                    <div>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input type="checkbox" 
                                   name="include_not_knocked" 
                                   value="1"
                                   {{ old('include_not_knocked') ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-[#6AB023] focus:ring-[#6AB023]">
                            <span class="text-sm font-medium text-gray-700">Include addresses that have not been knocked</span>
                        </label>
                        <p class="text-xs text-gray-500 mt-1 ml-6">When checked, the export will include all addresses in the selected scope, even those without any knock results</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Ward
                        </label>
                        <select name="ward_id" class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                            <option value="">All Wards</option>
                            @foreach($wards as $ward)
                                <option value="{{ $ward->id }}">{{ $ward->name }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-1">Filter results by a specific ward</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Date From
                            </label>
                            <input type="date" 
                                   name="date_from" 
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                            <p class="text-xs text-gray-500 mt-1">Start of date range</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Date To
                            </label>
                            <input type="date" 
                                   name="date_to" 
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                            <p class="text-xs text-gray-500 mt-1">End of date range</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Notes (optional)
                </label>
                <textarea name="notes" 
                          rows="3" 
                          class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]"
                          placeholder="Add any notes about this export (e.g., 'Pre-election snapshot', 'End of January canvassing')">{{ old('notes') }}</textarea>
                @error('notes')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h4 class="font-semibold text-gray-800 mb-2">Export will include:</h4>
                <ul class="text-sm text-gray-600 space-y-1">
                    <li>✓ House number and full address</li>
                    <li>✓ Response (party support)</li>
                    <li>✓ Notes from canvassers</li>
                    <li>✓ Canvasser names</li>
                    <li>✓ Date/time of each knock</li>
                </ul>
            </div>

            <div class="flex gap-3">
                <button type="submit" 
                        class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-3 rounded font-medium">
                    Create Export
                </button>
                <a href="{{ route('exports.index') }}" 
                   class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded font-medium">
                    Cancel
                </a>
            </div>
        </form>
        </div>
    </div>
</x-app-layout>
