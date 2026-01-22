<x-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Import Addresses</h2>

        <div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded">
            <h3 class="font-semibold text-blue-900 mb-2">Electoral Register CSV Format</h3>
            <p class="text-sm text-blue-800 mb-2">The importer supports UK electoral register CSV files with the following columns:</p>
            <code class="block bg-blue-100 p-2 rounded text-xs overflow-x-auto">
                Elector Number Prefix, Elector Number, Elector Number Suffix, Elector Markers, Elector DOB, Elector Name, PostCode, Address1, Address2, Address3...
            </code>
            <p class="text-xs text-blue-700 mt-2">
                The importer extracts: <strong>Address1</strong> (house number), <strong>Address2</strong> (street), <strong>Address3</strong> (town), and <strong>PostCode</strong>
            </p>
            <p class="text-xs text-blue-700 mt-1">
                <strong>Note:</strong> Duplicate addresses are automatically skipped to create one entry per address.
            </p>
        </div>

        @if($addressCount > 0)
            <div class="mb-6 p-4 bg-green-50 border border-green-200 rounded">
                <p class="text-green-800">
                    <strong>{{ number_format($addressCount) }}</strong> addresses currently in database
                </p>
            </div>
        @endif

        <form action="{{ route('import.store') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Select CSV File
                </label>
                <input type="file" 
                       name="csv_file" 
                       accept=".csv,.txt"
                       required
                       class="block w-full text-sm text-gray-900 border border-gray-300 rounded cursor-pointer bg-gray-50 focus:outline-none p-2">
                @error('csv_file')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" 
                    class="w-full bg-[#6AB023] hover:bg-[#5a9620] text-white font-medium py-3 px-4 rounded">
                Import Addresses
            </button>
        </form>

        @if($addressCount > 0)
            <div class="mt-8 pt-8 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Danger Zone</h3>
                <form action="{{ route('import.clear') }}" method="POST" onsubmit="return confirm('Are you sure you want to delete ALL addresses and results? This cannot be undone!');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" 
                            class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded">
                        Clear All Addresses
                    </button>
                </form>
            </div>
        @endif
        </div>
    </div>
</x-app-layout>
