<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-3xl font-bold mb-6 text-gray-800">Select a Ward to Canvas</h2>

                @if($wards->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-gray-600 mb-4">No wards available yet.</p>
                        <a href="{{ route('import.index') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded">
                            Import Addresses
                        </a>
                    </div>
                @else
                    <!-- Search/Filter Input -->
                    <div class="mb-4">
                        <input type="text" 
                               id="wardSearch" 
                               placeholder="Search wards..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#6AB023] focus:border-transparent">
                    </div>

                    <div id="wardsList" class="space-y-3">
                        @foreach($wards as $ward)
                            <a href="{{ route('canvassing.ward', $ward->id) }}" 
                               class="ward-item block p-4 border border-gray-200 rounded hover:bg-green-50 hover:border-[#6AB023] transition"
                               data-ward-name="{{ strtolower($ward->name) }}">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">{{ $ward->name }}</h3>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-700">
                                            {{ $ward->addresses_count }} addresses
                                        </p>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div id="noResults" class="hidden text-center py-8 text-gray-500">
                        <p>No wards found matching your search.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('wardSearch');
        const wardItems = document.querySelectorAll('.ward-item');
        const noResults = document.getElementById('noResults');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let visibleCount = 0;

                wardItems.forEach(function(item) {
                    const wardName = item.getAttribute('data-ward-name');
                    
                    if (wardName.includes(searchTerm)) {
                        item.classList.remove('hidden');
                        visibleCount++;
                    } else {
                        item.classList.add('hidden');
                    }
                });

                if (visibleCount === 0) {
                    noResults.classList.remove('hidden');
                } else {
                    noResults.classList.add('hidden');
                }
            });
        }
    });
    </script>
</x-app-layout>
