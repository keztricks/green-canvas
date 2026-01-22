<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-3xl font-bold mb-6 text-gray-800">Select a Street to Canvas</h2>

                @if($streets->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-gray-600 mb-4">No addresses imported yet.</p>
                        <a href="{{ route('import.index') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded">
                            Import Addresses
                        </a>
                    </div>
                @else
                    <!-- Search/Filter Input -->
                    <div class="mb-4">
                        <input type="text" 
                               id="streetSearch" 
                               placeholder="Search streets..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#6AB023] focus:border-transparent">
                    </div>

                    <div id="streetsList" class="space-y-3">
                        @foreach($streets as $street)
                            <a href="{{ route('canvassing.street', ['streetName' => $street->street_name]) }}" 
                               class="street-item block p-4 border border-gray-200 rounded hover:bg-green-50 hover:border-[#6AB023] transition"
                               data-street-name="{{ strtolower($street->street_name) }}"
                               data-town="{{ strtolower($street->town) }}">
                                <div class="flex justify-between items-center">
                                    <div>
                                        <h3 class="text-lg font-semibold text-gray-800">{{ $street->street_name }}</h3>
                                        <p class="text-sm text-gray-600">{{ $street->town }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-medium text-gray-700">
                                            {{ $street->knocked_count }} / {{ $street->address_count }} knocked
                                        </p>
                                        <div class="w-32 bg-gray-200 rounded-full h-2 mt-1">
                                            <div class="bg-[#6AB023] h-2 rounded-full" 
                                                 style="width: {{ $street->address_count > 0 ? ($street->knocked_count / $street->address_count * 100) : 0 }}%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>

                    <div id="noResults" class="hidden text-center py-8 text-gray-500">
                        <p>No streets found matching your search.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('streetSearch');
        const streetItems = document.querySelectorAll('.street-item');
        const noResults = document.getElementById('noResults');

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase().trim();
                let visibleCount = 0;

                streetItems.forEach(function(item) {
                    const streetName = item.getAttribute('data-street-name');
                    const town = item.getAttribute('data-town');
                    
                    if (streetName.includes(searchTerm) || town.includes(searchTerm)) {
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
