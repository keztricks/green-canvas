<?php

namespace App\Console\Commands;

use App\Models\Address;
use App\Models\Ward;
use Illuminate\Console\Command;

class RemoveWardAddresses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'addresses:remove-ward {ward : The name of the ward to remove addresses from} {--force : Skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove all addresses from a specified ward';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $wardName = $this->argument('ward');
        
        // Find the ward
        $ward = Ward::where('name', $wardName)->first();

        if (!$ward) {
            $this->error("Ward '{$wardName}' not found.");
            
            // Show available wards
            $availableWards = Ward::pluck('name')->toArray();
            if (!empty($availableWards)) {
                $this->info('Available wards: ' . implode(', ', $availableWards));
            }
            
            return 1;
        }

        // Count addresses in the ward
        $addressCount = Address::where('ward_id', $ward->id)->count();

        if ($addressCount === 0) {
            $this->info("No addresses found in {$wardName} ward.");
            return 0;
        }

        $this->info("Found {$addressCount} addresses in {$wardName} ward.");

        // Ask for confirmation unless --force is used
        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to delete all {$wardName} addresses? This will also delete related knock results and election data.")) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        // Delete addresses (cascade will handle knock_results and election relationships)
        $deleted = Address::where('ward_id', $ward->id)->delete();

        $this->info("Successfully deleted {$deleted} addresses from {$wardName} ward.");

        return 0;
    }
}
