<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AddressImportTest extends TestCase
{
    use RefreshDatabase;

    private function csvFile(array $rows, string $name = 'register.csv'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'gc-test-');
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return new UploadedFile($path, $name, 'text/csv', null, true);
    }

    private function header(): array
    {
        return [
            'Prefix', 'Number', 'Suffix', 'Markers', 'DOB', 'Name',
            'Postcode', 'Address1', 'Address2', 'Address3', 'Address4', 'Address5', 'Address6',
        ];
    }

    public function test_admin_can_import_a_basic_register_csv(): void
    {
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        $file = $this->csvFile([
            $this->header(),
            ['AA', '1', '0', '', '', 'SMITH John', 'DE1 1AA', '1 High Street', 'Demoville', 'DE1 1AA', '', '', ''],
            ['AA', '2', '0', '', '', 'SMITH Jane', 'DE1 1AA', '1 High Street', 'Demoville', 'DE1 1AA', '', '', ''],
            ['AB', '1', '0', '', '', 'JONES Sara', 'DE1 1AB', '3 High Street', 'Demoville', 'DE1 1AB', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post(route('import.store'), [
            'ward_id' => $ward->id,
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('import.index'))->assertSessionHas('success');
        $this->assertSame(2, Address::count());
        $this->assertSame(2, Address::where('house_number', '1')->first()->elector_count);
        $this->assertSame(1, Address::where('house_number', '3')->first()->elector_count);
    }

    public function test_default_constituency_comes_from_config(): void
    {
        Bus::fake();
        config(['canvassing.default_constituency' => 'Test Constituency']);
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        $file = $this->csvFile([
            $this->header(),
            ['AA', '1', '0', '', '', 'SMITH John', 'DE1 1AA', '1 High Street', 'Demoville', 'DE1 1AA', '', '', ''],
        ]);

        $this->actingAs($admin)->post(route('import.store'), [
            'ward_id' => $ward->id,
            'csv_file' => $file,
        ])->assertSessionHas('success');

        $this->assertSame('Test Constituency', Address::first()->constituency);
    }

    public function test_town_alias_default_used_when_address_has_no_explicit_town(): void
    {
        Bus::fake();
        config(['canvassing.town_aliases' => ['Demoville']]);
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        // Only fields are house+street and postcode — town gets filtered out as an alias,
        // so the importer falls back to the first configured alias as the default town.
        $file = $this->csvFile([
            $this->header(),
            ['AA', '1', '0', '', '', 'SMITH John', 'DE1 1AA', '5 High Street', 'Demoville', 'DE1 1AA', '', '', ''],
        ]);

        $this->actingAs($admin)->post(route('import.store'), [
            'ward_id' => $ward->id,
            'csv_file' => $file,
        ])->assertSessionHas('success');

        $this->assertSame('Demoville', Address::first()->town);
    }

    public function test_skipped_rows_report_per_reason_counts(): void
    {
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        $file = $this->csvFile([
            $this->header(),
            ['AA', '1', '0', '', '', 'OK Row', 'DE1 1AA', '1 High Street', 'Demoville', 'DE1 1AA', '', '', ''],
            // Missing postcode
            ['AA', '2', '0', '', '', 'No Postcode', '', '1 High Street', 'Demoville', '', '', '', ''],
            // Too few columns
            ['AA', '3', '0', '', ''],
            // Unparseable street: nothing that looks like a street name
            ['AA', '4', '0', '', '', 'Junk', 'DE9 9ZZ', 'Just gibberish', '', '', '', '', ''],
        ]);

        $response = $this->actingAs($admin)->post(route('import.store'), [
            'ward_id' => $ward->id,
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('success');
        $message = session('success');
        $this->assertStringContainsString('skipped', $message);
        $this->assertStringContainsString('missing postcode', $message);
        $this->assertStringContainsString('too few columns', $message);
    }

    public function test_ward_reference_lookup_uses_slugified_filename(): void
    {
        Bus::fake();
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create(['name' => 'North & South Ward']);

        // Drop a reference file matching Str::slug('North & South Ward') = "north-south-ward"
        config(['canvassing.ward_reference_dir' => 'test-ward-references']);
        $refDir = storage_path('app/test-ward-references');
        if (!is_dir($refDir)) {
            mkdir($refDir, 0777, true);
        }
        $refPath = $refDir . '/north-south-ward.csv';
        $handle = fopen($refPath, 'w');
        // 14 preamble lines (the importer skips them)
        for ($i = 0; $i < 14; $i++) {
            fputcsv($handle, ['', '', '', '', '', '', '', '', '']);
        }
        fputcsv($handle, ['North & South', 'North & South', 'Definitive Street', '', '', '', '', '', 'DE9 9XX']);
        fclose($handle);

        try {
            $file = $this->csvFile([
                $this->header(),
                // Address fields don't actually contain a real street — without the reference, this would fail to parse.
                ['AA', '1', '0', '', '', 'X', 'DE9 9XX', '99', 'Demoville', '', '', '', ''],
            ]);

            $this->actingAs($admin)->post(route('import.store'), [
                'ward_id' => $ward->id,
                'csv_file' => $file,
            ])->assertSessionHas('success');

            $address = Address::first();
            $this->assertNotNull($address, 'Reference lookup should have allowed the row to import.');
            $this->assertSame('Definitive Street', $address->street_name);
        } finally {
            @unlink($refPath);
            @rmdir($refDir);
        }
    }

    public function test_non_admin_cannot_import(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $ward = Ward::factory()->create();
        $file = $this->csvFile([$this->header()]);

        $this->actingAs($canvasser)->post(route('import.store'), [
            'ward_id' => $ward->id,
            'csv_file' => $file,
        ])->assertForbidden();
    }
}
