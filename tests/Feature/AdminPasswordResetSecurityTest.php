<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminPasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_link_response_does_not_disclose_tokens_or_links(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => 'full', 'is_active' => true]);

        $response = $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $admin->email]);

        $response->assertRedirect(route('password.request'))
            ->assertSessionHas('status', 'If an active administrator account matches that email, a password reset link has been sent.');
        $this->assertStringNotContainsString('token', $response->getContent());
        $this->assertStringNotContainsString('/admin/reset-password/', $response->getContent());
        Notification::assertSentTo($admin, \Illuminate\Auth\Notifications\ResetPassword::class);
    }

    public function test_password_reset_page_shows_the_shared_password_policy(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'is_active' => true]);
        $token = Password::broker()->createToken($admin);

        $this->get(route('password.reset', ['token' => $token, 'email' => $admin->email]))
            ->assertOk()
            ->assertSee('At least 12 characters. Include uppercase and lowercase letters, a number, and a symbol.')
            ->assertSee('minlength="12"', false);
    }

    public function test_password_reset_rejects_password_shorter_than_the_shared_policy(): void
    {
        $admin = User::factory()->create(['role' => 'full', 'is_active' => true]);
        $token = Password::broker()->createToken($admin);

        $this->from(route('password.reset', ['token' => $token, 'email' => $admin->email]))
            ->followingRedirects()
            ->post(route('password.update'), [
                'email' => $admin->email,
                'token' => $token,
                'password' => 'Strong#Pas2',
                'password_confirmation' => 'Strong#Pas2',
            ])
            ->assertOk()
            ->assertSee('The password field must be at least 12 characters.')
            ->assertSee('At least 12 characters. Include uppercase and lowercase letters, a number, and a symbol.');
    }

    public function test_password_reset_revokes_existing_admin_sessions(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'email' => 'reset.admin@example.test',
            'password' => 'CurrentPassword#2026',
            'is_active' => true,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'CurrentPassword#2026',
        ])->assertRedirect(route('admin.dashboard'));

        $oldSessionVersion = session('admin_session_version');
        $token = Password::broker()->createToken($admin);

        $this->post(route('password.update'), [
            'email' => $admin->email,
            'token' => $token,
            'password' => 'ResetPassword#2026',
            'password_confirmation' => 'ResetPassword#2026',
        ])->assertRedirect(route('admin.login'))
            ->assertSessionHas('success', 'Password reset successfully. You can now sign in.');

        $this->assertSame($oldSessionVersion + 1, $admin->fresh()->session_version);

        $this->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator session has expired. Please sign in again.');
    }
}
