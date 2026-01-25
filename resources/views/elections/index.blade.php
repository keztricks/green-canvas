<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-3xl font-bold text-gray-800">Elections</h2>
                    <a href="{{ route('elections.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                        Add Election
                    </a>
                </div>

                @if(session('success'))
                    <div class="bg-green-50 border border-[#6AB023] text-green-800 px-4 py-3 rounded mb-6">
                        {{ session('success') }}
                    </div>
                @endif

                @if($elections->isEmpty())
                    <div class="text-center py-12">
                        <p class="text-gray-600 mb-4">No elections added yet.</p>
                        <a href="{{ route('elections.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded">
                            Add First Election
                        </a>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b-2 border-gray-200">
                                <tr>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Name</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Date</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Type</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700">Wards</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($elections as $election)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-4 py-3 font-medium text-gray-900">
                                            {{ $election->name }}
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            {{ $election->election_date->format('d M Y') }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-700">
                                                {{ ucfirst($election->type) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-600">
                                            @if($election->wards->isNotEmpty())
                                                {{ $election->wards->pluck('name')->join(', ') }}
                                            @else
                                                <span class="text-gray-400">All wards</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <form action="{{ route('elections.destroy', $election) }}" 
                                                  method="POST" 
                                                  onsubmit="return confirm('Are you sure you want to delete this election?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 font-medium text-sm">
                                                    Delete
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
