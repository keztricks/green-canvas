<x-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('elections.index') }}" class="text-[#6AB023] hover:text-[#5a9620]">
                    ← Back to Elections
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h2 class="text-3xl font-bold mb-6 text-gray-800 dark:text-white">Add Election</h2>

                <form action="{{ route('elections.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Election Name <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               value="{{ old('name') }}" 
                               required
                               class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]"
                               placeholder="e.g., General Election 2024, Local Election 2023">
                        @error('name')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Election Date <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <input type="date" 
                               name="election_date" 
                               value="{{ old('election_date') }}" 
                               required
                               class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                        @error('election_date')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Type <span class="text-red-600 dark:text-red-400">*</span>
                        </label>
                        <select name="type" 
                                required
                                id="election-type"
                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                            <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>General Election</option>
                            <option value="local" {{ old('type') == 'local' ? 'selected' : '' }}>Local Election</option>
                            <option value="by-election" {{ old('type') == 'by-election' ? 'selected' : '' }}>By-Election</option>
                            <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('type')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="ward-selection" class="hidden">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                            Wards (optional)
                        </label>
                        <div class="border border-gray-300 dark:border-gray-600 dark:bg-gray-700 rounded px-4 py-3 max-h-60 overflow-y-auto space-y-2">
                            @foreach($wards as $ward)
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           name="ward_ids[]" 
                                           value="{{ $ward->id }}"
                                           {{ in_array($ward->id, old('ward_ids', [])) ? 'checked' : '' }}
                                           class="rounded border-gray-300 dark:border-gray-600 text-[#6AB023] focus:ring-[#6AB023]">
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-200">{{ $ward->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('ward_ids')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" 
                                class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-3 rounded font-medium">
                            Add Election
                        </button>
                        <a href="{{ route('elections.index') }}" 
                           class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-6 py-3 rounded font-medium">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('election-type').addEventListener('change', function() {
            const wardSelection = document.getElementById('ward-selection');
            if (this.value === 'local' || this.value === 'by-election') {
                wardSelection.classList.remove('hidden');
            } else {
                wardSelection.classList.add('hidden');
            }
        });

        // Show on page load if type is already selected
        document.addEventListener('DOMContentLoaded', function() {
            const type = document.getElementById('election-type').value;
            if (type === 'local' || type === 'by-election') {
                document.getElementById('ward-selection').classList.remove('hidden');
            }
        });
    </script>
</x-app-layout>
