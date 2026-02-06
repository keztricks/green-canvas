<?php

namespace App\Http\Controllers;

use App\Models\UserWardExportSchedule;
use App\Models\Ward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Get wards based on user role
        if ($user->isAdmin()) {
            $wards = Ward::orderBy('name')->get();
        } else {
            $wards = $user->wards()->orderBy('name')->get();
        }
        
        // Get existing schedules
        $schedules = $user->exportSchedules()
            ->with('ward')
            ->get()
            ->keyBy('ward_id');
        
        return view('settings.index', compact('user', 'wards', 'schedules'));
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return redirect()->route('settings.index')
            ->with('success', 'Profile updated successfully');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->route('settings.index')
            ->with('success', 'Password updated successfully');
    }

    public function updateExportSchedules(Request $request)
    {
        $user = auth()->user();
        
        $validated = $request->validate([
            'schedules' => 'required|array',
            'schedules.*' => 'required|in:none,daily,weekly',
        ]);
        
        foreach ($validated['schedules'] as $wardId => $frequency) {
            // Verify user has access to this ward
            if (!$user->isAdmin() && !$user->hasAccessToWard($wardId)) {
                continue;
            }
            
            UserWardExportSchedule::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'ward_id' => $wardId,
                ],
                [
                    'frequency' => $frequency,
                ]
            );
        }
        
        return redirect()->route('settings.index')
            ->with('success', 'Export schedule preferences updated successfully');
    }

    public function destroy(Request $request)
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
