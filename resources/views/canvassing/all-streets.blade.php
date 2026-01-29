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
            <h2 class="text-3xl font-bold mb-2 text-gray-800">All Streets</h2>
            <p class="text-gray-600">All addresses in this ward</p>
        </div>

        <!-- Add Address Button -->
        <div class="mb-4 flex justify-end">
            <button onclick="toggleAddressModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded flex items-center gap-2">
                <span>+</span> Add Missing Address
            </button>
        </div>

        <!-- Search/Filter Input and Election Toggle -->
        <div class="mb-4 space-y-3">
            <div>
                <input type="text" 
                       id="addressSearch"
                       value="{{ old('search', request('search')) }}"
                       placeholder="Search all addresses by street or house number..." 
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#6AB023] focus:border-transparent">
                <p id="searchStatus" class="text-xs text-gray-500 mt-1">Showing {{ $addresses->count() }} of {{ $addresses->total() }} addresses</p>
            </div>
            
            @if(auth()->user()->isAdmin())
            <label class="flex items-center space-x-2 cursor-pointer p-3 bg-gray-50 rounded-lg border border-gray-300 hover:bg-gray-100 transition-colors">
                <input type="checkbox" id="electionEditToggle" class="w-4 h-4 text-[#6AB023] rounded" onchange="toggleElectionEditing()">
                <span class="text-sm font-medium text-gray-700">
                    <span id="lockIcon">🔒</span> Enable election editing
                </span>
                <span class="text-xs text-gray-500 ml-auto">Click to toggle</span>
            </label>
            @endif
        </div>

        <div id="addressesList" class="space-y-4">
            @foreach($addresses as $address)
                @include('canvassing.partials.address-item', ['address' => $address, 'responseOptions' => $responseOptions, 'elections' => $elections])
            @endforeach
        </div>

        <!-- Load More Button -->
        @if($addresses->hasMorePages())
        <div class="mt-6 text-center">
            <button id="loadMoreBtn" onclick="loadMoreAddresses()" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-3 rounded-lg">
                Load More Addresses
            </button>
            <p class="text-sm text-gray-500 mt-2">Loaded {{ $addresses->count() }} of {{ $addresses->total() }} addresses</p>
        </div>
        @endif
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
        
        if (includeLatest) {
            const latestResult = document.querySelector(`.latest-result-${addressId}`);
            if (latestResult) {
                latestResult.classList.add('hidden');
            }
        }
    }
}

function updateVoteLikelihood(radio) {
    const form = radio.closest('form');
    const labels = form.querySelectorAll('.vote-likelihood-option');
    
    labels.forEach(label => {
        label.style.border = '3px solid transparent';
        label.style.opacity = '0.7';
        const checkmark = label.querySelector('.checkmark');
        if (checkmark) checkmark.remove();
    });
    
    const selectedLabel = radio.closest('label');
    selectedLabel.style.border = '3px solid #1f2937';
    selectedLabel.style.opacity = '1';
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

// Pagination state
let currentPage = {{ $addresses->currentPage() }};
let isSearching = false;
let searchTimeout = null;

// Load more addresses
function loadMoreAddresses() {
    const btn = document.getElementById('loadMoreBtn');
    if (!btn) return;
    
    btn.disabled = true;
    btn.textContent = 'Loading...';
    
    currentPage++;
    
    fetch(`/ward/{{ $ward->id }}/all-streets?page=${currentPage}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        appendAddresses(data.addresses);
        
        if (!data.hasMore) {
            btn.parentElement.remove();
        } else {
            btn.disabled = false;
            btn.textContent = 'Load More Addresses';
            btn.nextElementSibling.textContent = `Loaded ${document.querySelectorAll('.address-item').length} of ${data.total} addresses`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        btn.disabled = false;
        btn.textContent = 'Load More Addresses';
        alert('Failed to load more addresses');
    });
}

// Search functionality with server-side search
document.getElementById('addressSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.trim();
    
    clearTimeout(searchTimeout);
    
    if (searchTerm === '') {
        // Reload page to show all addresses
        window.location.href = '{{ route('canvassing.all-streets', $ward) }}';
        return;
    }
    
    isSearching = true;
    document.getElementById('searchStatus').textContent = 'Searching...';
    
    // Debounce search
    searchTimeout = setTimeout(() => {
        performSearch(searchTerm);
    }, 500);
});

function performSearch(searchTerm) {
    fetch(`/ward/{{ $ward->id }}/all-streets?search=${encodeURIComponent(searchTerm)}`, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Clear existing addresses
        document.getElementById('addressesList').innerHTML = '';
        
        // Hide load more button during search
        const loadMoreSection = document.getElementById('loadMoreBtn')?.parentElement;
        if (loadMoreSection) {
            loadMoreSection.style.display = 'none';
        }
        
        if (data.addresses.length === 0) {
            document.getElementById('addressesList').innerHTML = `
                <div class="text-center py-12 text-gray-500">
                    <p>No addresses found matching "${searchTerm}"</p>
                </div>
            `;
            document.getElementById('searchStatus').textContent = 'No results found';
        } else {
            appendAddresses(data.addresses);
            document.getElementById('searchStatus').textContent = `Found ${data.total} matching address${data.total !== 1 ? 'es' : ''}`;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('searchStatus').textContent = 'Search failed';
    });
}

function appendAddresses(addressesHtml) {
    const container = document.getElementById('addressesList');
    
    addressesHtml.forEach(html => {
        container.insertAdjacentHTML('beforeend', html);
    });
    
    // Re-initialize election editing state for new addresses
    if (window.electionEditingEnabled) {
        const newBadges = container.querySelectorAll('[onclick^="toggleElection"]');
        newBadges.forEach(badge => {
            badge.style.cursor = 'pointer';
            badge.style.opacity = '1';
        });
    }
}

function createAddressElement(address, responseOptions, elections) {
    // No longer needed - server returns rendered HTML
    return '';
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
    
    // Trigger search if there's a value in the search box
    const searchInput = document.getElementById('addressSearch');
    if (searchInput.value.trim()) {
        performSearch(searchInput.value);
    }
});

function toggleAddressModal() {
    const modal = document.getElementById('addAddressModal');
    modal.classList.toggle('hidden');
}
</script>

<!-- Add Address Modal -->
<div id="addAddressModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-2xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-900">Add Missing Address</h3>
            <button onclick="toggleAddressModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>

        <form action="{{ route('address.store') }}" method="POST" class="space-y-4">
            @csrf
            <input type="hidden" name="ward_id" value="{{ $ward->id }}">

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">House Number *</label>
                    <input type="text" 
                           name="house_number" 
                           required
                           placeholder="e.g. 12 or 12a"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Postcode *</label>
                    <input type="text" 
                           name="postcode" 
                           required
                           placeholder="e.g. HX1 3AB"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm uppercase">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Street Name *</label>
                <input type="text" 
                       name="street_name" 
                       required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Town *</label>
                <input type="text" 
                       name="town" 
                       required
                       value="Halifax"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>

            <div class="flex justify-end gap-3 pt-4">
                <button type="button" 
                        onclick="toggleAddressModal()" 
                        class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded">
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