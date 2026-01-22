<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\KnockResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ExportController extends Controller
{
    public function index()
    {
        $exports = Export::orderBy('created_at', 'desc')->get();
        return view('exports.index', compact('exports'));
    }

    public function create()
    {
        $totalResults = KnockResult::count();
        $lastExport = Export::latest()->first();
        
        // Suggest next version number
        $nextVersion = 'v1';
        if ($lastExport) {
            preg_match('/v(\d+)/', $lastExport->version, $matches);
            $nextVersion = 'v' . ((int)($matches[1] ?? 0) + 1);
        }

        return view('exports.create', compact('totalResults', 'nextVersion'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'version' => 'required|string|max:50|unique:exports,version',
            'notes' => 'nullable|string|max:500',
        ]);

        // Get all knock results with address information
        $results = KnockResult::with('address')
            ->orderBy('knocked_at', 'desc')
            ->get();

        if ($results->isEmpty()) {
            return redirect()->route('exports.index')
                ->with('error', 'No knock results to export');
        }

        // Generate CSV content
        $csvContent = $this->generateCSV($results);
        
        // Create filename with timestamp
        $filename = 'knock_results_' . $validated['version'] . '_' . now()->format('Y-m-d_His') . '.csv';
        
        // Save to storage
        Storage::disk('local')->put('exports/' . $filename, $csvContent);

        // Track the export
        Export::create([
            'filename' => $filename,
            'record_count' => $results->count(),
            'version' => $validated['version'],
            'notes' => $validated['notes'],
        ]);

        return redirect()->route('exports.index')
            ->with('success', "Export {$validated['version']} created successfully with {$results->count()} records");
    }

    public function download(Export $export)
    {
        $filePath = 'exports/' . $export->filename;
        
        if (!Storage::disk('local')->exists($filePath)) {
            return redirect()->route('exports.index')
                ->with('error', 'Export file not found');
        }

        return Storage::disk('local')->download($filePath, $export->filename);
    }

    public function destroy(Export $export)
    {
        // Delete the file
        $filePath = 'exports/' . $export->filename;
        if (Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        $export->delete();

        return redirect()->route('exports.index')
            ->with('success', 'Export deleted successfully');
    }

    private function generateCSV($results)
    {
        $csv = [];
        
        // Header row
        $csv[] = [
            'Export Date',
            'House Number',
            'Street Name',
            'Town',
            'Postcode',
            'Response',
            'Notes',
            'Canvasser',
            'Knocked At',
        ];

        // Data rows
        foreach ($results as $result) {
            $csv[] = [
                now()->format('Y-m-d H:i:s'),
                $result->address->house_number,
                $result->address->street_name,
                $result->address->town,
                $result->address->postcode,
                KnockResult::responseOptions()[$result->response] ?? $result->response,
                $result->notes ?? '',
                $result->canvasser_name ?? '',
                $result->knocked_at->format('Y-m-d H:i:s'),
            ];
        }

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csv as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }
}
