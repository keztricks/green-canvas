<x-app-layout>
    <div class="py-6">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('users.index') }}" class="text-[#6AB023] hover:text-[#5a9620] font-medium">
                    ← Back to Users
                </a>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-3xl font-bold text-gray-800 mb-6">Edit User</h2>

                <form method="POST" action="{{ route('users.update', $user) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Name</label>
                        <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                               class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email</label>
                        <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" required
                               class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('email') border-red-500 @enderror">
                        @error('email')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="role" class="block text-sm font-semibold text-gray-700 mb-2">Role</label>
                        <select name="role" id="role" required
                                class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('role') border-red-500 @enderror">
                            <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="ward_admin" {{ old('role', $user->role) === 'ward_admin' ? 'selected' : '' }}>Ward Admin</option>
                            <option value="canvasser" {{ old('role', $user->role) === 'canvasser' ? 'selected' : '' }}>Canvasser</option>
                        </select>
                        @error('role')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div id="wardsSection" class="{{ in_array($user->role, ['ward_admin', 'canvasser']) ? '' : 'hidden' }}">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Assigned Wards</label>
                        <p class="text-xs text-gray-600 mb-2">Select the wards this user can access (Ward Admins and Canvassers only)</p>
                        <div class="border border-gray-300 rounded p-3 max-h-60 overflow-y-auto space-y-2">
                            @foreach($wards as $ward)
                                <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                    <input type="checkbox" name="wards[]" value="{{ $ward->id }}" 
                                           {{ in_array($ward->id, old('wards', $user->wards->pluck('id')->toArray())) ? 'checked' : '' }}
                                           class="rounded text-[#6AB023] focus:ring-[#6AB023]">
                                    <span class="text-sm text-gray-700">{{ $ward->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('wards')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="border-t border-gray-200 pt-6">
                        <p class="text-sm font-semibold text-gray-700 mb-4">Change Password (optional)</p>
                        
                        <div class="space-y-4">
                            <div>
                                <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">New Password</label>
                                <input type="password" name="password" id="password"
                                       class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023] @error('password') border-red-500 @enderror">
                                <p class="text-xs text-gray-600 mt-1">Leave blank to keep current password. Minimum 8 characters, mixed case, and numbers required</p>
                                @error('password')
                                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirm New Password</label>
                                <input type="password" name="password_confirmation" id="password_confirmation"
                                       class="w-full border border-gray-300 rounded px-4 py-2 focus:outline-none focus:ring-2 focus:ring-[#6AB023]">
                            </div>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                            Update User
                        </button>
                        <a href="{{ route('users.index') }}" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-6 py-2 rounded font-medium">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
// Show/hide wards section based on role
document.getElementById('role').addEventListener('change', function() {
    const wardsSection = document.getElementById('wardsSection');
    if (this.value === 'ward_admin' || this.value === 'canvasser') {
        wardsSection.classList.remove('hidden');
    } else {
        wardsSection.classList.add('hidden');
    }
});
</script>
