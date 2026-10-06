<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\User;
use App\Services\AdminPasswordVerifier;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmergencySuperAdminTest extends TestCase
{
    use RefreshDatabase;

    private const EMAIL = 'emergency@example.test';

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = Str::random(64);
        config([
            'admin.super_admin_email' => self::EMAIL,
            'admin.super_admin_password' => $this->password,
        ]);
    }

    public function test_emergency_admin_signs_in_through_the_normal_login_without_a_user_record(): void
    {
        $throttleKey = self::EMAIL.'|127.0.0.1';
        RateLimiter::clear($throttleKey);
        $initialSessionId = session()->getId();

        $this->post(route('admin.login.post'), [
            'email' => self::EMAIL,
            'password' => $this->password,
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertTrue(session('is_admin'));
        $this->assertSame('full', session('admin_role'));
        $this->assertSame('emergency', session('admin_auth_source'));
        $this->assertSame(self::EMAIL, session('admin_email'));
        $this->assertNull(session('admin_user_id'));
        $this->assertNotSame($initialSessionId, session()->getId());
        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'actor_name' => 'Emergency Super Admin',
            'actor_email' => self::EMAIL,
            'action' => 'Signed in',
        ]);
    }

    public function test_emergency_admin_login_rejects_an_incorrect_password_without_database_fallback(): void
    {
        User::factory()->create([
            'email' => self::EMAIL,
            'password' => 'NormalDatabaseAdmin#2026',
            'role' => 'full',
            'is_active' => true,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => self::EMAIL,
            'password' => 'NormalDatabaseAdmin#2026',
        ])->assertSessionHas('error', 'Invalid admin credentials.');

        $this->assertNull(session('is_admin'));
    }

    public function test_emergency_admin_login_rejects_an_incorrect_email(): void
    {
        $this->post(route('admin.login.post'), [
            'email' => 'wrong-emergency@example.test',
            'password' => $this->password,
        ])->assertSessionHas('error', 'Invalid admin credentials.');

        $this->assertNull(session('is_admin'));
    }

    public function test_missing_emergency_credentials_fail_safely(): void
    {
        config([
            'admin.super_admin_email' => '',
            'admin.super_admin_password' => '',
        ]);

        $this->post(route('admin.login.post'), [
            'email' => self::EMAIL,
            'password' => $this->password,
        ])->assertSessionHas('error', 'Invalid admin credentials.');

        $this->assertNull(session('is_admin'));
    }

    public function test_emergency_login_uses_the_existing_email_and_ip_throttle(): void
    {
        $throttleKey = Str::lower(self::EMAIL).'|127.0.0.1';
        RateLimiter::clear($throttleKey);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('admin.login.post'), [
                'email' => self::EMAIL,
                'password' => 'incorrect',
            ])->assertSessionHas('error', 'Invalid admin credentials.');
        }

        $this->post(route('admin.login.post'), [
            'email' => self::EMAIL,
            'password' => $this->password,
        ])->assertSessionHas('error');

        $this->assertStringContainsString('Too many login attempts', session('error'));
        $this->assertNull(session('is_admin'));
    }

    public function test_emergency_admin_can_reach_full_admin_management_and_backup_recovery_routes(): void
    {
        $this->loginAsEmergencyAdmin();

        $this->get(route('admin.users'))->assertOk();
        $this->get(route('admin.backups'))->assertOk();
    }

    public function test_emergency_admin_can_recover_a_normal_admin_password_with_step_up_confirmation(): void
    {
        $teamAdmin = User::factory()->create([
            'role' => 'limited',
            'is_active' => true,
        ]);
        $this->loginAsEmergencyAdmin();

        $this->put(route('admin.users.reset', $teamAdmin), [
            'password' => 'RecoveredTeamAdmin#2026',
            'password_confirmation' => 'RecoveredTeamAdmin#2026',
            'current_admin_password' => $this->password,
        ])->assertRedirect();

        $this->assertTrue(password_verify('RecoveredTeamAdmin#2026', $teamAdmin->fresh()->password));
        $this->assertSame('limited', $teamAdmin->fresh()->role);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'Changed administrator password',
            'actor_name' => 'Emergency Super Admin',
        ]);
    }

    public function test_emergency_admin_can_restore_through_the_existing_authorized_recovery_route(): void
    {
        $package = Package::create([
            'name' => 'Emergency recovery package',
            'slug' => 'emergency-recovery-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
            'description' => 'Saved description',
        ]);
        $backupService = app(BackupService::class);
        $existingBackups = $backupService->listBackups();
        $previousTestNow = Carbon::getTestNow();
        Carbon::setTestNow(Carbon::now()->addYears(5)->addSeconds(random_int(1, 10000000)));

        try {
            $backupPath = $backupService->create();
            $backupName = basename($backupPath);
            $package->update(['description' => 'Changed description']);
            $this->loginAsEmergencyAdmin();

            $this->post(route('admin.backups.restore'), [
                'backup' => $backupName,
                'current_admin_password' => $this->password,
            ])->assertRedirect()->assertSessionHas('success');

            $this->assertSame('Saved description', $package->fresh()->description);
            $this->assertDatabaseHas('activity_logs', [
                'action' => 'Database restore completed',
                'actor_name' => 'Emergency Super Admin',
            ]);
            $this->assertCount(count($existingBackups) + 2, $backupService->listBackups());
        } finally {
            foreach (array_diff($backupService->listBackups(), $existingBackups) as $backup) {
                $backupService->delete($backup);
            }
            Carbon::setTestNow($previousTestNow);
        }
    }

    public function test_emergency_admin_session_survives_loss_of_normal_admin_records(): void
    {
        $this->loginAsEmergencyAdmin();
        User::query()->delete();

        $this->get(route('admin.backups'))->assertOk();
        $this->assertSame('emergency', session('admin_auth_source'));
        $this->assertNull(session('admin_user_id'));
    }

    public function test_team_admin_cannot_access_full_admin_management_or_backups(): void
    {
        $teamAdmin = User::factory()->create([
            'role' => 'limited',
            'is_active' => true,
        ]);
        $session = [
            'is_admin' => true,
            'admin_role' => 'limited',
            'admin_auth_source' => 'database',
            'admin_user_id' => $teamAdmin->id,
            'admin_session_version' => $teamAdmin->session_version,
        ];

        $this->withSession($session)->get(route('admin.users'))->assertForbidden();
        $this->withSession($session)->get(route('admin.backups'))->assertForbidden();
    }

    public function test_emergency_admin_password_confirmation_uses_environment_credentials(): void
    {
        $this->loginAsEmergencyAdmin();

        $verifier = app(AdminPasswordVerifier::class);
        $this->assertTrue($verifier->verify(request(), $this->password));
        $this->assertFalse($verifier->verify(request(), 'incorrect'));
    }

    public function test_emergency_admin_is_not_a_database_account_and_cannot_be_managed_as_one(): void
    {
        $this->loginAsEmergencyAdmin();

        $this->assertDatabaseMissing('users', ['email' => self::EMAIL]);
        $this->get(route('admin.users'))->assertOk();
    }

    public function test_emergency_admin_logout_clears_the_session_and_records_actor_without_password(): void
    {
        $this->loginAsEmergencyAdmin();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));

        $this->assertNull(session('is_admin'));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => null,
            'actor_name' => 'Emergency Super Admin',
            'actor_email' => self::EMAIL,
            'action' => 'Signed out',
        ]);
        $this->assertStringNotContainsString($this->password, ActivityLog::query()->pluck('description')->implode(' '));
    }

    public function test_emergency_password_is_not_included_in_database_backup_payload_or_ciphertext(): void
    {
        $this->loginAsEmergencyAdmin();
        $backupService = app(BackupService::class);
        $backupPath = $backupService->create();
        $backupName = basename($backupPath);

        try {
            $ciphertext = file_get_contents($backupPath);
            $payload = Crypt::decryptString($ciphertext);

            $this->assertStringNotContainsString($this->password, $ciphertext);
            $this->assertStringNotContainsString($this->password, $payload);
            $this->assertStringNotContainsString('"users"', $payload);
        } finally {
            $backupService->delete($backupName);
        }
    }

    public function test_existing_primary_and_team_admin_logins_remain_supported(): void
    {
        $primary = User::factory()->create([
            'role' => 'full',
            'password' => 'PrimaryAdmin-Test#2026',
            'is_active' => true,
        ]);
        $this->post(route('admin.login.post'), [
            'email' => $primary->email,
            'password' => 'PrimaryAdmin-Test#2026',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('database', session('admin_auth_source'));

        $this->post(route('admin.logout'));
        $teamAdmin = User::factory()->create([
            'role' => 'limited',
            'password' => 'TeamAdmin-Test#2026',
            'is_active' => true,
        ]);
        $this->post(route('admin.login.post'), [
            'email' => $teamAdmin->email,
            'password' => 'TeamAdmin-Test#2026',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertSame('limited', session('admin_role'));
        $this->assertSame('database', session('admin_auth_source'));
    }

    private function loginAsEmergencyAdmin(): void
    {
        $this->post(route('admin.login.post'), [
            'email' => self::EMAIL,
            'password' => $this->password,
        ])->assertRedirect(route('admin.dashboard'));
    }
}
