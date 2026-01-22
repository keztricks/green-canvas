<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-3xl font-bold mb-6 text-gray-800">Manage Canvassers</h2>

        <!-- Add New Canvasser Form -->
        <div class="mb-8 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <h3 class="text-lg font-semibold mb-3 text-gray-700">Add New Canvasser</h3>
            <form action="{{ route('canvassers.store') }}" method="POST" class="flex gap-3">
                @csrf
                <div class="flex-1">
                    <input type="text" 
                           name="name" 
                           placeholder="Enter canvasser name" 
                           required
                           class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]"
                           value="{{ old('name') }}">
                    @error('name')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                    Add Canvasser
                </button>
            </form>
        </div>

        <!-- Canvassers List -->
        @if($canvassers->isEmpty())
            <div class="text-center py-8 text-gray-600">
                <p>No canvassers added yet. Add your first canvasser above.</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($canvassers as $canvasser)
                    <div class="flex items-center justify-between p-4 border border-gray-200 rounded hover:bg-gray-50 {{ !$canvasser->active ? 'opacity-60' : '' }}">
                        <div class="flex items-center gap-3">
                            <span class="text-lg font-medium text-gray-800">{{ $canvasser->name }}</span>
                            @if(!$canvasser->active)
                                <span class="text-xs bg-gray-200 text-gray-600 px-2 py-1 rounded">Inactive</span>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <form action="{{ route('canvassers.toggle', $canvasser) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <button type="submit" 
                                        class="px-4 py-2 rounded text-sm font-medium {{ $canvasser->active ? 'bg-yellow-100 hover:bg-yellow-200 text-yellow-800' : 'bg-green-50 hover:bg-green-100 text-[#6AB023] border border-[#6AB023]' }}">
                                    {{ $canvasser->active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                            <form action="{{ route('canvassers.destroy', $canvasser) }}" 
                                  method="POST" 
                                  onsubmit="return confirm('Are you sure you want to delete this canvasser?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" 
                                        class="bg-red-100 hover:bg-red-200 text-red-800 px-4 py-2 rounded text-sm font-medium">
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded">
                <p class="text-sm text-blue-800">
                    <strong>Tip:</strong> Only active canvassers will appear in the dropdown when recording knock results.
                </p>
            </div>
        @endif
        </div>
    </div>
</x-app-layout>
