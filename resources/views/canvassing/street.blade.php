<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('canvassing.index') }}" class="text-[#6AB023] hover:text-[#5a9620]">
            ← Back to Streets
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-3xl font-bold mb-2 text-gray-800">{{ $streetName }}</h2>
        <p class="text-gray-600 mb-6">{{ $town }}</p>

        <div class="space-y-4">
            @foreach($addresses as $address)
                @php
                    $latestResult = $address->knockResults->first();
                    $hasResult = $latestResult !== null;
                @endphp

                <div class="border rounded-lg p-4 {{ $hasResult ? 'bg-gray-50 border-gray-300' : 'bg-white border-gray-200' }}">
                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-800">
                                {{ $address->house_number }} {{ $address->street_name }}
                            </h3>
                            <p class="text-sm text-gray-600">{{ $address->postcode }}</p>

                            @if($hasResult)
                                <div class="mt-2 p-3 bg-white rounded border-l-4 
                                    @if($latestResult->response === 'green') border-green-500
                                    @elseif($latestResult->response === 'labour') border-red-500
                                    @elseif($latestResult->response === 'conservative') border-blue-500
                                    @elseif($latestResult->response === 'lib_dem') border-orange-400
                                    @elseif($latestResult->response === 'undecided') border-yellow-500
                                    @else border-gray-400
                                    @endif">
                                    <p class="font-medium text-sm">
                                        Last result: <span class="font-bold">{{ $responseOptions[$latestResult->response] }}</span>
                                    </p>
                                    @if($latestResult->notes)
                                        <p class="text-sm text-gray-600 mt-1">{{ $latestResult->notes }}</p>
                                    @endif
                                    <p class="text-xs text-gray-500 mt-1">
                                        {{ $latestResult->knocked_at->diffForHumans() }}
                                        @if($latestResult->canvasser_name)
                                            by {{ $latestResult->canvasser_name }}
                                        @endif
                                    </p>
                                </div>
                            @endif
                        </div>

                        <button onclick="toggleForm({{ $address->id }})" 
                                class="ml-4 bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded">
                            {{ $hasResult ? 'Update' : 'Record' }}
                        </button>
                    </div>

                    <form id="form-{{ $address->id }}" 
                          action="{{ route('knock-result.store') }}" 
                          method="POST" 
                          class="mt-4 hidden space-y-3 border-t pt-4">
                        @csrf
                        <input type="hidden" name="address_id" value="{{ $address->id }}">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Response</label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($responseOptions as $value => $label)
                                    <label class="flex items-center space-x-2 p-2 border rounded hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="response" value="{{ $value }}" required class="text-green-600">
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Canvasser (optional)</label>
                            <select name="canvasser_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                                <option value="">-- Select canvasser --</option>
                                @foreach($canvassers as $canvasser)
                                    <option value="{{ $canvasser->id }}">{{ $canvasser->name }}</option>
                                @endforeach
                            </select>
                            @if($canvassers->isEmpty())
                                <p class="text-xs text-gray-500 mt-1">
                                    <a href="{{ route('canvassers.index') }}" class="text-[#6AB023] hover:underline">Add canvassers</a> to track who is knocking doors
                                </p>
                            @endif
                        </div>

                        <div class="flex space-x-2">
                            <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded">
                                Save Result
                            </button>
                            <button type="button" onclick="toggleForm({{ $address->id }})" 
                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
</div>

<script>
function toggleForm(addressId) {
    const form = document.getElementById(`form-${addressId}`);
    form.classList.toggle('hidden');
}
</script>
        </div>
    </div>
</x-app-layout>
