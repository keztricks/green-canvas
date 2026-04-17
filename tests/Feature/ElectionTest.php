<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Election;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_elections(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/elections')->assertOk();
    }

    public function test_non_admin_cannot_access_election_list(): void
    {
        $canvasser = User::factory()->canvasser()->create();

        $this->actingAs($canvasser)->get('/elections')->assertForbidden();
    }

    public function test_admin_can_create_election(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/elections', [
            'name' => 'Local Council 2026',
            'election_date' => '2026-05-07',
            'type' => 'local',
        ]);

        $response->assertRedirect(route('elections.index'));
        $this->assertDatabaseHas('elections', ['name' => 'Local Council 2026', 'type' => 'local']);
    }

    public function test_admin_can_delete_election(): void
    {
        $admin = User::factory()->admin()->create();
        $election = Election::factory()->create();

        $this->actingAs($admin)->delete("/elections/{$election->id}")
            ->assertRedirect(route('elections.index'));

        $this->assertNull($election->fresh());
    }

    public function test_non_admin_cannot_create_election(): void
    {
        $canvasser = User::factory()->canvasser()->create();

        $this->actingAs($canvasser)->post('/elections', [
            'name' => 'Unauthorized Election',
            'election_date' => '2026-05-07',
            'type' => 'local',
        ])->assertForbidden();
    }

    public function test_toggle_voted_cycles_through_statuses(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create();
        $election = Election::factory()->create();

        // First toggle: no record → voted
        $response = $this->actingAs($admin)
            ->post("/address/{$address->id}/election/{$election->id}/toggle");

        $response->assertOk()->assertJson(['success' => true, 'status' => 'voted']);

        // Second toggle: voted → not_voted
        $response = $this->actingAs($admin)
            ->post("/address/{$address->id}/election/{$election->id}/toggle");

        $response->assertOk()->assertJson(['success' => true, 'status' => 'not_voted']);

        // Third toggle: not_voted → unknown
        $response = $this->actingAs($admin)
            ->post("/address/{$address->id}/election/{$election->id}/toggle");

        $response->assertOk()->assertJson(['success' => true, 'status' => 'unknown']);
    }

    public function test_toggle_voted_denied_for_inaccessible_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $address = Address::factory()->create();
        $election = Election::factory()->create();

        $this->actingAs($canvasser)
            ->post("/address/{$address->id}/election/{$election->id}/toggle")
            ->assertForbidden();
    }

    public function test_election_can_be_associated_with_wards(): void
    {
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        $this->actingAs($admin)->post('/elections', [
            'name' => 'Ward Election',
            'election_date' => '2026-05-07',
            'type' => 'local',
            'ward_ids' => [$ward->id],
        ])->assertRedirect();

        $election = Election::where('name', 'Ward Election')->first();
        $this->assertTrue($election->wards->contains($ward));
    }
}
