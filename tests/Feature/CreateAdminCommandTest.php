<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_admin_user_from_options(): void
    {
        $this->artisan('canvassing:create-admin', [
            '--name' => 'Alex Admin',
            '--email' => 'alex@example.com',
            '--password' => 'secret-pw-123',
        ])->assertSuccessful();

        $user = User::where('email', 'alex@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame(User::ROLE_ADMIN, $user->role);
        $this->assertSame('Alex Admin', $user->name);
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('canvassing:create-admin', [
            '--name' => 'Alex Admin',
            '--email' => 'alex@example.com',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->admin()->create(['email' => 'taken@example.com']);

        $this->artisan('canvassing:create-admin', [
            '--name' => 'Other Admin',
            '--email' => 'taken@example.com',
            '--password' => 'long-enough-password',
        ])->assertFailed();

        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('canvassing:create-admin', [
            '--name' => 'Bad Email',
            '--email' => 'not-an-email',
            '--password' => 'long-enough-password',
        ])->assertFailed();

        $this->assertSame(0, User::count());
    }
}
