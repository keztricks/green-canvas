@php
    $allResults = $address->knockResults;
    $latestResult = $allResults->first();
    $hasResult = $latestResult !== null;
    $hasHistory = $allResults->count() > 1;
    $isNeverVoter = $latestResult && $latestResult->vote_likelihood == 5;
@endphp

<div id="address-{{ $address->id }}" 
     class="address-item border rounded-lg p-4 {{ ($address->do_not_knock || $isNeverVoter) ? 'bg-red-50 border-red-500 border-2' : ($hasResult ? 'bg-gray-50 border-gray-300' : 'bg-white border-gray-200') }}"
     data-street="{{ strtolower($address->street_name) }}"
     data-house="{{ strtolower($address->house_number) }}">
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
    @elseif($isNeverVoter)
        <div class="mb-3 p-3 bg-red-100 border-l-4 border-red-500 rounded">
            <p class="font-bold text-red-800 text-sm">⚠️ NEVER VOTING GREEN</p>
            <p class="text-xs text-red-700 mt-1">Vote likelihood: 5 (Never) - Recorded {{ $latestResult->knocked_at->diffForHumans() }}</p>
        </div>
    @endif

    <div class="flex justify-between items-start">
        <div class="flex-1">
            <h3 class="text-lg font-semibold {{ ($address->do_not_knock || $isNeverVoter) ? 'text-red-800' : 'text-gray-800' }}">
                {{ $address->house_number }} {{ $address->street_name }}
            </h3>
            <p class="text-sm text-gray-600">
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
                <div class="mt-2 p-3 bg-white rounded border-l-4 {{ ($address->do_not_knock || $isNeverVoter) ? 'hidden' : '' }} latest-result-{{ $address->id }}
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
                                    Green support: <span class="font-semibold">{{ $latestResult->vote_likelihood }}/5</span>
                                    @if($latestResult->vote_likelihood == 1)
                                        <span class="text-xs text-green-600">(Definitely)</span>
                                    @elseif($latestResult->vote_likelihood == 5)
                                        <span class="text-xs text-red-600">(Never)</span>
                                    @endif
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
                        <button onclick="toggleHistory({{ $address->id }}, {{ ($address->do_not_knock || $isNeverVoter) ? 'true' : 'false' }})" 
                                class="text-sm text-gray-600 hover:text-gray-800 underline">
                            Show history ({{ ($address->do_not_knock || $isNeverVoter) ? $allResults->count() : $allResults->count() - 1 }} {{ ($address->do_not_knock || $isNeverVoter) ? 'results' : 'previous' }})
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
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Edit form for latest result -->
                <form id="edit-form-{{ $latestResult->id }}" 
                      action="{{ route('knock-result.update', $latestResult) }}" 
                      method="POST" 
                      class="mt-4 hidden space-y-3 border-t pt-4"
                      x-data="{ turnoutLikelihood: '{{ $latestResult->turnout_likelihood }}', response: '{{ $latestResult->response }}' }">
                    @csrf
                    @method('PUT')
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Home Party</label>
                        <select name="response" 
                                required 
                                x-model="response"
                                @change="if (response === 'wont_vote') { turnoutLikelihood = 'wont'; $el.form.querySelector('select[name=turnout_likelihood]').value = 'wont'; }"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                            @foreach($responseOptions as $value => $label)
                                <option value="{{ $value }}" {{ $latestResult->response === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Turnout Likelihood</label>
                        <select name="turnout_likelihood" 
                                x-model="turnoutLikelihood"
                                @change="if (turnoutLikelihood === 'wont') { response = 'wont_vote'; $el.form.querySelector('select[name=response]').value = 'wont_vote'; }"
                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                            <option value="">Not specified</option>
                            @foreach($turnoutLikelihoodOptions as $value => $label)
                                <option value="{{ $value }}" {{ $latestResult->turnout_likelihood === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Green Party Support (1=Definitely, 5=Never)</label>
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
            @endif
        </div>
    </div>

    <form id="form-{{ $address->id }}" 
          action="{{ route('knock-result.store') }}" 
          method="POST" 
          class="mt-4 hidden space-y-3 border-t pt-4"
          x-data="{ turnoutLikelihood: '', response: '' }">
        @csrf
        <input type="hidden" name="address_id" value="{{ $address->id }}">

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Home Party</label>
            <div class="grid grid-cols-2 gap-2">
                @foreach($responseOptions as $value => $label)
                    <label class="flex items-center space-x-2 p-2 border rounded hover:bg-gray-50 cursor-pointer">
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
            <label class="block text-sm font-medium text-gray-700 mb-2">Turnout Likelihood</label>
            <div class="grid grid-cols-3 gap-2">
                @foreach($turnoutLikelihoodOptions as $value => $label)
                    <label class="flex items-center space-x-2 p-2 border rounded hover:bg-gray-50 cursor-pointer">
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
            <label class="block text-sm font-medium text-gray-700 mb-2">Green Party Support (optional)</label>
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
            <p class="text-xs text-gray-500 mt-1">5 = Never voting Green, 1 = Definitely voting Green</p>
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

        <div class="flex flex-col gap-2 min-[500px]:flex-row">
            <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded">
                Save Result
            </button>
            <button type="button" onclick="toggleForm({{ $address->id }})" 
                    class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
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
