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
                    $allResults = $address->knockResults;
                    $latestResult = $allResults->first();
                    $hasResult = $latestResult !== null;
                    $hasHistory = $allResults->count() > 1;
                @endphp

                <div id="address-{{ $address->id }}" class="border rounded-lg p-4 {{ $address->do_not_knock ? 'bg-red-50 border-red-500 border-2' : ($hasResult ? 'bg-gray-50 border-gray-300' : 'bg-white border-gray-200') }}">
                    @if($address->do_not_knock)
                        <div class="mb-3 p-3 bg-red-100 border-l-4 border-red-500 rounded">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-bold text-red-800 text-sm">⚠️ DO NOT KNOCK</p>
                                    <p class="text-xs text-red-700 mt-1">Marked on {{ $address->do_not_knock_at->format('d/m/Y H:i') }}</p>
                                </div>
                                <form action="{{ route('address.clear-do-not-knock', $address) }}" method="POST" onsubmit="return confirm('Are you sure you want to clear the Do Not Knock status?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-xs">
                                        Clear
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold {{ $address->do_not_knock ? 'text-red-800' : 'text-gray-800' }}">
                                {{ $address->house_number }} {{ $address->street_name }}
                            </h3>
                            <p class="text-sm text-gray-600">{{ $address->postcode }}</p>

                            @if($elections->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach($elections as $election)
                                        @php
                                            $hasVoted = $address->elections->contains('id', $election->id);
                                            $suffix = $election->type === 'general' ? '-GE' : '';
                                        @endphp
                                        <button type="button"
                                                onclick="toggleElection({{ $address->id }}, {{ $election->id }}, this)"
                                                class="text-xs px-2 py-1 rounded {{ $hasVoted ? 'bg-green-100 text-green-700 border border-green-300' : 'bg-gray-100 text-gray-500 border border-gray-300' }}"
                                                data-voted="{{ $hasVoted ? '1' : '0' }}"
                                                title="{{ $election->name }} - {{ $election->election_date->format('d/m/Y') }}">
                                            {{ $election->election_date->format('y') }}{{ $suffix }} {{ $hasVoted ? '✓' : '✗' }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if($hasResult)
                                <div class="mt-2 p-3 bg-white rounded border-l-4 
                                    @if($latestResult->response === 'green') border-green-500
                                    @elseif($latestResult->response === 'labour') border-red-500
                                    @elseif($latestResult->response === 'conservative') border-blue-500
                                    @elseif($latestResult->response === 'lib_dem') border-orange-400
                                    @elseif($latestResult->response === 'undecided') border-yellow-500
                                    @else border-gray-400
                                    @endif"
                                    @if($latestResult->response === 'reform') style="border-left-color: #17B9D1;" @endif>
                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                        <div class="flex-1">
                                            <p class="font-medium text-sm">
                                                Latest: <span class="font-bold">{{ $responseOptions[$latestResult->response] }}</span>
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
                                                @if($latestResult->user)
                                                    by {{ $latestResult->user->name }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex space-x-2 sm:space-x-1 sm:ml-2">
                                            <button onclick="toggleEditForm({{ $latestResult->id }})" 
                                                    class="text-blue-600 hover:text-blue-800 text-xs px-3 py-1 sm:px-2 border border-blue-600 rounded sm:border-0">
                                                Edit
                                            </button>
                                            <form action="{{ route('knock-result.destroy', $latestResult) }}" 
                                                  method="POST" 
                                                  onsubmit="return confirm('Are you sure you want to delete this result?')"
                                                  class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs px-3 py-1 sm:px-2 border border-red-600 rounded sm:border-0">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                @if($hasHistory)
                                    <div class="mt-2">
                                        <button onclick="toggleHistory({{ $address->id }})" 
                                                class="text-sm text-gray-600 hover:text-gray-800 underline">
                                            Show history ({{ $allResults->count() - 1 }} previous)
                                        </button>
                                        <div id="history-{{ $address->id }}" class="hidden mt-2 space-y-2">
                                            @foreach($allResults->skip(1) as $result)
                                                <div class="p-2 bg-gray-100 rounded border-l-4 
                                                    @if($result->response === 'green') border-green-500
                                                    @elseif($result->response === 'labour') border-red-500
                                                    @elseif($result->response === 'conservative') border-blue-500
                                                    @elseif($result->response === 'lib_dem') border-orange-400
                                                    @elseif($result->response === 'undecided') border-yellow-500
                                                    @else border-gray-400
                                                    @endif text-sm"
                                                    @if($result->response === 'reform') style="border-left-color: #17B9D1;" @endif>
                                                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-2">
                                                        <div class="flex-1">
                                                            <p class="font-medium">{{ $responseOptions[$result->response] }}
                                                                @if($result->vote_likelihood)
                                                                    <span class="text-gray-600">({{ $result->vote_likelihood }}/5)</span>
                                                                @endif
                                                            </p>
                                                            @if($result->notes)
                                                                <p class="text-gray-600 mt-1">{{ $result->notes }}</p>
                                                            @endif
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                {{ $result->knocked_at->format('d/m/Y H:i') }}
                                                                @if($result->user)
                                                                    by {{ $result->user->name }}
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div class="flex space-x-2 sm:space-x-1 sm:ml-2">
                                                            <button onclick="toggleEditForm({{ $result->id }})" 
                                                                    class="text-blue-600 hover:text-blue-800 text-xs px-2 py-1 border border-blue-600 rounded sm:border-0">
                                                                Edit
                                                            </button>
                                                            <form action="{{ route('knock-result.destroy', $result) }}" 
                                                                  method="POST" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this result?')"
                                                                  class="inline">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs px-2 py-1 border border-red-600 rounded sm:border-0">
                                                                    Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Edit form for this result -->
                                                    <form id="edit-form-{{ $result->id }}" 
                                                          action="{{ route('knock-result.update', $result) }}" 
                                                          method="POST" 
                                                          class="hidden mt-3 space-y-2 border-t pt-2">
                                                        @csrf
                                                        @method('PUT')
                                                        
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 mb-1">Voting Intention</label>
                                                            <select name="response" required class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                                                @foreach($responseOptions as $value => $label)
                                                                    <option value="{{ $value }}" {{ $result->response === $value ? 'selected' : '' }}>
                                                                        {{ $label }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 mb-1">Vote Likelihood</label>
                                                            <select name="vote_likelihood" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">
                                                                <option value="">Not specified</option>
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <option value="{{ $i }}" {{ $result->vote_likelihood == $i ? 'selected' : '' }}>
                                                                        {{ $i }}
                                                                    </option>
                                                                @endfor
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 mb-1">Notes</label>
                                                            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-2 py-1 text-xs">{{ $result->notes }}</textarea>
                                                        </div>

                                                        <div class="flex space-x-2">
                                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs">
                                                                Save
                                                            </button>
                                                            <button type="button" onclick="toggleEditForm({{ $result->id }})" 
                                                                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-3 py-1 rounded text-xs">
                                                                Cancel
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                <!-- Edit form for latest result -->
                                <form id="edit-form-{{ $latestResult->id }}" 
                                      action="{{ route('knock-result.update', $latestResult) }}" 
                                      method="POST" 
                                      class="hidden mt-3 space-y-2 border-t pt-3">
                                    @csrf
                                    @method('PUT')
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Voting Intention</label>
                                        <select name="response" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                                            @foreach($responseOptions as $value => $label)
                                                <option value="{{ $value }}" {{ $latestResult->response === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Vote Likelihood</label>
                                        <select name="vote_likelihood" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                                            <option value="">Not specified</option>
                                            @for($i = 1; $i <= 5; $i++)
                                                <option value="{{ $i }}" {{ $latestResult->vote_likelihood == $i ? 'selected' : '' }}>
                                                    {{ $i }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                                        <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">{{ $latestResult->notes }}</textarea>
                                    </div>

                                    <div class="flex space-x-2">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                            Update Result
                                        </button>
                                        <button type="button" onclick="toggleEditForm({{ $latestResult->id }})" 
                                                class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            @endif
                        </div>

                        <div class="ml-4 flex flex-col gap-2">
                            @if(!$address->do_not_knock)
                                <button onclick="toggleForm({{ $address->id }})" 
                                        class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded w-20">
                                    {{ $hasResult ? 'New' : 'Record' }}
                                </button>
                                <form action="{{ route('address.mark-do-not-knock', $address) }}" method="POST" onsubmit="return confirm('Are you sure you want to mark this address as Do Not Knock?')">
                                    @csrf
                                    <button type="submit" class="w-20 bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded text-sm">
                                        DNK
                                    </button>
                                </form>
                            @endif
                        </div>
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
                            <div class="flex gap-2 sm:gap-3">
                                @for($i = 1; $i <= 5; $i++)
                                    <label class="flex items-center justify-center bg-white border-gray-400 rounded-lg hover:border-[#6AB023] cursor-pointer transition-all shadow-sm vote-likelihood-option flex-1" style="max-width: 64px; height: 56px; border-width: 3px;">
                                        <input type="radio" name="vote_likelihood" value="{{ $i }}" class="sr-only" onchange="updateVoteLikelihood(this)">
                                        <span class="text-xl sm:text-2xl font-semibold text-gray-700">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 mt-1">1 = Very unlikely, 5 = Very likely</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
                            <textarea name="notes" rows="2" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></textarea>
                        </div>

                        <div class="bg-gray-50 border border-gray-200 rounded p-3">
                            <p class="text-sm text-gray-700">
                                <span class="font-medium">Logged by:</span> {{ auth()->user()->name }}
                            </p>
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

function toggleEditForm(resultId) {
    const editForm = document.getElementById(`edit-form-${resultId}`);
    editForm.classList.toggle('hidden');
}

function toggleHistory(addressId) {
    const history = document.getElementById(`history-${addressId}`);
    const button = event.target;
    
    if (history.classList.contains('hidden')) {
        history.classList.remove('hidden');
        const count = button.textContent.match(/\d+/)[0];
        button.textContent = 'Hide history';
    } else {
        history.classList.add('hidden');
        const count = history.querySelectorAll('.p-2').length;
        button.textContent = `Show history (${count} previous)`;
    }
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

function toggleElection(addressId, electionId, button) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const url = `/address/${addressId}/election/${electionId}/toggle`;
    
    fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            const voted = data.voted;
            const suffix = button.textContent.includes('-GE') ? '-GE' : '';
            const year = button.textContent.match(/\d+/)[0];
            
            if (voted) {
                button.className = 'text-xs px-2 py-1 rounded bg-green-100 text-green-700 border border-green-300';
                button.innerHTML = `${year}${suffix} ✓`;
                button.dataset.voted = '1';
            } else {
                button.className = 'text-xs px-2 py-1 rounded bg-gray-100 text-gray-500 border border-gray-300';
                button.innerHTML = `${year}${suffix} ✗`;
                button.dataset.voted = '0';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update election status. Please try again.');
    });
}
</script>
        </div>
    </div>
</x-app-layout>
