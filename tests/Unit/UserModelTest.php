<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Ward;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_admin_returns_true_for_admin_role(): void
    {
        $user = User::factory()->admin()->make();
        $this->assertTrue($user->isAdmin());
        $this->assertFalse($user->isCanvasser());
        $this->assertFalse($user->isWardAdmin());
    }

    public function test_is_canvasser_returns_true_for_canvasser_role(): void
    {
        $user = User::factory()->canvasser()->make();
        $this->assertTrue($user->isCanvasser());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isWardAdmin());
    }

    public function test_is_ward_admin_returns_true_for_ward_admin_role(): void
    {
        $user = User::factory()->wardAdmin()->make();
        $this->assertTrue($user->isWardAdmin());
        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->isCanvasser());
    }

    public function test_can_access_exports_is_true_for_admin(): void
    {
        $this->assertTrue(User::factory()->admin()->make()->canAccessExports());
    }

    public function test_can_access_exports_is_true_for_ward_admin(): void
    {
        $this->assertTrue(User::factory()->wardAdmin()->make()->canAccessExports());
    }

    public function test_can_access_exports_is_false_for_canvasser(): void
    {
        $this->assertFalse(User::factory()->canvasser()->make()->canAccessExports());
    }

    public function test_admin_has_access_to_all_wards(): void
    {
        $admin = User::factory()->admin()->create();
        $ward = Ward::factory()->create();

        $this->assertTrue($admin->hasAccessToWard($ward->id));
    }

    public function test_canvasser_has_access_to_assigned_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $ward = Ward::factory()->create();
        $canvasser->wards()->attach($ward);

        $this->assertTrue($canvasser->hasAccessToWard($ward->id));
    }

    public function test_canvasser_has_no_access_to_unassigned_ward(): void
    {
        $canvasser = User::factory()->canvasser()->create();
        $ward = Ward::factory()->create();

        $this->assertFalse($canvasser->hasAccessToWard($ward->id));
    }

    public function test_email_is_stored_lowercase(): void
    {
        $user = User::factory()->create(['email' => 'UPPER@EXAMPLE.COM']);

        $this->assertSame('upper@example.com', $user->email);
    }
}
