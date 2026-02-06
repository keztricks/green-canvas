<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use Illuminate\Http\Request;

class FeatureFlagController extends Controller
{
    public function index()
    {
        // Only admins can access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $flags = FeatureFlag::orderBy('name')->get();
        return view('feature-flags.index', compact('flags'));
    }

    public function toggle(FeatureFlag $flag)
    {
        // Only admins can access
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $flag->update([
            'is_enabled' => !$flag->is_enabled
        ]);

        $status = $flag->is_enabled ? 'enabled' : 'disabled';
        
        return redirect()->route('feature-flags.index')
            ->with('success', "Feature '{$flag->name}' has been {$status}.");
    }
}
