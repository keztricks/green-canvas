<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index()
    {
        // Only admins can access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $users = User::with('wards')->orderBy('name')->get();
        return view('users.index', compact('users'));
    }

    public function create()
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $wards = Ward::orderBy('name')->get();
        return view('users.create', compact('wards'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,canvasser,ward_admin',
            'wards' => 'nullable|array',
            'wards.*' => 'exists:wards,id',
        ]);

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
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $wards = Ward::orderBy('name')->get();
        return view('users.edit', compact('user', 'wards'));
    }

    public function update(Request $request, User $user)
    {
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role' => 'required|in:admin,canvasser,ward_admin',
            'wards' => 'nullable|array',
            'wards.*' => 'exists:wards,id',
        ]);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);
        
        // Sync wards for non-admin users
        if ($request->has('wards')) {
            $user->wards()->sync($request->wards);
        } else {
            $user->wards()->sync([]);
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully');
    }

    public function destroy(User $user)
    {
        if (!auth()->user()->isAdmin()) {
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
