<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PrimaryAdminSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('admin.primary_admin_setup_key', 'TestSetupKey-OnlyForFeatureTests#2026');
    }

    public function test_fresh_install_allows_first_time_setup(): void
    {
        $this->get('/admin')
            ->assertRedirect(route('admin.setup'));

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Create Primary Admin')
            ->assertSee('At least 12 characters. Include uppercase and lowercase letters, a number, and a symbol.')
            ->assertDontSee('beneficiary')
            ->assertSee('keep the password private');

        $this->get(route('admin.login'))
            ->assertOk()
            ->assertSee(route('admin.setup'))
            ->assertSee('Primary Administrator Setup Required')
            ->assertSee('Primary Administrator setup is required.')
            ->assertSee('Set Up Primary Administrator')
            ->assertDontSee('No active Primary Administrator is set up yet. The beneficiary can create the account for this system.');
    }

    public function test_first_run_setup_flow_completes_and_normal_admin_login_resumes(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.setup'));

        $this->post(route('admin.setup.store'), [
            'setup_key' => $this->setupKey(),
            'name' => 'First Primary Admin',
            'email' => 'first.primary@example.test',
            'password' => 'FirstPrimarySecret#2026',
            'password_confirmation' => 'FirstPrimarySecret#2026',
        ])->assertRedirect(route('admin.login'));

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Admin setup has already been completed.')
            ->assertDontSee('name="password"', false);

        $admin = User::where('email', 'first.primary@example.test')->firstOrFail();
        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'FirstPrimarySecret#2026',
        ])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('Set Up Primary Administrator');
        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Admin setup has already been completed.')
            ->assertDontSee('name="password"', false);
    }

    public function test_existing_active_primary_admin_is_sent_to_login_not_first_run_setup(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => true]);

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('Set Up Primary Administrator');
    }

    public function test_disabled_primary_admin_does_not_reenable_first_run_setup(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => false]);

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get(route('admin.login'))
            ->assertOk()
            ->assertDontSee('Set Up Primary Administrator');
        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Admin setup has already been completed.')
            ->assertDontSee('name="password"', false);
    }

    public function test_team_admin_cannot_access_first_run_setup(): void
    {
        $teamAdmin = User::factory()->create(['role' => 'limited', 'is_active' => true]);
        $session = [
            'is_admin' => true,
            'admin_role' => 'limited',
            'admin_user_id' => $teamAdmin->id,
            'admin_session_version' => $teamAdmin->session_version,
        ];

        $this->withSession($session)->get(route('admin.setup'))->assertForbidden();
        $this->withSession($session)->post(route('admin.setup.store'), [
            'setup_key' => $this->setupKey(),
            'name' => 'Unauthorized Primary Admin',
            'email' => 'unauthorized.primary@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized.primary@example.test']);
    }

    public function test_setup_rejects_a_concurrent_attempt_while_another_setup_holds_the_lock(): void
    {
        $lock = Cache::lock('3yos-primary-admin-initial-setup', 30);
        $this->assertTrue($lock->get());

        try {
            $this->from(route('admin.setup'))
                ->post(route('admin.setup.store'), [
                    'setup_key' => $this->setupKey(),
                    'name' => 'Concurrent Admin',
                    'email' => 'concurrent.admin@example.test',
                    'password' => 'ConcurrentSecret#2026',
                    'password_confirmation' => 'ConcurrentSecret#2026',
                ])
                ->assertRedirect(route('admin.setup'))
                ->assertSessionHas('error', 'Primary Admin setup is busy. Please try again.');
        } finally {
            $lock->release();
        }

        $this->assertDatabaseCount('users', 0);
    }

    public function test_beneficiary_can_create_and_then_log_in_as_the_database_primary_admin(): void
    {
        $response = $this->post(route('admin.setup.store'), [
            'setup_key' => $this->setupKey(),
            'name' => '  Beneficiary Admin  ',
            'email' => '  BENEFICIARY@example.test ',
            'password' => 'BeneficiarySecret#2026',
            'password_confirmation' => 'BeneficiarySecret#2026',
        ]);

        $response->assertRedirect(route('admin.login'));
        $response->assertSessionHas('success', 'Primary Admin created successfully. Please sign in.');

        $admin = User::where('email', 'beneficiary@example.test')->firstOrFail();
        $this->assertSame('Beneficiary Admin', $admin->name);
        $this->assertSame('full', $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('BeneficiarySecret#2026', $admin->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'Primary Admin account created',
        ]);
        $this->assertDatabaseMissing('activity_logs', ['description' => 'BeneficiarySecret#2026']);

        $this->post(route('admin.login.post'), [
            'email' => $admin->email,
            'password' => 'BeneficiarySecret#2026',
        ])->assertRedirect(route('admin.dashboard'));
        $this->assertSame($admin->id, session('admin_user_id'));
    }

    public function test_setup_is_locked_after_an_active_primary_admin_exists(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => true]);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Admin setup has already been completed.')
            ->assertSee('Go to Admin Login')
            ->assertDontSee('name="password"', false);

        $this->post(route('admin.setup.store'), [
            'setup_key' => $this->setupKey(),
            'name' => 'Unexpected Admin',
            'email' => 'unexpected@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertRedirect(route('admin.setup'))
            ->assertSessionHas('error', 'Primary Admin setup has already been completed.');

        $this->assertDatabaseMissing('users', ['email' => 'unexpected@example.test']);
    }

    public function test_setup_stays_locked_after_a_primary_account_is_disabled(): void
    {
        User::factory()->create(['role' => 'full', 'is_active' => false]);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Admin setup has already been completed.')
            ->assertDontSee('name="password"', false);
    }

    public function test_setup_validates_name_email_password_and_confirmation(): void
    {
        $this->from(route('admin.setup'))
            ->post(route('admin.setup.store'), [
                'setup_key' => $this->setupKey(),
                'name' => '   ',
                'email' => 'not-an-email',
                'password' => 'weak',
                'password_confirmation' => 'different',
            ])
            ->assertRedirect(route('admin.setup'))
            ->assertSessionHasErrors(['name', 'email', 'password']);
    }

    public function test_setup_rejects_case_insensitive_duplicate_email(): void
    {
        User::factory()->create(['role' => 'limited', 'email' => 'Existing.Admin@example.test']);

        $this->from(route('admin.setup'))
            ->post(route('admin.setup.store'), [
                'setup_key' => $this->setupKey(),
                'name' => 'Beneficiary Admin',
                'email' => 'existing.admin@example.test',
                'password' => 'BeneficiarySecret#2026',
                'password_confirmation' => 'BeneficiarySecret#2026',
            ])
            ->assertRedirect(route('admin.setup'))
            ->assertSessionHasErrors('email');
    }

    public function test_setup_requires_the_configured_one_time_key(): void
    {
        $this->get(route('admin.setup'))->assertSee('One-time Setup Key');

        $this->post(route('admin.setup.store'), [
            'setup_key' => 'incorrect-key',
            'name' => 'Untrusted Admin',
            'email' => 'untrusted@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertRedirect(route('admin.setup'))
            ->assertSessionHas('error', 'The setup key is invalid.');

        $this->assertDatabaseMissing('users', ['email' => 'untrusted@example.test']);
    }

    public function test_setup_form_is_unavailable_without_a_configured_key(): void
    {
        config()->set('admin.primary_admin_setup_key', null);

        $this->get(route('admin.setup'))
            ->assertOk()
            ->assertSee('Primary Admin setup is not available.')
            ->assertDontSee('name="setup_key"', false);

        $this->post(route('admin.setup.store'), [
            'name' => 'Unconfigured Admin',
            'email' => 'unconfigured@example.test',
            'password' => 'AnotherSecret#2026',
            'password_confirmation' => 'AnotherSecret#2026',
        ])->assertRedirect(route('admin.setup'))
            ->assertSessionHas('error', 'Primary Admin setup is not available. Contact the system administrator.');

        $this->assertDatabaseMissing('users', ['email' => 'unconfigured@example.test']);
    }

    private function setupKey(): string
    {
        return 'TestSetupKey-OnlyForFeatureTests#2026';
    }
}
