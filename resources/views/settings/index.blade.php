<x-app-layout>
    <div class="py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            
            @if(session('success'))
                <div class="p-4 bg-green-100 border border-green-400 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Profile Information -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Profile Information</h2>
                <p class="text-sm text-gray-600 mb-4">Update your account's profile information and email address.</p>

                <form action="{{ route('settings.profile.update') }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-4">
                        <div>
                            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Name</label>
                            <input type="text" 
                                   name="name" 
                                   id="name" 
                                   value="{{ old('name', $user->name) }}"
                                   required
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('name') border-red-500 @enderror">
                            @error('name')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                            <input type="email" 
                                   name="email" 
                                   id="email" 
                                   value="{{ old('email', $user->email) }}"
                                   required
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('email') border-red-500 @enderror">
                            @error('email')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Update Password -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Update Password</h2>
                <p class="text-sm text-gray-600 mb-4">Ensure your account is using a long, random password to stay secure.</p>

                <form action="{{ route('settings.password.update') }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-sm font-semibold text-gray-700 mb-2">Current Password</label>
                            <input type="password" 
                                   name="current_password" 
                                   id="current_password"
                                   required
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('current_password') border-red-500 @enderror">
                            @error('current_password')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                            <input type="password" 
                                   name="password" 
                                   id="password"
                                   required
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('password') border-red-500 @enderror">
                            @error('password')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirm Password</label>
                            <input type="password" 
                                   name="password_confirmation" 
                                   id="password_confirmation"
                                   required
                                   class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                        </div>

                        <div class="flex gap-3 pt-2">
                            <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Export Schedule Settings -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Export Email Schedule</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Configure automatic export emails for your assigned wards. You will receive a CSV export at the selected frequency.
                </p>

                @if($wards->isEmpty())
                    <div class="text-center py-8 bg-gray-50 rounded">
                        <p class="text-gray-600">
                            @if(auth()->user()->isAdmin())
                                No wards available yet.
                            @else
                                You have not been assigned to any wards yet. Contact your administrator.
                            @endif
                        </p>
                    </div>
                @else
                    <form action="{{ route('settings.export-schedules.update') }}" method="POST">
                        @csrf

                        <div class="space-y-3">
                            @foreach($wards as $ward)
                                @php
                                    $currentSchedule = $schedules->get($ward->id);
                                    $currentFrequency = $currentSchedule ? $currentSchedule->frequency : 'none';
                                @endphp
                                
                                <div class="flex items-center justify-between p-4 border border-gray-200 rounded hover:bg-gray-50">
                                    <div class="flex-1">
                                        <h4 class="font-medium text-gray-800">{{ $ward->name }}</h4>
                                    </div>
                                    <div class="flex gap-2">
                                        <label class="inline-flex items-center px-4 py-2 border rounded cursor-pointer transition-colors
                                            {{ $currentFrequency === 'none' ? 'bg-[#6AB023] text-white border-[#6AB023]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                            <input type="radio" 
                                                   name="schedules[{{ $ward->id }}]" 
                                                   value="none" 
                                                   {{ $currentFrequency === 'none' ? 'checked' : '' }}
                                                   class="sr-only">
                                            <span class="text-sm font-medium">None</span>
                                        </label>
                                        <label class="inline-flex items-center px-4 py-2 border rounded cursor-pointer transition-colors
                                            {{ $currentFrequency === 'daily' ? 'bg-[#6AB023] text-white border-[#6AB023]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                            <input type="radio" 
                                                   name="schedules[{{ $ward->id }}]" 
                                                   value="daily" 
                                                   {{ $currentFrequency === 'daily' ? 'checked' : '' }}
                                                   class="sr-only">
                                            <span class="text-sm font-medium">Daily</span>
                                        </label>
                                        <label class="inline-flex items-center px-4 py-2 border rounded cursor-pointer transition-colors
                                            {{ $currentFrequency === 'weekly' ? 'bg-[#6AB023] text-white border-[#6AB023]' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50' }}">
                                            <input type="radio" 
                                                   name="schedules[{{ $ward->id }}]" 
                                                   value="weekly" 
                                                   {{ $currentFrequency === 'weekly' ? 'checked' : '' }}
                                                   class="sr-only">
                                            <span class="text-sm font-medium">Weekly</span>
                                        </label>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex gap-3">
                            <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                                Save Preferences
                            </button>
                        </div>
                    </form>
                @endif
            </div>

            <!-- Delete Account -->
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-2xl font-bold mb-4 text-gray-800">Delete Account</h2>
                <p class="text-sm text-gray-600 mb-4">
                    Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.
                </p>

                <button type="button" 
                        onclick="document.getElementById('deleteAccountModal').classList.remove('hidden')"
                        class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded font-medium">
                    Delete Account
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    <div id="deleteAccountModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Are you sure you want to delete your account?</h3>
                <p class="text-sm text-gray-600 mb-4">
                    Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.
                </p>

                <form action="{{ route('settings.destroy') }}" method="POST">
                    @csrf
                    @method('DELETE')

                    <div class="mb-4">
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input type="password" 
                               name="password" 
                               id="delete_password"
                               required
                               class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-red-500 @error('password', 'userDeletion') border-red-500 @enderror">
                        @error('password', 'userDeletion')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex gap-3 justify-end">
                        <button type="button" 
                                onclick="document.getElementById('deleteAccountModal').classList.add('hidden')"
                                class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded font-medium">
                            Cancel
                        </button>
                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded font-medium">
                            Delete Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
// Handle radio button styling
document.querySelectorAll('input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function() {
        // Get all labels in the same group
        const name = this.name;
        const labels = document.querySelectorAll(`input[name="${name}"]`).forEach(r => {
            const label = r.closest('label');
            if (r.checked) {
                label.classList.remove('bg-white', 'text-gray-700', 'border-gray-300', 'hover:bg-gray-50');
                label.classList.add('bg-[#6AB023]', 'text-white', 'border-[#6AB023]');
            } else {
                label.classList.remove('bg-[#6AB023]', 'text-white', 'border-[#6AB023]');
                label.classList.add('bg-white', 'text-gray-700', 'border-gray-300', 'hover:bg-gray-50');
            }
        });
    });
});

// Show modal if there are validation errors
@if($errors->userDeletion->any())
    document.getElementById('deleteAccountModal').classList.remove('hidden');
@endif
</script>
