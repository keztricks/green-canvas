<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\KnockResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ExportController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        
        // Only admins and ward admins can access exports
        if (!$user->canAccessExports()) {
            abort(403, 'You do not have permission to access exports.');
        }
        
        // Filter exports based on user's ward access
        $query = Export::with('ward')->orderBy('created_at', 'desc');
        
        if (!$user->isAdmin()) {
            $userWardIds = $user->wards->pluck('id')->toArray();
            $query->whereIn('ward_id', $userWardIds);
        }
        
        $exports = $query->get();
        return view('exports.index', compact('exports'));
    }

    public function create()
    {
        $user = auth()->user();
        
        // Only admins and ward admins can access exports
        if (!$user->canAccessExports()) {
            abort(403, 'You do not have permission to access exports.');
        }
        
        // Build query for total results count based on user's ward access
        $query = KnockResult::query();
        
        if (!$user->isAdmin()) {
            $userWardIds = $user->wards->pluck('id')->toArray();
            $query->whereHas('address', function($q) use ($userWardIds) {
                $q->whereIn('ward_id', $userWardIds);
            });
        }
        
        $totalResults = $query->count();
        $lastExport = Export::latest()->first();
        
        // Suggest next version number
        $nextVersion = 'v1';
        if ($lastExport) {
            preg_match('/v(\d+)/', $lastExport->version, $matches);
            $nextVersion = 'v' . ((int)($matches[1] ?? 0) + 1);
        }

        // Filter wards based on user's access
        $wardsQuery = \App\Models\Ward::orderBy('name');
        
        if (!$user->isAdmin()) {
            $wardsQuery->whereHas('users', function($q) use ($user) {
                $q->where('users.id', $user->id);
            });
        }
        
        $wards = $wardsQuery->get();

        return view('exports.create', compact('totalResults', 'nextVersion', 'wards'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        
        // Only admins and ward admins can access exports
        if (!$user->canAccessExports()) {
            abort(403, 'You do not have permission to access exports.');
        }
        
        $validated = $request->validate([
            'version' => 'required|string|max:50|unique:exports,version',
            'notes' => 'nullable|string|max:500',
            'format' => 'required|in:csv,xlsx',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'ward_id' => 'nullable|exists:wards,id',
        ]);
        
        // Verify user has access to the selected ward
        if (!empty($validated['ward_id']) && !$user->hasAccessToWard($validated['ward_id'])) {
            abort(403, 'You do not have access to this ward.');
        }

        // Build query with optional filters
        $query = KnockResult::with(['address', 'user'])
            ->join('addresses', 'knock_results.address_id', '=', 'addresses.id');
        
        // Restrict to user's wards if not admin
        if (!$user->isAdmin()) {
            $userWardIds = $user->wards->pluck('id')->toArray();
            $query->whereIn('addresses.ward_id', $userWardIds);
        }
        
        // Apply date filters
        if (!empty($validated['date_from'])) {
            $query->whereDate('knock_results.knocked_at', '>=', $validated['date_from']);
        }
        if (!empty($validated['date_to'])) {
            $query->whereDate('knock_results.knocked_at', '<=', $validated['date_to']);
        }
        
        // Apply ward filter
        if (!empty($validated['ward_id'])) {
            $query->where('addresses.ward_id', $validated['ward_id']);
        }
        
        $results = $query->orderBy('addresses.street_name')
            ->orderBy('addresses.sort_order')
            ->orderBy('knock_results.knocked_at', 'desc')
            ->select('knock_results.*')
            ->get()
            ->groupBy('address_id');

        if ($results->isEmpty()) {
            return redirect()->route('exports.index')
                ->with('error', 'No knock results to export');
        }

        // Create filename with timestamp
        $extension = $validated['format'];
        $filename = 'knock_results_' . $validated['version'] . '_' . now()->format('Y-m-d_His') . '.' . $extension;
        
        // Generate file content based on format
        if ($validated['format'] === 'xlsx') {
            $filePath = $this->generateXLSX($results, $filename);
        } else {
            $csvContent = $this->generateCSV($results);
            Storage::disk('local')->put('exports/' . $filename, $csvContent);
        }

        // Count total records
        $totalRecords = $results->flatten()->count();

        // Track the export
        Export::create([
            'filename' => $filename,
            'record_count' => $totalRecords,
            'version' => $validated['version'],
            'notes' => $validated['notes'],
            'ward_id' => $validated['ward_id'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ]);

        return redirect()->route('exports.index')
            ->with('success', "Export {$validated['version']} created successfully with {$totalRecords} records");
    }

    public function download(Export $export)
    {
        $user = auth()->user();
        
        // Only admins and ward admins can access exports
        if (!$user->canAccessExports()) {
            abort(403, 'You do not have permission to access exports.');
        }
        
        // Verify user has access to this export
        if (!$user->isAdmin()) {
            // If export has a ward filter, check access to that ward
            if ($export->ward_id && !$user->hasAccessToWard($export->ward_id)) {
                abort(403, 'You do not have access to this export.');
            }
        }
        
        $filePath = 'exports/' . $export->filename;
        
        if (!Storage::disk('local')->exists($filePath)) {
            return redirect()->route('exports.index')
                ->with('error', 'Export file not found');
        }

        return Storage::disk('local')->download($filePath, $export->filename);
    }

    public function destroy(Export $export)
    {
        $user = auth()->user();
        
        // Only admins and ward admins can access exports
        if (!$user->canAccessExports()) {
            abort(403, 'You do not have permission to access exports.');
        }
        
        // Verify user has access to delete this export
        if (!$user->isAdmin()) {
            // If export has a ward filter, check access to that ward
            if ($export->ward_id && !$user->hasAccessToWard($export->ward_id)) {
                abort(403, 'You do not have access to delete this export.');
            }
        }
        
        // Delete the file
        $filePath = 'exports/' . $export->filename;
        if (Storage::disk('local')->exists($filePath)) {
            Storage::disk('local')->delete($filePath);
        }

        $export->delete();

        return redirect()->route('exports.index')
            ->with('success', 'Export deleted successfully');
    }

    private function generateCSV($groupedResults)
    {
        $csv = [];
        
        // Header row
        $csv[] = [
            'Export Date',
            'House Number',
            'Street Name',
            'Town',
            'Postcode',
            'Latest Intention',
            'Notes',
            'User',
            'Knocked At',
            'History Count',
            'Previous Results',
        ];

        // Data rows - one row per address with all historical data
        foreach ($groupedResults as $addressId => $results) {
            $latestResult = $results->first(); // Most recent
            $address = $latestResult->address;
            
            // Format latest intention
            $latestIntention = $this->formatIntention($latestResult->response, $latestResult->vote_likelihood);
            
            // Build history string for previous results
            $historyCount = $results->count() - 1;
            $previousResults = [];
            
            foreach ($results->skip(1) as $historicResult) {
                $historicIntention = $this->formatIntention($historicResult->response, $historicResult->vote_likelihood);
                $previousResults[] = sprintf(
                    '%s (%s, %s)',
                    $historicIntention,
                    $historicResult->knocked_at->format('d/m/Y'),
                    $historicResult->user?->name ?? 'Unknown'
                );
            }
            
            $csv[] = [
                now()->format('Y-m-d H:i:s'),
                $address->house_number,
                $address->street_name,
                $address->town,
                $address->postcode,
                $latestIntention,
                $latestResult->notes ?? '',
                $latestResult->user?->name ?? '',
                $latestResult->knocked_at->format('Y-m-d H:i:s'),
                $historyCount,
                implode(' | ', $previousResults),
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

    private function formatIntention($response, $likelihood)
    {
        $codes = [
            'conservative' => 'C',
            'labour' => 'L',
            'lib_dem' => 'LD',
            'green' => 'G',
            'reform' => 'R',
            'your_party' => 'Y',
            'undecided' => 'U',
            'not_home' => 'NH',
            'refused' => 'X',
            'other' => 'O',
        ];

        $code = $codes[$response] ?? strtoupper(substr($response, 0, 1));
        
        // Add likelihood if present
        if ($likelihood) {
            return $code . $likelihood;
        }
        
        return $code;
    }

    private function generateXLSX($groupedResults, $filename)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Knock Results');
        
        // Header row
        $headers = [
            'Export Date',
            'House Number',
            'Street Name',
            'Town',
            'Postcode',
            'Latest Intention',
            'Notes',
            'User',
            'Knocked At',
            'History Count',
            'Previous Results',
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Style header row
        $headerStyle = $sheet->getStyle('A1:K1');
        $headerStyle->getFont()->setBold(true)->setSize(12);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('6AB023');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');
        
        // Data rows
        $row = 2;
        foreach ($groupedResults as $addressId => $results) {
            $latestResult = $results->first();
            $address = $latestResult->address;
            
            $latestIntention = $this->formatIntention($latestResult->response, $latestResult->vote_likelihood);
            
            // Format previous results
            $previousResults = [];
            foreach ($results->skip(1) as $result) {
                $intention = $this->formatIntention($result->response, $result->vote_likelihood);
                $date = $result->knocked_at->format('d/m/Y');
                $user = $result->user ? $result->user->name : 'Unknown';
                $previousResults[] = "{$intention} ({$date}, {$user})";
            }
            
            $rowData = [
                now()->format('Y-m-d H:i:s'),
                $address->house_number,
                $address->street_name,
                $address->town,
                $address->postcode,
                $latestIntention,
                $latestResult->notes ?? '',
                $latestResult->user ? $latestResult->user->name : 'Unknown',
                $latestResult->knocked_at->format('Y-m-d H:i:s'),
                $results->count() - 1,
                implode(' | ', $previousResults),
            ];
            
            $sheet->fromArray($rowData, null, 'A' . $row);
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add auto-filter to all columns
        $lastRow = $row - 1;
        $sheet->setAutoFilter('A1:K' . $lastRow);
        
        // Save to storage
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        $writer->save($tempFile);
        
        Storage::disk('local')->put('exports/' . $filename, file_get_contents($tempFile));
        unlink($tempFile);
        
        return 'exports/' . $filename;
    }
}
