<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_primary_admin_database_credentials_log_in(): void
    {
        $admin = $this->createPrimaryAdmin();

        $response = $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'ValidPrimarySecret#2026',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(session('is_admin'));
        $this->assertSame($admin->id, session('admin_user_id'));
        $this->assertSame('full', session('admin_role'));
    }

    public function test_wrong_password_is_rejected_for_a_known_admin(): void
    {
        $admin = $this->createPrimaryAdmin();

        $response = $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'incorrect-secret',
        ]);

        $response->assertSessionHas('error', 'Invalid admin credentials.');
        $this->assertNull(session('is_admin'));
    }

    public function test_repeated_failed_attempts_are_rate_limited(): void
    {
        $admin = $this->createPrimaryAdmin();
        $throttleKey = Str::lower($admin->email).'|127.0.0.1';
        RateLimiter::clear($throttleKey);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.post'), ['email' => $admin->email, 'password' => 'wrong'])
                ->assertSessionHas('error', 'Invalid admin credentials.');
        }

        $response = $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'ValidPrimarySecret#2026',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Too many login attempts', session('error'));
        $this->assertNull(session('is_admin'));
    }

    public function test_successful_login_clears_the_rate_limit_counter(): void
    {
        $admin = $this->createPrimaryAdmin();
        $throttleKey = Str::lower($admin->email).'|127.0.0.1';
        RateLimiter::clear($throttleKey);
        $this->post(route('admin.login.post'), ['email' => $admin->email, 'password' => 'wrong']);
        $this->post(route('admin.login.post'), ['email' => $admin->email, 'password' => 'wrong']);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'ValidPrimarySecret#2026',
        ])->assertRedirect(route('admin.dashboard'));

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('admin.login.post'), ['email' => $admin->email, 'password' => 'wrong'])
                ->assertSessionHas('error', 'Invalid admin credentials.');
        }
    }

    public function test_limited_admin_cannot_access_backups(): void
    {
        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
        ])->get(route('admin.backups'))
            ->assertForbidden();
    }

    public function test_full_admin_can_still_access_backups(): void
    {
        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.backups'))
            ->assertOk();
    }

    public function test_admin_session_without_a_database_identity_is_invalidated(): void
    {
        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => null,
        ])->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator session has expired. Please sign in again.');

        $this->assertNull(session('is_admin'));
    }

    public function test_admin_session_is_invalidated_after_session_version_changes(): void
    {
        $admin = $this->createPrimaryAdmin();
        $session = [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin->id,
            'admin_session_version' => $admin->session_version,
        ];
        $admin->increment('session_version');

        $this->withSession($session)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator session has expired. Please sign in again.');
    }

    private function createPrimaryAdmin(): User
    {
        return User::factory()->create([
            'role' => 'full',
            'email' => 'primary.admin@example.test',
            'password' => 'ValidPrimarySecret#2026',
            'is_active' => true,
        ]);
    }
}
