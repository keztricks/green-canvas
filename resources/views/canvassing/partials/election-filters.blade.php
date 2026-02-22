@php
    // Build default filters with nothing selected
    $defaultFilters = [];
    
    // Use selected filters if present, otherwise use defaults (empty)
    $activeFilters = !empty($selectedElectionFilters) ? $selectedElectionFilters : $defaultFilters;
@endphp

<div x-data="{ 
    open: false,
    electionFilters: {{ json_encode(old('election_filters', $activeFilters)) }},
    
    toggleStatus(electionId, status) {
        if (!this.electionFilters[electionId]) {
            this.electionFilters[electionId] = [];
        }
        const index = this.electionFilters[electionId].indexOf(status);
        if (index > -1) {
            this.electionFilters[electionId].splice(index, 1);
            if (this.electionFilters[electionId].length === 0) {
                delete this.electionFilters[electionId];
            }
        } else {
            this.electionFilters[electionId].push(status);
        }
    },
    
    hasStatus(electionId, status) {
        return this.electionFilters[electionId] && this.electionFilters[electionId].includes(status);
    },
    
    hasActiveFilters() {
        return Object.keys(this.electionFilters).length > 0;
    },
    
    getActiveFiltersCount() {
        return Object.keys(this.electionFilters).length;
    }
}" class="mb-4 p-4 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg">
    
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-200">Filter by Election Voting History</h3>
        <button @click="open = !open" type="button" class="text-sm text-[#6AB023] hover:text-[#5a9620] font-medium">
            <span x-show="!open">Show Filters</span>
            <span x-show="open">Hide Filters</span>
        </button>
    </div>

    <div x-show="open" x-collapse>
        <form method="GET" class="space-y-4">
            <!-- Preserve search parameter if it exists -->
            @if(request()->has('search'))
                <input type="hidden" name="search" value="{{ request('search') }}">
            @endif

            <!-- Elections with Per-Election Status Selection -->
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-3">
                    Select Elections and Voting Status
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-normal block mt-1">
                        Check statuses for each election you want to filter by
                    </span>
                </label>
                <div class="space-y-3 max-h-96 overflow-y-auto border border-gray-200 dark:border-gray-600 rounded p-3 bg-gray-50 dark:bg-gray-700">
                    @forelse($elections as $election)
                        <div class="bg-white dark:bg-gray-800 p-3 rounded border border-gray-200 dark:border-gray-600">
                            <div class="font-medium text-sm text-gray-800 dark:text-gray-200 mb-2">
                                {{ $election->name }}
                                <span class="text-xs text-gray-500 dark:text-gray-400 font-normal">
                                    ({{ $election->election_date->format('d M Y') }})
                                </span>
                            </div>
                            <div class="flex flex-wrap gap-3 ml-2">
                                <label class="flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="election_filters[{{ $election->id }}][]" 
                                        value="voted"
                                        @click="toggleStatus({{ $election->id }}, 'voted')"
                                        x-bind:checked="hasStatus({{ $election->id }}, 'voted')"
                                        class="rounded border-gray-300 dark:border-gray-600 text-green-600 focus:ring-green-500 dark:bg-gray-700"
                                    >
                                    <span class="ml-2 text-xs text-gray-700 dark:text-gray-200">Voted ✓</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="election_filters[{{ $election->id }}][]" 
                                        value="not_voted"
                                        @click="toggleStatus({{ $election->id }}, 'not_voted')"
                                        x-bind:checked="hasStatus({{ $election->id }}, 'not_voted')"
                                        class="rounded border-gray-300 dark:border-gray-600 text-red-600 focus:ring-red-500 dark:bg-gray-700"
                                    >
                                    <span class="ml-2 text-xs text-gray-700 dark:text-gray-200">Not Voted ✗</span>
                                </label>
                                <label class="flex items-center cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        name="election_filters[{{ $election->id }}][]" 
                                        value="unknown"
                                        @click="toggleStatus({{ $election->id }}, 'unknown')"
                                        x-bind:checked="hasStatus({{ $election->id }}, 'unknown')"
                                        class="rounded border-gray-300 dark:border-gray-600 text-gray-600 focus:ring-gray-500 dark:bg-gray-700"
                                    >
                                    <span class="ml-2 text-xs text-gray-700 dark:text-gray-200">Unknown ?</span>
                                </label>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">No elections available</p>
                    @endforelse
                </div>
            </div>

            <!-- Help Text -->
            <div class="text-xs text-gray-600 dark:text-gray-400 bg-blue-50 dark:bg-blue-900/20 p-2 rounded">
                <strong>Note:</strong> Addresses must match <strong>AT LEAST ONE</strong> selected election. For each election, the address must have at least one of the selected statuses.
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-2 pt-2">
                <button 
                    type="submit" 
                    class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-4 py-2 rounded text-sm font-medium"
                >
                    Apply Filters
                </button>
                <a 
                    href="{{ url()->current() }}" 
                    class="bg-gray-300 dark:bg-gray-600 hover:bg-gray-400 dark:hover:bg-gray-500 text-gray-800 dark:text-white px-4 py-2 rounded text-sm font-medium"
                >
                    Clear Filters
                </a>
            </div>
        </form>
    </div>

    <!-- Active Filters Badge -->
    <div x-show="!open && hasActiveFilters()" class="mt-2">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-[#6AB023] text-white">
            <span x-text="getActiveFiltersCount() + (getActiveFiltersCount() === 1 ? ' election filtered' : ' elections filtered')"></span>
        </span>
    </div>
</div>
