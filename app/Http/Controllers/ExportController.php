<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\KnockResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use App\Models\Address;

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
        
        // Filter out CSV exports for non-admin users
        if (!$user->isAdmin()) {
            $exports = $exports->filter(function($export) {
                return !str_ends_with($export->filename, '.csv');
            });
        }
        
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
            'include_not_knocked' => 'nullable|boolean',
        ]);
        
        // Restrict CSV exports to admins only
        if ($validated['format'] === 'csv' && !$user->isAdmin()) {
            return redirect()->back()->withErrors(['format' => 'Only administrators can export in CSV format.']);
        }
        
        // Verify user has access to the selected ward
        if (!empty($validated['ward_id']) && !$user->hasAccessToWard($validated['ward_id'])) {
            abort(403, 'You do not have access to this ward.');
        }

        $includeNotKnocked = !empty($validated['include_not_knocked']);

        // Build query with optional filters
        if ($includeNotKnocked) {
            // Use Address as base to include not knocked addresses
            $query = Address::query();
            
            // Restrict to user's wards if not admin
            if (!$user->isAdmin()) {
                $userWardIds = $user->wards->pluck('id')->toArray();
                $query->whereIn('addresses.ward_id', $userWardIds);
            }
            
            // Apply ward filter
            if (!empty($validated['ward_id'])) {
                $query->where('addresses.ward_id', $validated['ward_id']);
            }
            
            // Left join to get knock results (may be null)
            $query->leftJoin('knock_results', function($join) use ($validated) {
                $join->on('addresses.id', '=', 'knock_results.address_id');
                
                // Apply date filters to knock results if provided
                if (!empty($validated['date_from'])) {
                    $join->whereDate('knock_results.knocked_at', '>=', $validated['date_from']);
                }
                if (!empty($validated['date_to'])) {
                    $join->whereDate('knock_results.knocked_at', '<=', $validated['date_to']);
                }
            });
            
            $query->with(['knockResults.user']);
            
            $results = $query->orderBy('addresses.street_name')
                ->orderBy('addresses.sort_order')
                ->orderBy('knock_results.knocked_at', 'desc')
                ->select('addresses.*', 'knock_results.*', 'addresses.id as address_id')
                ->get()
                ->groupBy('address_id');
        } else {
            // Original query - only knocked addresses
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
        }

        if ($results->isEmpty()) {
            $message = $includeNotKnocked ? 'No addresses found to export' : 'No knock results to export';
            return redirect()->route('exports.index')
                ->with('error', $message);
        }

        // Calculate total addresses with same filters for statistics
        $addressQuery = Address::query();
        
        // Apply same ward restrictions
        if (!$user->isAdmin()) {
            $userWardIds = $user->wards->pluck('id')->toArray();
            $addressQuery->whereIn('ward_id', $userWardIds);
        }
        
        if (!empty($validated['ward_id'])) {
            $addressQuery->where('ward_id', $validated['ward_id']);
        }
        
        $totalAddresses = $addressQuery->count();

        // Create filename with timestamp
        $extension = $validated['format'];
        $filename = 'knock_results_' . $validated['version'] . '_' . now()->format('Y-m-d_His') . '.' . $extension;
        
        // Generate file content based on format
        if ($validated['format'] === 'xlsx') {
            $filePath = $this->generateXLSX($results, $filename, $totalAddresses);
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
            'House Number',
            'Street Name',
            'Town',
            'Postcode',
            'Voting Intention',
            'Likelihood',
            'Intention Code',
            'Notes',
            'User',
            'Knocked At',
            'History Count',
            'Previous Results',
        ];

        // Data rows - one row per address with all historical data
        foreach ($groupedResults as $addressId => $results) {
            $latestResult = $results->first(); // Most recent
            
            // Handle addresses without knock results
            if (!$latestResult || !isset($latestResult->response)) {
                // Get address from the result object directly (when using leftJoin)
                $address = $latestResult;
                
                $csv[] = [
                    $address->house_number ?? '',
                    $address->street_name ?? '',
                    $address->town ?? '',
                    $address->postcode ?? '',
                    '', // No voting intention
                    '', // No likelihood
                    '', // No intention code
                    '', // No notes
                    '', // No user
                    '', // No knocked at
                    0,  // No history
                    '', // No previous results
                ];
                continue;
            }
            
            // When using leftJoin, address fields are on the result object directly
            // When using normal query, address is in the relationship
            $address = $latestResult->address ?? $latestResult;
            
            // Format response and likelihood separately
            $votingIntention = $this->formatResponse($latestResult->response);
            $likelihood = $latestResult->vote_likelihood ?? '';
            $intentionCode = $this->formatIntention($latestResult->response, $latestResult->vote_likelihood);
            
            // Handle knocked_at as string or Carbon instance
            $knockedAt = $latestResult->knocked_at;
            if (is_string($knockedAt)) {
                $knockedAt = \Carbon\Carbon::parse($knockedAt);
            }
            
            // Build history string for previous results
            $historyCount = $results->count() - 1;
            $previousResults = [];
            
            foreach ($results->skip(1) as $historicResult) {
                // Skip if this result doesn't have knock data
                if (!isset($historicResult->response) || !$historicResult->knocked_at) {
                    continue;
                }
                $historicIntention = $this->formatIntention($historicResult->response, $historicResult->vote_likelihood);
                
                // Handle knocked_at as string or Carbon instance
                $knockedDate = $historicResult->knocked_at;
                if (is_string($knockedDate)) {
                    $knockedDate = \Carbon\Carbon::parse($knockedDate);
                }
                
                $previousResults[] = sprintf(
                    '%s (%s, %s)',
                    $historicIntention,
                    $knockedDate->format('d/m/Y'),
                    $historicResult->user?->name ?? 'Unknown'
                );
            }
            
            $csv[] = [
                $address->house_number,
                $address->street_name,
                $address->town,
                $address->postcode,
                $votingIntention,
                $likelihood,
                $intentionCode,
                $latestResult->notes ?? '',
                $latestResult->user?->name ?? '',
                $knockedAt->format('Y-m-d H:i:s'),
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

    private function formatResponse($response)
    {
        $labels = [
            'conservative' => 'Conservative',
            'labour' => 'Labour',
            'lib_dem' => 'Liberal Democrat',
            'green' => 'Green Party',
            'reform' => 'Reform UK',
            'your_party' => 'Your Party',
            'undecided' => 'Undecided',
            'not_home' => 'Not Home',
            'refused' => 'Refused to Say',
            'other' => 'Other',
        ];

        return $labels[$response] ?? ucfirst($response);
    }

    private function generateXLSX($groupedResults, $filename, $totalAddresses = null)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Knock Results');
        
        // Header row
        $headers = [
            'House Number',
            'Street Name',
            'Town',
            'Postcode',
            'Voting Intention',
            'Likelihood',
            'Intention Code',
            'Notes',
            'User',
            'Knocked At',
            'History Count',
            'Previous Results',
        ];
        
        $sheet->fromArray($headers, null, 'A1');
        
        // Style header row
        $headerStyle = $sheet->getStyle('A1:L1');
        $headerStyle->getFont()->setBold(true)->setSize(12);
        $headerStyle->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('6AB023');
        $headerStyle->getFont()->getColor()->setRGB('FFFFFF');
        
        // Data rows
        $row = 2;
        foreach ($groupedResults as $addressId => $results) {
            $latestResult = $results->first();
            
            // Handle addresses without knock results
            if (!$latestResult || !isset($latestResult->response)) {
                // Get address from the result object directly (when using leftJoin)
                $address = $latestResult;
                
                $rowData = [
                    $address->house_number ?? '',
                    $address->street_name ?? '',
                    $address->town ?? '',
                    $address->postcode ?? '',
                    '', // No voting intention
                    '', // No likelihood
                    '', // No intention code
                    '', // No notes
                    '', // No user
                    '', // No knocked at
                    0,  // No history
                    '', // No previous results
                ];
                
                $sheet->fromArray($rowData, null, 'A' . $row);
                $row++;
                continue;
            }
            
            // When using leftJoin, address fields are on the result object directly
            // When using normal query, address is in the relationship
            $address = $latestResult->address ?? $latestResult;
            
            $votingIntention = $this->formatResponse($latestResult->response);
            $likelihood = $latestResult->vote_likelihood ?? '';
            $intentionCode = $this->formatIntention($latestResult->response, $latestResult->vote_likelihood);
            
            // Handle knocked_at as string or Carbon instance
            $knockedAt = $latestResult->knocked_at;
            if (is_string($knockedAt)) {
                $knockedAt = \Carbon\Carbon::parse($knockedAt);
            }
            
            // Format previous results
            $previousResults = [];
            foreach ($results->skip(1) as $result) {
                // Skip if this result doesn't have knock data
                if (!isset($result->response) || !$result->knocked_at) {
                    continue;
                }
                $intention = $this->formatIntention($result->response, $result->vote_likelihood);
                
                // Handle knocked_at as string or Carbon instance
                $knockedDate = $result->knocked_at;
                if (is_string($knockedDate)) {
                    $knockedDate = \Carbon\Carbon::parse($knockedDate);
                }
                
                $date = $knockedDate->format('d/m/Y');
                $user = $result->user ? $result->user->name : 'Unknown';
                $previousResults[] = "{$intention} ({$date}, {$user})";
            }
            
            $rowData = [
                $address->house_number,
                $address->street_name,
                $address->town,
                $address->postcode,
                $votingIntention,
                $likelihood,
                $intentionCode,
                $latestResult->notes ?? '',
                $latestResult->user ? $latestResult->user->name : 'Unknown',
                $knockedAt->format('Y-m-d H:i:s'),
                $results->count() - 1,
                implode(' | ', $previousResults),
            ];
            
            $sheet->fromArray($rowData, null, 'A' . $row);
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', 'L') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Add auto-filter to all columns
        $lastRow = $row - 1;
        $sheet->setAutoFilter('A1:L' . $lastRow);
        
        // Create summary statistics sheet if total addresses provided
        if ($totalAddresses !== null) {
            $this->createSummarySheet($spreadsheet, $groupedResults, $totalAddresses);
        }
        
        // Save to storage
        $writer = new Xlsx($spreadsheet);
        $writer->setIncludeCharts(true);
        $tempFile = tempnam(sys_get_temp_dir(), 'export_');
        $writer->save($tempFile);
        
        Storage::disk('local')->put('exports/' . $filename, file_get_contents($tempFile));
        unlink($tempFile);
        
        return 'exports/' . $filename;
    }

    private function createSummarySheet($spreadsheet, $groupedResults, $totalAddresses)
    {
        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        
        // Calculate statistics - count only addresses with actual knock results
        $knockedAddresses = 0;
        $intentionCounts = [];
        $greenLikelihoodCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        
        foreach ($groupedResults as $results) {
            $latestResult = $results->first();
            
            // Skip addresses without knock results
            if (!$latestResult || !isset($latestResult->response)) {
                continue;
            }
            
            $knockedAddresses++;
            $response = $latestResult->response;
            $intentionCounts[$response] = ($intentionCounts[$response] ?? 0) + 1;
            
            // Track Green Party likelihood distribution
            if ($latestResult->vote_likelihood) {
                $likelihood = (int)$latestResult->vote_likelihood;
                if (isset($greenLikelihoodCounts[$likelihood])) {
                    $greenLikelihoodCounts[$likelihood]++;
                }
            }
        }
        
        $notKnockedAddresses = $totalAddresses - $knockedAddresses;
        $knockedPercentage = $totalAddresses > 0 ? round(($knockedAddresses / $totalAddresses) * 100, 1) : 0;
        $notKnockedPercentage = $totalAddresses > 0 ? round(($notKnockedAddresses / $totalAddresses) * 100, 1) : 0;
        
        // Section 1: Canvassing Progress
        $summarySheet->setCellValue('A1', 'Canvassing Progress');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $summarySheet->getStyle('A1')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('6AB023');
        $summarySheet->getStyle('A1')->getFont()->getColor()->setRGB('FFFFFF');
        $summarySheet->mergeCells('A1:C1');
        
        $summarySheet->fromArray(['Status', 'Count', 'Percentage'], null, 'A2');
        $summarySheet->getStyle('A2:C2')->getFont()->setBold(true);
        
        $summarySheet->fromArray(['Knocked', $knockedAddresses, $knockedPercentage . '%'], null, 'A3');
        $summarySheet->fromArray(['Not Knocked', $notKnockedAddresses, $notKnockedPercentage . '%'], null, 'A4');
        $summarySheet->fromArray(['Total Addresses', $totalAddresses, '100%'], null, 'A5');
        $summarySheet->getStyle('A5:C5')->getFont()->setBold(true);
        
        // Section 2: Voting Intentions
        $summarySheet->setCellValue('A7', 'Voting Intentions');
        $summarySheet->getStyle('A7')->getFont()->setBold(true)->setSize(14);
        $summarySheet->getStyle('A7')->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('6AB023');
        $summarySheet->getStyle('A7')->getFont()->getColor()->setRGB('FFFFFF');
        $summarySheet->mergeCells('A7:C7');
        
        $summarySheet->fromArray(['Party/Response', 'Count', 'Percentage'], null, 'A8');
        $summarySheet->getStyle('A8:C8')->getFont()->setBold(true);
        
        $intentionRow = 9;
        foreach ($intentionCounts as $response => $count) {
            $percentage = $knockedAddresses > 0 ? round(($count / $knockedAddresses) * 100, 1) : 0;
            $label = $this->formatResponse($response);
            $summarySheet->fromArray([$label, $count, $percentage . '%'], null, 'A' . $intentionRow);
            $intentionRow++;
        }
        
        // Section 3: Green Party Support by Likelihood
        $greenStartRow = $intentionRow + 1;
        $summarySheet->setCellValue('A' . $greenStartRow, 'Green Party Support by Likelihood');
        $summarySheet->getStyle('A' . $greenStartRow)->getFont()->setBold(true)->setSize(14);
        $summarySheet->getStyle('A' . $greenStartRow)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('6AB023');
        $summarySheet->getStyle('A' . $greenStartRow)->getFont()->getColor()->setRGB('FFFFFF');
        $summarySheet->mergeCells('A' . $greenStartRow . ':C' . $greenStartRow);
        
        $greenHeaderRow = $greenStartRow + 1;
        $summarySheet->fromArray(['Likelihood', 'Count', 'Description'], null, 'A' . $greenHeaderRow);
        $summarySheet->getStyle('A' . $greenHeaderRow . ':C' . $greenHeaderRow)->getFont()->setBold(true);
        
        $likelihoodDescriptions = [
            1 => 'Very Likely',
            2 => 'Likely',
            3 => 'Possible',
            4 => 'Unlikely',
            5 => 'Never Voter'
        ];
        
        $greenDataRow = $greenHeaderRow + 1;
        foreach ($greenLikelihoodCounts as $likelihood => $count) {
            $description = $likelihoodDescriptions[$likelihood] ?? '';
            $summarySheet->fromArray([$likelihood, $count, $description], null, 'A' . $greenDataRow);
            $greenDataRow++;
        }
        
        // Auto-size columns
        foreach (range('A', 'C') as $col) {
            $summarySheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Create charts
        $this->addKnockedChart($summarySheet);
        $this->addVotingIntentionsChart($summarySheet, $intentionRow - 1);
        $this->addGreenLikelihoodChart($summarySheet, $greenDataRow - 1, $greenHeaderRow + 1);
    }
    
    private function addKnockedChart($sheet)
    {
        // Data series for knocked vs not knocked
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$3', null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$4', null, 1),
        ];
        
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$3:$A$4', null, 2),
        ];
        
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Summary!$B$3:$B$4', null, 2),
        ];
        
        $series = new DataSeries(
            DataSeries::TYPE_PIECHART,
            null,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        
        $layout = null;
        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Canvassing Progress');
        
        $chart = new Chart(
            'canvassingProgressChart',
            $title,
            $legend,
            $plotArea
        );
        
        $chart->setTopLeftPosition('E2');
        $chart->setBottomRightPosition('K18');
        
        $sheet->addChart($chart);
    }
    
    private function addVotingIntentionsChart($sheet, $lastIntentionRow)
    {
        $firstRow = 9;
        $rowCount = $lastIntentionRow - $firstRow + 1;
        
        if ($rowCount < 1) {
            return;
        }
        
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$B$8', null, 1),
        ];
        
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$' . $firstRow . ':$A$' . $lastIntentionRow, null, $rowCount),
        ];
        
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Summary!$B$' . $firstRow . ':$B$' . $lastIntentionRow, null, $rowCount),
        ];
        
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STANDARD,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        
        $series->setPlotDirection(DataSeries::DIRECTION_BAR);
        
        $layout = null;
        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Voting Intentions');
        
        $chart = new Chart(
            'votingIntentionsChart',
            $title,
            $legend,
            $plotArea
        );
        
        $chart->setTopLeftPosition('E20');
        $chart->setBottomRightPosition('K35');
        
        $sheet->addChart($chart);
    }
    
    private function addGreenLikelihoodChart($sheet, $lastRow, $firstRow)
    {
        $rowCount = $lastRow - $firstRow + 1;
        
        if ($rowCount < 1) {
            return;
        }
        
        $dataSeriesLabels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$B$' . ($firstRow - 1), null, 1),
        ];
        
        $xAxisTickValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, 'Summary!$A$' . $firstRow . ':$A$' . $lastRow, null, $rowCount),
        ];
        
        $dataSeriesValues = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, 'Summary!$B$' . $firstRow . ':$B$' . $lastRow, null, $rowCount),
        ];
        
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_CLUSTERED,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        
        $layout = null;
        $plotArea = new PlotArea($layout, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new Title('Green Party Support by Likelihood');
        
        $chart = new Chart(
            'greenLikelihoodChart',
            $title,
            $legend,
            $plotArea
        );
        
        $chart->setTopLeftPosition('L20');
        $chart->setBottomRightPosition('S35');
        
        $sheet->addChart($chart);
    }
}
