<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_is_accessible(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/settings')->assertOk();
    }

    public function test_settings_page_requires_authentication(): void
    {
        $this->get('/settings')->assertRedirect(route('login'));
    }

    public function test_profile_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect(route('settings.index'));

        $user->refresh();
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_preserved_when_email_unchanged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'New Name',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_password_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/password', [
            'current_password' => 'password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('settings.index'));

        $this->assertTrue(Hash::check('NewPassword1', $user->refresh()->password));
    }

    public function test_password_update_fails_with_wrong_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch('/settings/password', [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_account_can_be_deleted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings', [
            'password' => 'password',
        ])->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_account_deletion_requires_correct_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->delete('/settings', [
            'password' => 'wrong-password',
        ])->assertSessionHasErrorsIn('userDeletion', 'password');

        $this->assertNotNull($user->fresh());
    }
}
