<x-app-layout>
    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('elections.index') }}" class="text-[#6AB023] hover:text-[#5a9620]">
                    ← Back to Elections
                </a>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-3xl font-bold mb-6 text-gray-800">Add Election</h2>

                <form action="{{ route('elections.store') }}" method="POST" class="space-y-6">
                    @csrf

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Election Name <span class="text-red-600">*</span>
                        </label>
                        <input type="text" 
                               name="name" 
                               value="{{ old('name') }}" 
                               required
                               class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]"
                               placeholder="e.g., General Election 2024, Local Election 2023">
                        @error('name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Election Date <span class="text-red-600">*</span>
                        </label>
                        <input type="date" 
                               name="election_date" 
                               value="{{ old('election_date') }}" 
                               required
                               class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                        @error('election_date')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Type <span class="text-red-600">*</span>
                        </label>
                        <select name="type" 
                                required
                                class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                            <option value="general" {{ old('type') == 'general' ? 'selected' : '' }}>General Election</option>
                            <option value="local" {{ old('type') == 'local' ? 'selected' : '' }}>Local Election</option>
                            <option value="by-election" {{ old('type') == 'by-election' ? 'selected' : '' }}>By-Election</option>
                            <option value="other" {{ old('type') == 'other' ? 'selected' : '' }}>Other</option>
                        </select>
                        @error('type')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-3">
                        <button type="submit" 
                                class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-3 rounded font-medium">
                            Add Election
                        </button>
                        <a href="{{ route('elections.index') }}" 
                           class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-6 py-3 rounded font-medium">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
