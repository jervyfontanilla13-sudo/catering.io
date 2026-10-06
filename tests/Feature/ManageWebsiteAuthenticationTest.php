<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManageWebsiteAuthenticationTest extends TestCase
{
    public function test_website_sections_are_grouped_under_the_password_protected_sidebar_dropdown(): void
    {
        $response = $this->withSession($this->adminSession())->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Manage Website')
            ->assertSee('Packages')
            ->assertSee('Services')
            ->assertSee('Gallery')
            ->assertSee('id="manageWebsiteSubnav"', false)
            ->assertSee('id="manageWebsiteAuthForm"', false)
            ->assertSee('Unlock Manage Website')
            ->assertSee('data-manage-website-unlocked="false"', false);
    }

    public function test_direct_access_to_every_website_section_requires_reauthentication(): void
    {
        foreach (['admin.packages.index', 'admin.services.index', 'admin.gallery.index'] as $routeName) {
            $response = $this->withSession($this->adminSession())->get(route($routeName));

            $response->assertRedirect(route('admin.dashboard'));
            $this->get(route('admin.dashboard'))
                ->assertOk()
                ->assertSee('manageWebsiteAuthForm', false)
                ->assertSee('requestSubmit()', false);
        }
    }

    public function test_incorrect_password_keeps_website_sections_locked(): void
    {
        $response = $this->withSession($this->adminSession())
            ->from(route('admin.dashboard'))
            ->post(route('admin.manage-website.reauthenticate'), [
                'current_admin_password' => 'incorrect-password',
                'return_to' => '/admin/',
            ]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors('manage_website_password');

        $this->get(route('admin.packages.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_correct_password_unlocks_all_website_sections_without_reauthentication(): void
    {
        $authenticationStartedAt = now()->timestamp;
        $response = $this->withSession($this->adminSession())
            ->post(route('admin.manage-website.reauthenticate'), [
                'current_admin_password' => 'the-correct-password',
                'return_to' => '/admin/',
            ]);

        $response->assertRedirect(route('admin.dashboard'))
            ->assertSessionHas('manage_website_auth_expires_at', $authenticationStartedAt + 15 * 60);

        foreach (['admin.packages.index', 'admin.services.index', 'admin.gallery.index'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_expired_manage_website_authentication_requires_reauthentication_again(): void
    {
        $session = $this->manageWebsiteSession($this->adminSession(), now()->subSecond()->timestamp);

        $this->withSession($session)
            ->get(route('admin.services.index'))
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_logout_clears_manage_website_authentication(): void
    {
        $this->withSession($this->manageWebsiteSession($this->adminSession(), now()->addHour()->timestamp))
            ->post(route('admin.logout'))
            ->assertRedirect(route('admin.login'));

        $this->get(route('admin.gallery.index'))
            ->assertRedirect(route('admin.login'));
    }

    private function adminSession(): array
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('the-correct-password'),
        ]);

        return [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_auth_source' => 'database',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
        ];
    }

    private function manageWebsiteSession(array $adminSession, int $expiresAt): array
    {
        return $adminSession + [
            'manage_website_auth_user_id' => $adminSession['admin_user_id'],
            'manage_website_auth_source' => 'database',
            'manage_website_auth_email' => strtolower((string) $adminSession['admin_email']),
            'manage_website_auth_expires_at' => $expiresAt,
        ];
    }
}
