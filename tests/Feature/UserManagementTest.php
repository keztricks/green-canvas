<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_users(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->canvasser()->count(3)->create();

        $this->actingAs($admin)->get('/users')->assertOk();
    }

    public function test_ward_admin_can_list_canvassers(): void
    {
        $wardAdmin = User::factory()->wardAdmin()->create();

        $this->actingAs($wardAdmin)->get('/users')->assertOk();
    }

    public function test_canvasser_cannot_access_user_list(): void
    {
        $canvasser = User::factory()->canvasser()->create();

        $this->actingAs($canvasser)->get('/users')->assertForbidden();
    }

    public function test_admin_can_create_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/users', [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
            'role' => 'canvasser',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com', 'role' => 'canvasser']);
    }

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->canvasser()->create();

        $this->actingAs($admin)->delete("/users/{$target->id}")->assertRedirect();

        $this->assertNull($target->fresh());
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->delete("/users/{$admin->id}")->assertRedirect();

        $this->assertNotNull($admin->fresh());
    }

    public function test_ward_admin_cannot_create_admin_user(): void
    {
        $wardAdmin = User::factory()->wardAdmin()->create();

        $this->actingAs($wardAdmin)->post('/users', [
            'name' => 'Bad Admin',
            'email' => 'bad@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
            'role' => 'admin',
        ])->assertSessionHasErrors('role');
    }

    public function test_ward_admin_cannot_assign_user_to_unmanaged_ward(): void
    {
        $wardAdmin = User::factory()->wardAdmin()->create();
        $ownWard = Ward::factory()->create();
        $otherWard = Ward::factory()->create();
        $wardAdmin->wards()->attach($ownWard);

        $this->actingAs($wardAdmin)->post('/users', [
            'name' => 'New Canvasser',
            'email' => 'canv@example.com',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
            'role' => 'canvasser',
            'wards' => [$otherWard->id],
        ])->assertRedirect()->assertSessionHasErrors('wards');
    }
}
