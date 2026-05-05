<x-app-layout>
    <div class="py-6 flex-grow min-h-full">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="mb-6">
        <a href="{{ route('canvassing.ward', $ward->id) }}{{ request()->has('election_filters') ? '?' . http_build_query(['election_filters' => request('election_filters')]) : '' }}" class="text-[#6AB023] hover:text-[#5a9620]">
            ← Back to {{ $ward->name }} Streets
        </a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <div class="mb-6">
            <p class="text-sm text-gray-600 dark:text-gray-300">{{ $ward->name }}</p>
            <h2 class="text-3xl font-bold mb-2 text-gray-800 dark:text-white">{{ $streetName }}</h2>
            <p class="text-gray-600 dark:text-gray-300">{{ $town }}</p>
        </div>

        <!-- Election Filters -->
        @include('canvassing.partials.election-filters')

        <!-- Knock Result Filters -->
        @include('canvassing.partials.knock-filters')

        <!-- Add Address Button -->
        <div class="mb-4 flex justify-end">
            <button onclick="toggleAddressModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center gap-2">
                <span>+</span> Add Missing Address
            </button>
        </div>

        <!-- Election Toggle -->
        <div class="mb-4">
            <label class="flex items-center space-x-2 cursor-pointer p-3 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors">
                <input type="checkbox" id="electionEditToggle" class="w-4 h-4 text-[#6AB023] rounded" onchange="toggleElectionEditing()">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
                    <span id="lockIcon">🔒</span> Enable election editing
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400 ml-auto">Click to toggle</span>
            </label>
        </div>

        @if($addresses->isEmpty())
            <div class="text-center py-12 text-gray-500 dark:text-gray-400">
                <p class="text-lg font-medium mb-2">No addresses match your filters</p>
                <p class="text-sm mb-4">Try adjusting or clearing your knock result filters.</p>
                <a href="{{ url()->current() }}" class="inline-block bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium">
                    Clear Filters
                </a>
            </div>
        @endif

        <div class="space-y-4">
            @foreach($addresses as $address)
                @php
                    $allResults = $address->knockResults;
                    $latestResult = $allResults->first();
                    $hasResult = $latestResult !== null;
                    $hasHistory = $allResults->count() > 1;
                    $isNeverVoter = $latestResult && $latestResult->vote_likelihood == 5;
                    $isWontVote = $latestResult && $latestResult->response === 'wont_vote';
                @endphp

                <div id="address-{{ $address->id }}" class="border rounded-lg p-4 {{ ($address->do_not_knock || $isNeverVoter || $isWontVote) ? 'bg-red-50 dark:bg-red-900 border-red-500 border-2' : ($hasResult ? 'bg-gray-50 dark:bg-gray-700 border-gray-300 dark:border-gray-600' : 'bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700') }}">
                    @if($address->do_not_knock)
                        <div class="mb-3 p-3 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 dark:border-red-700 rounded">
                            <div class="flex justify-between items-center">
                                <div>
                                    <p class="font-bold text-red-800 dark:text-red-300 text-sm">⚠️ DO NOT KNOCK</p>
                                    <p class="text-xs text-red-700 dark:text-red-400 mt-1">Marked on {{ $address->do_not_knock_at->format('d/m/Y') }}</p>
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
                    @elseif($isNeverVoter)
                        <div class="mb-3 p-3 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 dark:border-red-700 rounded">
                            <p class="font-bold text-red-800 dark:text-red-300 text-sm">💔 NEVER VOTING GREEN</p>
                            <p class="text-xs text-red-700 dark:text-red-400 mt-1">Vote likelihood: 5 (Never) - Recorded {{ $latestResult->knocked_at->diffForHumans() }}</p>
                        </div>
                    @elseif($isWontVote)
                        <div class="mb-3 p-3 bg-red-100 dark:bg-red-900 border-l-4 border-red-500 dark:border-red-700 rounded">
                            <p class="font-bold text-red-800 dark:text-red-300 text-sm">🚫 WON'T VOTE</p>
                            <p class="text-xs text-red-700 dark:text-red-400 mt-1">Resident will not be voting - Recorded {{ $latestResult->knocked_at->diffForHumans() }}</p>
                        </div>
                    @endif

                    <div class="flex justify-between items-start">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold {{ ($address->do_not_knock || $isNeverVoter || $isWontVote) ? 'text-red-800 dark:text-red-300' : 'text-gray-800 dark:text-white' }}">
                                {{ $address->house_number }} {{ $address->street_name }}
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-300">
                                {{ $address->postcode }}
                                @if($address->elector_count > 0)
                                    <span class="ml-2 text-xs bg-blue-100 text-blue-800 px-2 py-0.5 rounded dark:bg-blue-900 dark:text-blue-200">
                                        {{ $address->elector_count }} {{ Str::plural('elector', $address->elector_count) }}
                                    </span>
                                @endif
                            </p>

                            @if($elections->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1">
                                    @foreach($elections as $election)
                                        @php
                                            $electionPivot = $address->elections->where('id', $election->id)->first();
                                            $status = $electionPivot ? $electionPivot->pivot->status : 'unknown';
                                            $suffix = $election->type === 'general' ? '-GE' : '';
                                            
                                            $classes = match($status) {
                                                'voted' => 'bg-green-100 text-green-700 border border-green-300',
                                                'not_voted' => 'bg-red-100 text-red-700 border border-red-300',
                                                default => 'bg-gray-100 text-gray-500 border border-gray-300'
                                            };
                                            
                                            $symbol = match($status) {
                                                'voted' => '✓',
                                                'not_voted' => '✗',
                                                default => '?'
                                            };
                                        @endphp
                                        <button type="button"
                                                onclick="toggleElection({{ $address->id }}, {{ $election->id }}, this)"
                                                class="text-xs px-2 py-1 rounded {{ $classes }}"
                                                data-status="{{ $status }}"
                                                title="{{ $election->name }} - {{ $election->election_date->format('d/m/Y') }}">
                                            {{ $election->election_date->format('y') }}{{ $suffix }} {{ $symbol }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if($hasResult)
                                <div class="mt-2 p-3 bg-white dark:bg-gray-800 rounded border-l-4 {{ ($address->do_not_knock || $isNeverVoter || $isWontVote) ? 'hidden' : '' }} latest-result-{{ $address->id }}
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
                                            <p class="font-medium text-sm dark:text-gray-200">
                                                Latest: <span class="font-bold">{{ $responseOptions[$latestResult->response] }}</span>
                                            </p>
                                            @if($latestResult->vote_likelihood)
                                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">
                                                    Green support: <span class="font-semibold">{{ $latestResult->vote_likelihood }}/5</span>
                                                    @if($latestResult->vote_likelihood == 1)
                                                        <span class="text-xs text-green-600 dark:text-green-400">(Definitely)</span>
                                                    @elseif($latestResult->vote_likelihood == 5)
                                                        <span class="text-xs text-red-600 dark:text-red-400">(Never)</span>
                                                    @endif
                                                </p>
                                            @endif
                                            @if($latestResult->notes)
                                                <p class="text-sm text-gray-600 dark:text-gray-300 mt-1">{{ $latestResult->notes }}</p>
                                            @endif
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
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
                                        <button onclick="toggleHistory({{ $address->id }}, {{ ($address->do_not_knock || $isNeverVoter || $isWontVote) ? 'true' : 'false' }})" 
                                                class="text-sm text-gray-600 dark:text-gray-300 hover:text-gray-800 dark:hover:text-white underline">
                                            Show history ({{ ($address->do_not_knock || $isNeverVoter || $isWontVote) ? $allResults->count() : $allResults->count() - 1 }} {{ ($address->do_not_knock || $isNeverVoter || $isWontVote) ? 'results' : 'previous' }})
                                        </button>
                                        <div id="history-{{ $address->id }}" class="hidden mt-2 space-y-2">
                                            @foreach($allResults->skip(1) as $result)
                                                <div class="p-2 bg-gray-100 dark:bg-gray-700 rounded border-l-4 
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
                                                            <p class="font-medium dark:text-gray-200">{{ $responseOptions[$result->response] }}
                                                                @if($result->vote_likelihood)
                                                                    <span class="text-gray-600 dark:text-gray-300">({{ $result->vote_likelihood }}/5)</span>
                                                                @endif
                                                            </p>
                                                            @if($result->notes)
                                                                <p class="text-gray-600 dark:text-gray-300 mt-1">{{ $result->notes }}</p>
                                                            @endif
                                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
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
                                                          class="hidden mt-3 space-y-2 border-t dark:border-gray-600 pt-2"
                                                          x-data="{ turnoutLikelihood: '{{ $result->turnout_likelihood }}', response: '{{ $result->response }}' }">
                                                        @csrf
                                                        @method('PUT')
                                                        
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Home Party</label>
                                                            <select name="response" 
                                                                    required 
                                                                    x-model="response"
                                                                    @change="if (response === 'wont_vote') { turnoutLikelihood = 'wont'; $el.form.querySelector('select[name=turnout_likelihood]').value = 'wont'; }"
                                                                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-xs">
                                                                @foreach($responseOptions as $value => $label)
                                                                    <option value="{{ $value }}" {{ $result->response === $value ? 'selected' : '' }}>
                                                                        {{ $label }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        
                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Turnout Likelihood (optional)</label>
                                                            <select name="turnout_likelihood" 
                                                                    x-model="turnoutLikelihood"
                                                                    @change="if (turnoutLikelihood === 'wont') { response = 'wont_vote'; $el.form.querySelector('select[name=response]').value = 'wont_vote'; }"
                                                                    class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-xs">
                                                                <option value="">Not specified</option>
                                                                @foreach($turnoutLikelihoodOptions as $value => $label)
                                                                    <option value="{{ $value }}" {{ $result->turnout_likelihood === $value ? 'selected' : '' }}>
                                                                        {{ $label }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Green Support (1=Def, 5=Never)</label>
                                                            <select name="vote_likelihood" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-xs">
                                                                <option value="">Not specified</option>
                                                                @for($i = 1; $i <= 5; $i++)
                                                                    <option value="{{ $i }}" {{ $result->vote_likelihood == $i ? 'selected' : '' }}>
                                                                        {{ $i }}
                                                                    </option>
                                                                @endfor
                                                            </select>
                                                        </div>

                                                        <div>
                                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-200 mb-1">Notes</label>
                                                            <textarea name="notes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-2 py-1 text-xs">{{ $result->notes }}</textarea>
                                                        </div>

                                                        <div class="flex space-x-2">
                                                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs">
                                                                Save
                                                            </button>
                                                            <button type="button" onclick="toggleEditForm({{ $result->id }})" 
                                                                    class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-3 py-1 rounded text-xs">
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
                                      class="hidden mt-3 space-y-2 border-t dark:border-gray-600 pt-3"
                                      x-data="{ turnoutLikelihood: '{{ $latestResult->turnout_likelihood }}', response: '{{ $latestResult->response }}' }">
                                    @csrf
                                    @method('PUT')
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Home Party</label>
                                        <select name="response" 
                                                required 
                                                x-model="response"
                                                @change="if (response === 'wont_vote') { turnoutLikelihood = 'wont'; $el.form.querySelector('select[name=turnout_likelihood]').value = 'wont'; }"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                                            @foreach($responseOptions as $value => $label)
                                                <option value="{{ $value }}" {{ $latestResult->response === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Turnout Likelihood (optional)</label>
                                        <select name="turnout_likelihood" 
                                                x-model="turnoutLikelihood"
                                                @change="if (turnoutLikelihood === 'wont') { response = 'wont_vote'; $el.form.querySelector('select[name=response]').value = 'wont_vote'; }"
                                                class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                                            <option value="">Not specified</option>
                                            @foreach($turnoutLikelihoodOptions as $value => $label)
                                                <option value="{{ $value }}" {{ $latestResult->turnout_likelihood === $value ? 'selected' : '' }}>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Green Party Support (1=Definitely, 5=Never)</label>
                                        <select name="vote_likelihood" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                                            <option value="">Not specified</option>
                                            @for($i = 1; $i <= 5; $i++)
                                                <option value="{{ $i }}" {{ $latestResult->vote_likelihood == $i ? 'selected' : '' }}>
                                                    {{ $i }}
                                                </option>
                                            @endfor
                                        </select>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Notes</label>
                                        <textarea name="notes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">{{ $latestResult->notes }}</textarea>
                                    </div>

                                    <div class="flex space-x-2">
                                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                                            Update Result
                                        </button>
                                        <button type="button" onclick="toggleEditForm({{ $latestResult->id }})" 
                                                class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-4 py-2 rounded">
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
                            @endif
                            @if($address->latitude !== null)
                                <a href="{{ route('canvassing.map', $address->ward_id) }}?focus={{ $address->id }}"
                                   class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded w-20 text-center text-sm">
                                    Map
                                </a>
                            @endif
                        </div>
                    </div>

                    <form id="form-{{ $address->id }}" 
                          action="{{ route('knock-result.store') }}" 
                          method="POST" 
                          class="mt-4 hidden space-y-3 border-t dark:border-gray-600 pt-4"
                          x-data="{ turnoutLikelihood: '', response: '' }">
                        @csrf
                        <input type="hidden" name="address_id" value="{{ $address->id }}">

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Home Party</label>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($responseOptions as $value => $label)
                                    <label class="flex items-center space-x-2 p-2 border dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer dark:text-gray-200">
                                        <input type="radio" 
                                               name="response" 
                                               value="{{ $value }}" 
                                               required 
                                               x-model="response"
                                               @change="if (response === 'wont_vote') { turnoutLikelihood = 'wont'; document.querySelector('#form-{{ $address->id }} input[name=turnout_likelihood][value=wont]').checked = true; }"
                                               class="text-green-600">
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Turnout Likelihood (optional)</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach($turnoutLikelihoodOptions as $value => $label)
                                    <label class="flex items-center space-x-2 p-2 border dark:border-gray-600 rounded hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer dark:text-gray-200">
                                        <input type="radio" 
                                               name="turnout_likelihood" 
                                               value="{{ $value }}" 
                                               x-model="turnoutLikelihood"
                                               @change="if (turnoutLikelihood === 'wont') { response = 'wont_vote'; document.querySelector('#form-{{ $address->id }} input[name=response][value=wont_vote]').checked = true; }"
                                               class="text-green-600">
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Green Party Support (optional)</label>
                            <div class="flex gap-2 sm:gap-3">
                                @for($i = 5; $i >= 1; $i--)
                                    @php
                                        $bgColor = match($i) {
                                            5 => 'background-color: #ef4444;',
                                            4 => 'background-color: #f97316;',
                                            3 => 'background-color: #eab308;',
                                            2 => 'background-color: #84cc16;',
                                            1 => 'background-color: #22c55e;',
                                        };
                                    @endphp
                                    <label class="flex items-center justify-center rounded-lg hover:opacity-80 cursor-pointer transition-all shadow-sm vote-likelihood-option flex-1" style="max-width: 64px; height: 56px; border-width: 3px; border-color: transparent; {{ $bgColor }}">
                                        <input type="radio" name="vote_likelihood" value="{{ $i }}" class="sr-only" onchange="updateVoteLikelihood(this)">
                                        <span class="text-xl sm:text-2xl font-semibold text-white">{{ $i }}</span>
                                    </label>
                                @endfor
                            </div>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">5 = Never voting Green, 1 = Definitely voting Green</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Notes (optional)</label>
                            <textarea name="notes" rows="2" class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm"></textarea>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 rounded p-3">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                <span class="font-medium">Logged by:</span> {{ auth()->user()->name }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 min-[500px]:flex-row">
                            <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded">
                                Save Result
                            </button>
                            <button type="button" onclick="toggleForm({{ $address->id }})" 
                                    class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-4 py-2 rounded">
                                Cancel
                            </button>
                            @if(!$address->do_not_knock)
                                <button type="button" onclick="if(confirm('Are you sure you want to mark this address as Do Not Knock?')) { document.getElementById('dnk-form-{{ $address->id }}').submit(); }" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded">
                                    Mark as Do Not Knock
                                </button>
                            @endif
                        </div>
                    </form>
                    @if(!$address->do_not_knock)
                        <form id="dnk-form-{{ $address->id }}" action="{{ route('address.mark-do-not-knock', $address) }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    @endif
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

function toggleHistory(addressId, includeLatest = false) {
    const history = document.getElementById(`history-${addressId}`);
    const button = event.target;
    
    if (history.classList.contains('hidden')) {
        history.classList.remove('hidden');
        const count = button.textContent.match(/\d+/)[0];
        button.textContent = 'Hide history';
        
        // Also show the latest result if this is a DNK or never voter
        if (includeLatest) {
            const latestResult = document.querySelector(`.latest-result-${addressId}`);
            if (latestResult) {
                latestResult.classList.remove('hidden');
            }
        }
    } else {
        history.classList.add('hidden');
        const count = history.querySelectorAll('.p-2').length;
        const label = includeLatest ? 'results' : 'previous';
        button.textContent = `Show history (${count} ${label})`;
        
        // Also hide the latest result if this is a DNK or never voter
        if (includeLatest) {
            const latestResult = document.querySelector(`.latest-result-${addressId}`);
            if (latestResult) {
                latestResult.classList.add('hidden');
            }
        }
    }
}

function updateVoteLikelihood(radio) {
    // Get all vote likelihood labels in the same form
    const form = radio.closest('form');
    const labels = form.querySelectorAll('.vote-likelihood-option');
    
    // Reset all labels
    labels.forEach(label => {
        label.style.border = '3px solid transparent';
        label.style.opacity = '0.7';
        const span = label.querySelector('span');
        // Remove checkmark if exists
        const checkmark = label.querySelector('.checkmark');
        if (checkmark) checkmark.remove();
    });
    
    // Highlight selected label
    const selectedLabel = radio.closest('label');
    selectedLabel.style.border = '3px solid #1f2937';
    selectedLabel.style.opacity = '1';
    // Add checkmark
    const checkmark = document.createElement('div');
    checkmark.className = 'checkmark absolute -top-1 -right-1 bg-green-600 rounded-full w-6 h-6 flex items-center justify-center';
    checkmark.innerHTML = '<span style="color: white; font-size: 14px;">✓</span>';
    selectedLabel.style.position = 'relative';
    selectedLabel.appendChild(checkmark);
}

function toggleElection(addressId, electionId, button) {
    // Check if editing is enabled
    if (!window.electionEditingEnabled) {
        return; // Do nothing if editing is disabled
    }
    
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
            const status = data.status;
            const suffix = button.textContent.includes('-GE') ? '-GE' : '';
            const year = button.textContent.match(/\d+/)[0];
            
            if (status === 'voted') {
                button.className = 'text-xs px-2 py-1 rounded bg-green-100 text-green-700 border border-green-300';
                button.innerHTML = `${year}${suffix} ✓`;
                button.dataset.status = 'voted';
            } else if (status === 'not_voted') {
                button.className = 'text-xs px-2 py-1 rounded bg-red-100 text-red-700 border border-red-300';
                button.innerHTML = `${year}${suffix} ✗`;
                button.dataset.status = 'not_voted';
            } else {
                button.className = 'text-xs px-2 py-1 rounded bg-gray-100 text-gray-500 border border-gray-300';
                button.innerHTML = `${year}${suffix} ?`;
                button.dataset.status = 'unknown';
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update election status. Please try again.');
    });
}

// Initialize election editing state
window.electionEditingEnabled = false;

function toggleElectionEditing() {
    const checkbox = document.getElementById('electionEditToggle');
    const lockIcon = document.getElementById('lockIcon');
    const allElectionBadges = document.querySelectorAll('[onclick^="toggleElection"]');
    
    window.electionEditingEnabled = checkbox.checked;
    
    if (checkbox.checked) {
        lockIcon.textContent = '🔓';
        allElectionBadges.forEach(badge => {
            badge.style.cursor = 'pointer';
            badge.style.opacity = '1';
        });
    } else {
        lockIcon.textContent = '🔒';
        allElectionBadges.forEach(badge => {
            badge.style.cursor = 'not-allowed';
            badge.style.opacity = '0.7';
        });
    }
}

// Set initial state on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleElectionEditing();
});

function toggleAddressModal() {
    const modal = document.getElementById('addAddressModal');
    modal.classList.toggle('hidden');
}
</script>

<!-- Add Address Modal -->
<div id="addAddressModal" class="hidden fixed inset-0 bg-gray-600 dark:bg-gray-900 bg-opacity-50 dark:bg-opacity-75 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border border-gray-200 dark:border-gray-700 w-full max-w-2xl shadow-lg rounded-md bg-white dark:bg-gray-800">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900 dark:text-white">Add Missing Address</h3>
            <button onclick="toggleAddressModal()" class="text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 text-2xl">&times;</button>
        </div>

        <form action="{{ route('address.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="ward_id" value="{{ $ward->id }}">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">House Number *</label>
                    <input type="text" 
                           name="house_number" 
                           required
                           placeholder="e.g. 12 or 12a"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Postcode *</label>
                    <input type="text" 
                           name="postcode" 
                           required
                           placeholder="e.g. HX1 3AB"
                           value="{{ $addresses->first()->postcode ?? '' }}"
                           class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm uppercase">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Street Name *</label>
                <input type="text" 
                       name="street_name" 
                       required
                       value="{{ $streetName }}"
                       class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Town *</label>
                <input type="text" 
                       name="town" 
                       required
                       value="{{ $town }}"
                       class="w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded px-3 py-2 text-sm">
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" 
                        onclick="toggleAddressModal()" 
                        class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-4 py-2 rounded">
                    Cancel
                </button>
                <button type="submit" 
                        class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    Add Address
                </button>
            </div>
        </form>
    </div>
</div>
        </div>
    </div>
</x-app-layout>
