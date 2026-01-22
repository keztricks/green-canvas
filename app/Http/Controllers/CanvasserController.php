<?php

namespace App\Http\Controllers;

use App\Models\Canvasser;
use Illuminate\Http\Request;

class CanvasserController extends Controller
{
    public function index()
    {
        $canvassers = Canvasser::orderBy('name')->get();
        return view('canvassers.index', compact('canvassers'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:canvassers,name',
        ]);

        Canvasser::create($validated);

        return redirect()->route('canvassers.index')
            ->with('success', 'Canvasser added successfully');
    }

    public function destroy(Canvasser $canvasser)
    {
        $canvasser->delete();

        return redirect()->route('canvassers.index')
            ->with('success', 'Canvasser removed successfully');
    }

    public function toggleActive(Canvasser $canvasser)
    {
        $canvasser->update(['active' => !$canvasser->active]);

        return redirect()->route('canvassers.index')
            ->with('success', 'Canvasser status updated');
    }
}
