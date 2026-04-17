<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\KnockResult;
use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanvassingTest extends TestCase
{
    use RefreshDatabase;

    public function test_canvassing_index_requires_authentication(): void
    {
        $this->get('/canvassing')->assertRedirect(route('login'));
    }

    public function test_admin_sees_all_wards_on_index(): void
    {
        $admin = User::factory()->admin()->create();
        Ward::factory()->count(3)->create();

        $this->actingAs($admin)->get('/canvassing')->assertOk();
    }

    public function test_canvasser_only_sees_assigned_wards(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $assigned = Ward::factory()->create();
        Ward::factory()->create(); // unassigned
        $canvasser->wards()->attach($assigned);

        $response = $this->actingAs($canvasser)->get('/canvassing');
        $response->assertOk()->assertSee($assigned->name);
    }

    public function test_ward_view_requires_access(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $ward = Ward::factory()->create();

        $this->actingAs($canvasser)->get("/ward/{$ward->id}")->assertForbidden();
    }

    public function test_admin_can_view_any_ward(): void
    {
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();
        Address::factory()->create(['ward_id' => $ward->id]);

        $this->actingAs($admin)->get("/ward/{$ward->id}")->assertOk();
    }

    public function test_canvasser_can_view_assigned_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $ward = Ward::factory()->create();
        $canvasser->wards()->attach($ward);
        Address::factory()->create(['ward_id' => $ward->id]);

        $this->actingAs($canvasser)->get("/ward/{$ward->id}")->assertOk();
    }

    public function test_knock_result_can_be_stored(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create();

        $response = $this->actingAs($admin)->post('/knock-result', [
            'address_id' => $address->id,
            'response' => 'not_home',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('knock_results', [
            'address_id' => $address->id,
            'response' => 'not_home',
            'user_id' => $admin->id,
        ]);
    }

    public function test_knock_result_store_denied_for_inaccessible_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $address = Address::factory()->create();

        $this->actingAs($canvasser)->post('/knock-result', [
            'address_id' => $address->id,
            'response' => 'not_home',
        ])->assertForbidden();
    }

    public function test_knock_result_can_be_updated(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create();
        $result = KnockResult::factory()->create(['address_id' => $address->id, 'user_id' => $admin->id, 'response' => 'not_home']);

        $this->actingAs($admin)->put("/knock-result/{$result->id}", [
            'response' => 'green',
        ])->assertRedirect();

        $this->assertDatabaseHas('knock_results', ['id' => $result->id, 'response' => 'green']);
    }

    public function test_knock_result_update_denied_for_inaccessible_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $address = Address::factory()->create();
        $result = KnockResult::factory()->create(['address_id' => $address->id]);

        $this->actingAs($canvasser)->put("/knock-result/{$result->id}", [
            'response' => 'green',
        ])->assertForbidden();
    }

    public function test_knock_result_can_be_deleted(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create();
        $result = KnockResult::factory()->create(['address_id' => $address->id]);

        $this->actingAs($admin)->delete("/knock-result/{$result->id}")->assertRedirect();

        $this->assertDatabaseMissing('knock_results', ['id' => $result->id]);
    }

    public function test_address_can_be_marked_do_not_knock(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create(['do_not_knock' => false]);

        $this->actingAs($admin)->post("/address/{$address->id}/do-not-knock")->assertRedirect();

        $this->assertTrue($address->refresh()->do_not_knock);
    }

    public function test_do_not_knock_can_be_cleared(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create(['do_not_knock' => true]);

        $this->actingAs($admin)->delete("/address/{$address->id}/do-not-knock")->assertRedirect();

        $this->assertFalse($address->refresh()->do_not_knock);
    }

    public function test_mark_do_not_knock_denied_for_inaccessible_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $address = Address::factory()->create();

        $this->actingAs($canvasser)->post("/address/{$address->id}/do-not-knock")->assertForbidden();
    }

    public function test_new_address_can_be_stored(): void
    {
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        $this->actingAs($admin)->post('/address/create', [
            'ward_id' => $ward->id,
            'house_number' => '42',
            'street_name' => 'Test Street',
            'town' => 'Testville',
            'postcode' => 'HX1 1AA',
        ])->assertRedirect();

        $this->assertDatabaseHas('addresses', [
            'ward_id' => $ward->id,
            'house_number' => '42',
            'street_name' => 'Test Street',
        ]);
    }

    public function test_duplicate_address_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $address = Address::factory()->create();

        $this->actingAs($admin)->post('/address/create', [
            'ward_id' => $address->ward_id,
            'house_number' => $address->house_number,
            'street_name' => $address->street_name,
            'town' => $address->town,
            'postcode' => $address->postcode,
        ])->assertRedirect()->assertSessionHas('error');
    }
}
