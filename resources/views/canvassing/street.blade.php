<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('canvassing.ward', $ward->id) }}" class="text-[#6AB023] hover:text-[#5a9620]">
            ← Back to {{ $ward->name }} Streets
        </a>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="mb-6">
            <p class="text-sm text-gray-600">{{ $ward->name }}</p>
            <h2 class="text-3xl font-bold mb-2 text-gray-800">{{ $streetName }}</h2>
            <p class="text-gray-600">{{ $town }}</p>
        </div>

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
                                    @if($latestResult->vote_likelihood)
                                        <p class="text-sm text-gray-700 mt-1">
                                            Vote likelihood: <span class="font-semibold">{{ $latestResult->vote_likelihood }}/5</span>
                                        </p>
                                    @endif
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Voting Intention</label>
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
                            <label class="block text-sm font-medium text-gray-700 mb-2">Vote Likelihood (optional)</label>
                            <div class="flex gap-3">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center justify-center bg-white border-gray-400 rounded-lg hover:border-[#6AB023] cursor-pointer transition-all shadow-sm vote-likelihood-option" style="min-width: 64px; width: 64px; height: 64px; border-width: 3px;">
                                        <input type="radio" name="vote_likelihood" value="{{ $i }}" class="sr-only" onchange="updateVoteLikelihood(this)">
                                        <span class="text-2xl font-semibold text-gray-700">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1 = Very unlikely, 5 = Very likely</p>
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

function updateVoteLikelihood(radio) {
    // Get all vote likelihood labels in the same form
    const form = radio.closest('form');
    const labels = form.querySelectorAll('.vote-likelihood-option');
    
    // Reset all labels
    labels.forEach(label => {
        label.classList.remove('border-[#6AB023]', 'bg-green-50');
        label.classList.add('border-gray-400');
        label.style.borderWidth = '3px';
        const span = label.querySelector('span');
        span.classList.remove('text-[#6AB023]', 'font-bold');
        span.classList.add('text-gray-700', 'font-semibold');
    });
    
    // Highlight selected label
    const selectedLabel = radio.closest('label');
    selectedLabel.classList.remove('border-gray-400');
    selectedLabel.classList.add('border-[#6AB023]', 'bg-green-50');
    selectedLabel.style.borderWidth = '3px';
    const span = selectedLabel.querySelector('span');
    span.classList.remove('text-gray-700', 'font-semibold');
    span.classList.add('text-[#6AB023]', 'font-bold');
}
</script>
        </div>
    </div>
</x-app-layout>
