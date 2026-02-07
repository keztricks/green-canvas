<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Ward;
use App\Models\UserWardExportSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        $currentUser = auth()->user();
        
        // Only admins and ward admins can access
        if (!$currentUser->isAdmin() && !$currentUser->isWardAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admins see all users, ward admins see only canvassers
        if ($currentUser->isWardAdmin()) {
            $users = User::with('wards')->where('role', User::ROLE_CANVASSER)->orderBy('name')->get();
        } else {
            $users = User::with('wards')->orderBy('name')->get();
        }
        
        // Get current user's ward IDs for filtering actions
        $userWardIds = $currentUser->isAdmin() ? null : $currentUser->wards->pluck('id')->toArray();
        
        return view('users.index', compact('users', 'userWardIds'));
    }

    public function create()
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isWardAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Admins see all wards, ward admins only see their assigned wards
        $wards = $currentUser->isAdmin() 
            ? Ward::orderBy('name')->get()
            : $currentUser->wards()->orderBy('name')->get();
        
        return view('users.create', compact('wards'));
    }

    public function store(Request $request)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isWardAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Ward admins can only create canvassers
        $allowedRoles = $currentUser->isAdmin() 
            ? 'admin,canvasser,ward_admin' 
            : 'canvasser';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:' . $allowedRoles,
            'wards' => 'nullable|array',
            'wards.*' => 'exists:wards,id',
        ]);

        // Ward admins can only assign users to their own wards
        if ($currentUser->isWardAdmin() && $request->has('wards')) {
            $userWardIds = $currentUser->wards->pluck('id')->toArray();
            $invalidWards = array_diff($request->wards, $userWardIds);
            
            if (!empty($invalidWards)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['wards' => 'You can only assign users to wards you manage.']);
            }
        }

        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);
        
        // Sync wards for non-admin users
        if ($request->has('wards')) {
            $user->wards()->sync($request->wards);
        }

        return redirect()->route('users.index')
            ->with('success', 'User created successfully');
    }

    public function edit(User $user)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isWardAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Ward admins can only edit users in their wards
        if ($currentUser->isWardAdmin()) {
            $userWardIds = $currentUser->wards->pluck('id')->toArray();
            $targetUserWardIds = $user->wards->pluck('id')->toArray();
            
            // Check if there's any overlap
            if (empty(array_intersect($userWardIds, $targetUserWardIds)) && !$user->wards->isEmpty()) {
                abort(403, 'You do not have permission to edit this user.');
            }
        }

        // Admins see all wards, ward admins only see their assigned wards
        $wards = $currentUser->isAdmin() 
            ? Ward::orderBy('name')->get()
            : $currentUser->wards()->orderBy('name')->get();
        
        // Get export schedules for the user
        $exportSchedules = UserWardExportSchedule::where('user_id', $user->id)
            ->pluck('frequency', 'ward_id')
            ->toArray();
        
        return view('users.edit', compact('user', 'wards', 'exportSchedules'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();
        
        if (!$currentUser->isAdmin() && !$currentUser->isWardAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Ward admins can only edit users in their wards
        if ($currentUser->isWardAdmin()) {
            $userWardIds = $currentUser->wards->pluck('id')->toArray();
            $targetUserWardIds = $user->wards->pluck('id')->toArray();
            
            // Check if there's any overlap or user has no wards
            if (empty(array_intersect($userWardIds, $targetUserWardIds)) && !$user->wards->isEmpty()) {
                abort(403, 'You do not have permission to edit this user.');
            }
            
            // Ward admins cannot edit admin users
            if ($user->isAdmin()) {
                abort(403, 'You do not have permission to edit admin users.');
            }
        }

        // Ward admins can only set role to canvasser
        $allowedRoles = $currentUser->isAdmin() 
            ? 'admin,canvasser,ward_admin' 
            : 'canvasser';

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => 'required|in:' . $allowedRoles,
            'wards' => 'nullable|array',
            'wards.*' => 'exists:wards,id',
            'export_schedules' => 'nullable|array',
            'export_schedules.*' => 'in:none,daily,weekly',
        ]);

        // Ward admins can only assign users to their own wards
        if ($currentUser->isWardAdmin() && $request->has('wards')) {
            $userWardIds = $currentUser->wards->pluck('id')->toArray();
            $invalidWards = array_diff($request->wards, $userWardIds);
            
            if (!empty($invalidWards)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['wards' => 'You can only assign users to wards you manage.']);
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        
        // Sync wards
        if ($request->has('wards')) {
            if ($currentUser->isWardAdmin()) {
                // Ward admins: only modify their own wards, keep other wards intact
                $currentUserWardIds = $currentUser->wards->pluck('id')->toArray();
                $existingOtherWards = $user->wards()->whereNotIn('ward_id', $currentUserWardIds)->pluck('ward_id')->toArray();
                $newWards = array_merge($existingOtherWards, $request->wards);
                $user->wards()->sync($newWards);
            } else {
                // Admins: full control
                $user->wards()->sync($request->wards);
            }
        } elseif ($currentUser->isAdmin()) {
            // Only admins can remove all wards
            $user->wards()->sync([]);
        }
        
        // Update export schedules
        if ($request->has('export_schedules')) {
            foreach ($request->export_schedules as $wardId => $frequency) {
                // Only update schedules for wards the user is assigned to
                if (in_array($wardId, $user->wards->pluck('id')->toArray())) {
                    UserWardExportSchedule::updateOrCreate(
                        ['user_id' => $user->id, 'ward_id' => $wardId],
                        ['frequency' => $frequency]
                    );
                }
            }
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();
        
        // Only admins can delete users
        if (!$currentUser->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')
                ->with('error', 'You cannot delete your own account');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully');
    }
}
