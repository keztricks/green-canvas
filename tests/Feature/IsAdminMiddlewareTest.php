<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IsAdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_import_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/import')->assertOk();
    }

    public function test_admin_can_access_elections_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/elections')->assertOk();
    }

    public function test_admin_can_access_feature_flags_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/feature-flags')->assertOk();
    }

    public function test_canvasser_cannot_access_import_page(): void
    {
        $canvasser = User::factory()->canvasser()->create();

        $this->actingAs($canvasser)->get('/import')->assertForbidden();
    }

    public function test_canvasser_cannot_access_elections_page(): void
    {
        $canvasser = User::factory()->canvasser()->create();

        $this->actingAs($canvasser)->get('/elections')->assertForbidden();
    }

    public function test_canvasser_cannot_access_feature_flags_page(): void
    {
        $canvasser = User::factory()->canvasser()->create();

        $this->actingAs($canvasser)->get('/feature-flags')->assertForbidden();
    }

    public function test_ward_admin_cannot_access_admin_routes(): void
    {
        $wardAdmin = User::factory()->wardAdmin()->create();

        $this->actingAs($wardAdmin)->get('/import')->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected_from_admin_routes(): void
    {
        $this->get('/import')->assertRedirect(route('login'));
    }
}
