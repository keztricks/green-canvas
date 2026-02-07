<x-app-layout>
    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-3xl font-bold text-gray-800 dark:text-white">User Management</h2>
                    <a href="{{ route('users.create') }}" class="bg-[#6AB023] hover:bg-[#5a9620] text-white px-6 py-2 rounded font-medium">
                        Add New User
                    </a>
                </div>

                @if(session('success'))
                    <div class="bg-green-50 dark:bg-green-900 border border-[#6AB023] dark:border-green-700 text-green-800 dark:text-green-300 px-4 py-3 rounded mb-6">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-50 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-300 px-4 py-3 rounded mb-6">
                        {{ session('error') }}
                    </div>
                @endif

                @if($users->isEmpty())
                    <div class="text-center py-8 text-gray-600 dark:text-gray-300">
                        <p>No users found.</p>
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead>
                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Name</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Email</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Role</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Assigned Wards</th>
                                    <th class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-200">Last Login</th>
                                    <th class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-200">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($users as $user)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-4">
                                            <div class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="text-gray-600 dark:text-gray-300">{{ $user->email }}</div>
                                        </td>
                                        <td class="px-4 py-4">
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full 
                                                {{ $user->role === 'admin' ? 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300' : ($user->role === 'ward_admin' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300') }}">
                                                {{ ucfirst(str_replace('_', ' ', $user->role)) }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300 text-sm">
                                            @if($user->role === 'admin')
                                                <span class="text-gray-400 dark:text-gray-500 italic">All wards</span>
                                            @elseif($user->wards->count() > 0)
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach($user->wards as $ward)
                                                        <span class="px-2 py-0.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded text-xs">{{ $ward->name }}</span>
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="text-red-500 dark:text-red-400 text-xs">No wards assigned</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300 text-sm">
                                            @if($user->last_login_at)
                                                <span title="{{ $user->last_login_at->format('d M Y H:i:s') }}">
                                                    {{ $user->last_login_at->diffForHumans() }}
                                                </span>
                                            @else
                                                <span class="text-gray-400 dark:text-gray-500">Never</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-4">
                                            <div class="flex justify-end items-center gap-3">
                                                @php
                                                    // Determine if current user can edit this user
                                                    $canEdit = auth()->user()->isAdmin() || 
                                                               (auth()->user()->isWardAdmin() && 
                                                                !$user->isAdmin() && 
                                                                (!empty(array_intersect($userWardIds ?? [], $user->wards->pluck('id')->toArray())) || $user->wards->isEmpty()));
                                                    
                                                    // Only admins can delete users
                                                    $canDelete = auth()->user()->isAdmin() && $user->id !== auth()->id();
                                                @endphp
                                                
                                                @if($canEdit)
                                                    <a href="{{ route('users.edit', $user) }}" 
                                                       class="text-[#6AB023] hover:text-[#5a9620] font-medium text-sm">
                                                        Edit
                                                    </a>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-sm">-</span>
                                                @endif
                                                
                                                @if($canDelete)
                                                    <form action="{{ route('users.destroy', $user) }}" method="POST" class="flex" 
                                                          onsubmit="return confirm('Are you sure you want to delete {{ $user->name }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300 font-medium text-sm p-0 bg-transparent border-0">
                                                            Delete
                                                        </button>
                                                    </form>
                                                @elseif($user->id === auth()->id())
                                                    <span class="text-gray-400 dark:text-gray-500 text-sm">(You)</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
