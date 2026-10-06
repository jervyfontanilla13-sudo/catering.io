<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_and_new_admins_are_active_by_default(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $existing = User::factory()->create(['role' => 'limited']);
        $createdAdmin = User::factory()->make(['role' => 'full']);

        $this->assertTrue($existing->is_active);

        $response = $this->withSession($this->fullAdminSession($actor))
            ->post(route('admin.users.store'), [
                'name' => 'New Primary Admin',
                'email' => $createdAdmin->email,
                'password' => 'StrongPassword#2026',
                'password_confirmation' => 'StrongPassword#2026',
                'current_admin_password' => 'password',
                'role' => 'full',
            ]);

        $response->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email' => $createdAdmin->email,
            'role' => 'full',
            'is_active' => true,
        ]);
    }

    public function test_admin_account_creation_uses_the_shared_password_policy_and_audits_the_display_role(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $password = 'Strong#Pas26';

        $this->assertSame(12, strlen($password));

        $this->withSession($this->fullAdminSession($actor))
            ->post(route('admin.users.store'), [
                'name' => 'New Primary Admin',
                'email' => 'NEW.PRIMARY@example.test',
                'password' => $password,
                'password_confirmation' => $password,
                'current_admin_password' => 'password',
                'role' => 'full',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $created = User::where('email', 'new.primary@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $created->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'actor_name' => $actor->name,
            'actor_email' => $actor->email,
            'action' => 'Administrator created',
            'description' => 'Created Primary Admin account for New Primary Admin (new.primary@example.test).',
        ]);
        $this->assertDatabaseMissing('activity_logs', ['description' => $password]);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'password']);
    }

    public function test_admin_creation_rejects_short_passwords_and_mismatched_confirmation(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $validDetails = [
            'name' => 'New Team Admin',
            'email' => 'new.team@example.test',
            'role' => 'limited',
        ];

        $this->withSession($this->fullAdminSession($actor))
            ->from(route('admin.users'))
            ->followingRedirects()
            ->post(route('admin.users.store'), $validDetails + [
                'password' => 'Strong#Pas2',
                'password_confirmation' => 'Strong#Pas2',
                'current_admin_password' => 'password',
            ])
            ->assertOk()
            ->assertSee('The password field must be at least 12 characters.')
            ->assertSee('At least 12 characters. Include uppercase and lowercase letters, a number, and a symbol.');

        $this->withSession($this->fullAdminSession($actor))
            ->post(route('admin.users.store'), $validDetails + [
                'password' => 'Strong#Pas26',
                'password_confirmation' => 'Strong#Pas27',
                'current_admin_password' => 'password',
            ])
            ->assertSessionHasErrors(['password' => 'Passwords do not match.']);

        $this->assertDatabaseMissing('users', ['email' => 'new.team@example.test']);
    }

    public function test_admin_creation_rejects_existing_email_without_case_sensitivity(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        User::factory()->create(['email' => 'Existing.Admin@example.test']);

        $this->withSession($this->fullAdminSession($actor))
            ->post(route('admin.users.store'), [
                'name' => 'Duplicate Admin',
                'email' => 'existing.admin@example.test',
                'password' => 'Strong#Pas26',
                'password_confirmation' => 'Strong#Pas26',
                'current_admin_password' => 'password',
                'role' => 'limited',
            ])
            ->assertSessionHasErrors(['email' => 'That email address is already in use.'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation')
            ->assertSessionMissing('_old_input.current_admin_password');
    }

    public function test_admin_account_form_uses_consistent_labels_and_the_shared_password_helper(): void
    {
        $actor = User::factory()->create(['role' => 'full', 'name' => 'Primary Admin']);
        User::factory()->create(['role' => 'full']);
        User::factory()->create(['role' => 'limited']);

        $this->withSession($this->fullAdminSession($actor))
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Add Admin')
            ->assertSee('Create Admin')
            ->assertSee('data-password-confirm', false)
            ->assertSee('Security Confirmation')
            ->assertSee('data-password-button="Confirm & Create Admin"', false)
            ->assertSee('Enter your current Primary Admin password to authorize creating this administrator.')
            ->assertSee('data-current-admin-name="Primary Admin"', false)
            ->assertSee('Primary Admin')
            ->assertSee('Team Admin')
            ->assertSee('Team Admins')
            ->assertSee('At least 12 characters. Include uppercase and lowercase letters, a number, and a symbol.')
            ->assertSee('minlength="12"', false)
            ->assertDontSee('At least 8 characters.');
    }

    public function test_admin_creation_requires_the_authenticated_primary_admin_password_and_never_logs_it(): void
    {
        $actor = User::factory()->create(['role' => 'full', 'name' => 'John Dela Cruz']);
        $details = [
            'name' => 'Jane Santos',
            'email' => 'jane.santos@example.test',
            'password' => 'NewAdmin#Pass2026',
            'password_confirmation' => 'NewAdmin#Pass2026',
            'role' => 'limited',
        ];

        $this->withSession($this->fullAdminSession($actor))
            ->from(route('admin.users'))
            ->post(route('admin.users.store'), $details)
            ->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors('current_admin_password');

        $this->assertDatabaseMissing('users', ['email' => $details['email']]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Administrator created']);

        $this->withSession($this->fullAdminSession($actor))
            ->from(route('admin.users'))
            ->post(route('admin.users.store'), $details + ['current_admin_password' => 'incorrect-password'])
            ->assertRedirect(route('admin.users'))
            ->assertSessionHasErrors(['current_admin_password' => 'Primary Admin password is incorrect.'])
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation')
            ->assertSessionMissing('_old_input.current_admin_password');

        $this->assertDatabaseMissing('users', ['email' => $details['email']]);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Administrator created']);

        $this->withSession($this->fullAdminSession($actor))
            ->post(route('admin.users.store'), $details + ['current_admin_password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('_old_input.current_admin_password');

        $this->assertDatabaseHas('users', ['email' => $details['email'], 'role' => 'limited']);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Administrator created',
            'description' => 'Created Team Admin account for Jane Santos (jane.santos@example.test).',
        ]);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'incorrect-password']);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'password']);
    }

    public function test_admin_creation_throttles_incorrect_step_up_password_attempts(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $details = [
            'name' => 'Attempted Admin',
            'email' => 'attempted@example.test',
            'password' => 'NewAdmin#Pass2026',
            'password_confirmation' => 'NewAdmin#Pass2026',
            'role' => 'limited',
            'current_admin_password' => 'incorrect-password',
        ];

        foreach (range(1, 5) as $attempt) {
            $this->withSession($this->fullAdminSession($actor))
                ->from(route('admin.users'))
                ->post(route('admin.users.store'), $details)
                ->assertSessionHasErrors(['current_admin_password' => 'Primary Admin password is incorrect.']);
        }

        $this->withSession($this->fullAdminSession($actor))
            ->from(route('admin.users'))
            ->post(route('admin.users.store'), $details)
            ->assertSessionHasErrors('current_admin_password')
            ->assertSessionHasErrors(['current_admin_password' => 'Too many password confirmation attempts. Please try again in 5 minute(s).']);

        $this->assertDatabaseMissing('users', ['email' => 'attempted@example.test']);
        $this->assertDatabaseMissing('activity_logs', ['action' => 'Administrator created']);
    }

    public function test_team_admin_page_shows_account_status_and_management_actions(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $activeAdmin = User::factory()->create(['role' => 'full', 'is_active' => true]);
        $disabledAdmin = User::factory()->create(['role' => 'limited', 'is_active' => false]);

        $this->withSession($this->fullAdminSession($actor))
            ->get(route('admin.users'))
            ->assertOk()
            ->assertSee('Active')
            ->assertSee('Disabled')
            ->assertSee('Edit name')
            ->assertSee('Enable')
            ->assertSee('Disable')
            ->assertSee($activeAdmin->name)
            ->assertSee($disabledAdmin->name);
    }

    public function test_full_admin_can_disable_and_enable_an_account_without_deleting_it_or_its_history(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited']);
        $history = ActivityLog::create([
            'user_id' => $target->id,
            'actor_name' => $target->name,
            'actor_email' => $target->email,
            'actor_role' => 'limited',
            'action' => 'Previous action',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Historical activity.',
        ]);
        $originalSessionVersion = $target->session_version;

        $this->withSession($this->fullAdminSession($actor))
            ->patch(route('admin.users.status', $target), ['is_active' => '0', 'current_admin_password' => 'password'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $target->id, 'is_active' => false]);
        $this->assertSame($originalSessionVersion + 1, $target->fresh()->session_version);
        $this->assertDatabaseHas('activity_logs', ['id' => $history->id, 'user_id' => $target->id]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Disabled administrator',
            'description' => "{$actor->name} disabled Team Admin {$target->name}.",
        ]);

        $this->withSession($this->fullAdminSession($actor))
            ->patch(route('admin.users.status', $target), ['is_active' => '1', 'current_admin_password' => 'password'])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->is_active);
        $this->assertSame($originalSessionVersion + 1, $target->fresh()->session_version);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Enabled administrator',
        ]);
    }

    public function test_disabled_team_admin_cannot_log_in_but_can_log_in_after_enable(): void
    {
        $admin = User::factory()->create([
            'role' => 'limited',
            'email' => 'disabled.staff@example.test',
            'password' => 'password',
            'is_active' => false,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a Primary Admin.');

        $admin->update(['is_active' => true]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame($admin->id, session('admin_user_id'));
    }

    public function test_active_database_primary_admin_can_log_in_with_its_hashed_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'email' => 'active.primary@example.test',
            'password' => 'secure-database-password',
            'is_active' => true,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'secure-database-password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame($admin->id, session('admin_user_id'));
        $this->assertSame('full', session('admin_role'));
    }

    public function test_disabled_database_primary_admin_cannot_log_in(): void
    {
        $email = 'disabled.primary@example.test';
        $password = 'disabled-primary-secret';
        User::factory()->create([
            'role' => 'full',
            'email' => $email,
            'password' => $password,
            'is_active' => false,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $email,
            'password' => $password,
        ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a Primary Admin.');
    }

    public function test_disabled_primary_admin_can_log_in_again_after_being_reenabled(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => true]);
        $admin = User::factory()->create([
            'role' => 'full',
            'email' => 'reenabled.primary@example.test',
            'password' => 'primary-reenable-secret',
            'is_active' => false,
        ]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'primary-reenable-secret',
        ])->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a Primary Admin.');

        $admin->update(['is_active' => true]);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'primary-reenable-secret',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($admin->id, session('admin_user_id'));
    }

    public function test_one_primary_admin_can_disable_another_while_a_primary_remains_active(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'full']);

        $this->withSession($this->fullAdminSession($actor))
            ->patch(route('admin.users.status', $target), ['is_active' => '0', 'current_admin_password' => 'password'])
            ->assertSessionHas('success', 'Administrator account disabled successfully.');

        $this->assertFalse($target->fresh()->is_active);
        $this->assertTrue($actor->fresh()->is_active);
    }

    public function test_disabled_logged_in_admin_is_logged_out_on_the_next_protected_request(): void
    {
        $admin = User::factory()->create(['role' => 'limited']);
        $admin->update(['is_active' => false]);

        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
            'admin_user_id' => $admin->id,
            'admin_name' => $admin->name,
            'admin_email' => $admin->email,
        ])->get(route('admin.dashboard'))
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator account has been disabled. Please contact a Primary Admin.');

        $this->assertNull(session('is_admin'));
    }

    public function test_full_admin_can_edit_name_and_change_is_audited_without_rewriting_history(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited', 'name' => 'Old Name']);
        $previousHistory = ActivityLog::create([
            'user_id' => $target->id,
            'actor_name' => 'Old Name',
            'actor_email' => $target->email,
            'actor_role' => 'limited',
            'action' => 'Previous action',
            'activity_date' => now()->toDateString(),
            'activity_time' => now()->toTimeString(),
            'description' => 'Logged while the admin used the old name.',
        ]);

        $this->withSession($this->fullAdminSession($actor))
            ->put(route('admin.users.update-name', $target), ['name' => '  Jane Dela Cruz  '])
            ->assertRedirect();

        $this->assertSame('Jane Dela Cruz', $target->fresh()->name);
        $this->assertSame('Old Name', $previousHistory->fresh()->actor_name);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Changed administrator name',
            'description' => "Changed Team Admin name from 'Old Name' to 'Jane Dela Cruz'.",
        ]);
    }

    public function test_full_admin_can_reset_a_team_password_and_revoke_its_existing_sessions(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited', 'password' => 'PreviousPassword#2026']);
        $version = $target->session_version;

        $this->withSession($this->fullAdminSession($actor))
            ->put(route('admin.users.reset', $target), [
                'current_admin_password' => 'password',
                'password' => 'NewTeamPassword#2026',
                'password_confirmation' => 'NewTeamPassword#2026',
            ])
            ->assertSessionHas('success', 'Password updated for '.$target->name.'.');

        $this->assertTrue(Hash::check('NewTeamPassword#2026', $target->fresh()->password));
        $this->assertSame($version + 1, $target->fresh()->session_version);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $actor->id,
            'action' => 'Changed administrator password',
            'description' => 'Updated administrator password. Existing sessions were revoked.',
        ]);
    }

    public function test_name_validation_rejects_empty_and_invalid_input(): void
    {
        $actor = User::factory()->create(['role' => 'full']);
        $target = User::factory()->create(['role' => 'limited']);

        foreach (['', '   ', '<script>alert(1)</script>'] as $name) {
            $this->withSession($this->fullAdminSession($actor))
                ->put(route('admin.users.update-name', $target), ['name' => $name])
                ->assertSessionHasErrors('name');
        }
    }

    public function test_admin_cannot_disable_themselves_or_the_last_active_primary_admin(): void
    {
        $onlyPrimaryAdmin = User::factory()->create(['role' => 'full']);

        $this->withSession($this->fullAdminSession($onlyPrimaryAdmin))
            ->patch(route('admin.users.status', $onlyPrimaryAdmin), ['is_active' => '0', 'current_admin_password' => 'password'])
            ->assertSessionHas('error', 'Cannot disable the last active Primary Admin. At least one active Primary Admin is required.');
        $onlyPrimaryAdmin->update(['is_active' => false]);

        $target = User::factory()->create(['role' => 'full']);

        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => null,
            'admin_email' => 'primary@example.test',
            'admin_name' => 'Primary Administrator',
        ])
            ->patch(route('admin.users.status', $target), ['is_active' => '0', 'current_admin_password' => 'some-password'])
            ->assertRedirect(route('admin.login'))
            ->assertSessionHas('error', 'Your administrator session has expired. Please sign in again.');

        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_limited_admin_cannot_manage_admin_accounts(): void
    {
        $limitedAdmin = User::factory()->create(['role' => 'limited']);
        $target = User::factory()->create(['role' => 'limited']);

        $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
            'admin_user_id' => $limitedAdmin->id,
            'admin_email' => $limitedAdmin->email,
        ])->put(route('admin.users.update-name', $target), ['name' => 'Changed'])
            ->assertForbidden();

        $this->post(route('admin.users.store'), [
            'name' => 'Unauthorized Admin',
            'email' => 'unauthorized@example.test',
            'password' => 'Strong#Pas26',
            'password_confirmation' => 'Strong#Pas26',
            'current_admin_password' => 'password',
            'role' => 'full',
        ])->assertForbidden();

        $this->patch(route('admin.users.status', $target), ['is_active' => '0'])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized@example.test']);
    }

    private function fullAdminSession(?User $admin = null): array
    {
        return [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_user_id' => $admin?->id,
            'admin_name' => $admin?->name ?? 'Primary Admin',
            'admin_email' => $admin?->email ?? 'primary@example.test',
            'admin_session_version' => $admin?->session_version ?? 0,
        ];
    }
}
