@php
    $allResponseOptions = \App\Models\KnockResult::responseOptions();
    $activeResponses = $selectedResponseFilters ?? [];
    $activeLikelihoods = $selectedLikelihoodFilters ?? [];
    $likelihoodLabels = [1 => 'Definitely (1)', 2 => 'Likely (2)', 3 => 'Maybe (3)', 4 => 'Unlikely (4)', 5 => 'Never (5)'];
    $hasActiveKnockFilters = !empty($activeResponses) || !empty($activeLikelihoods);
@endphp

<div x-data="{
    open: {{ $hasActiveKnockFilters ? 'true' : 'false' }},
    responses: {{ json_encode(array_values($activeResponses)) }},
    likelihoods: {{ json_encode(array_map('intval', array_values($activeLikelihoods))) }},
    toggleResponse(val) {
        const i = this.responses.indexOf(val);
        i > -1 ? this.responses.splice(i, 1) : this.responses.push(val);
    },
    toggleLikelihood(val) {
        const i = this.likelihoods.indexOf(val);
        i > -1 ? this.likelihoods.splice(i, 1) : this.likelihoods.push(val);
    },
    activeCount() {
        return (this.responses.length > 0 ? 1 : 0) + (this.likelihoods.length > 0 ? 1 : 0);
    }
}" class="mb-4 p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg">

    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Filter by Knock Result</h3>
        <button @click="open = !open" type="button" class="text-sm text-[#6AB023] hover:text-[#5a9620] font-medium">
            <span x-show="!open">Show Filters</span>
            <span x-show="open">Hide Filters</span>
        </button>
    </div>

    <div x-show="open" x-collapse>
        <form method="GET" class="space-y-4">
            @if(request()->has('search'))
                <input type="hidden" name="search" value="{{ request('search') }}">
            @endif
            @if(!empty($selectedElectionFilters))
                @foreach($selectedElectionFilters as $electionId => $statuses)
                    @foreach($statuses as $status)
                        <input type="hidden" name="election_filters[{{ $electionId }}][]" value="{{ $status }}">
                    @endforeach
                @endforeach
            @endif

            <!-- Response type -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">Response</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($allResponseOptions as $value => $label)
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                name="response_filters[]"
                                value="{{ $value }}"
                                @click="toggleResponse('{{ $value }}')"
                                x-bind:checked="responses.includes('{{ $value }}')"
                                class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 dark:bg-gray-700"
                            >
                            <span class="ml-1.5 text-xs text-gray-700 dark:text-gray-200">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Vote likelihood -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                    Vote Likelihood
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">(1 = Definitely Green, 5 = Never)</span>
                </label>
                <div class="flex flex-wrap gap-3">
                    @foreach($likelihoodLabels as $num => $likelihoodLabel)
                        <label class="flex items-center cursor-pointer">
                            <input
                                type="checkbox"
                                name="likelihood_filters[]"
                                value="{{ $num }}"
                                @click="toggleLikelihood({{ $num }})"
                                x-bind:checked="likelihoods.includes({{ $num }})"
                                class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 dark:bg-gray-700"
                            >
                            <span class="ml-1.5 text-xs text-gray-700 dark:text-gray-200">{{ $likelihoodLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="text-xs text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 p-2 rounded">
                <strong>Note:</strong> Filters apply to each address's <strong>latest knock result</strong>. Both filters must match when both are set.
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded text-sm font-medium">
                    Apply Filters
                </button>
                <a href="{{ url()->current() }}{{ request()->has('search') ? '?search=' . urlencode(request('search')) : '' }}"
                   class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium">
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Active badge -->
    <div x-show="!open && activeCount() > 0" class="mt-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#6AB023] text-white">
            <span x-text="activeCount() + (activeCount() === 1 ? ' knock filter active' : ' knock filters active')"></span>
        </span>
    </div>
</div>
