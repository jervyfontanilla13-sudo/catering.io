<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminHeaderTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    public function test_header_shows_current_admin_and_date_while_sidebar_contains_theme_and_logout_controls(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::create(2026, 10, 6, 10, 30, 0, 'Asia/Manila'));
        $response = $this->withSession(self::ADMIN + ['admin_name' => 'John Doe'])->get(route('admin.reservations'));

        $response->assertOk();
        $response->assertSee('<header class="header-bar"', false);
        $response->assertSee('Welcome, <span class="admin-header-name">John Doe</span><span class="admin-header-date"> | October 6, 2026</span>', false);
        $response->assertSee('Manage and review catering reservations');
        $response->assertDontSee('Catering management');
        $response->assertSee('id="themeToggle"', false);
        $response->assertSee('aria-label="Enable dark mode"', false);
        $response->assertSee('Dark Mode');
        $response->assertSee('Sign Out');
        $response->assertSee('href="'.route('home').'" class="header-btn">View website', false);
        $response->assertSee('action="'.route('admin.logout').'"', false);
        $response->assertSee('name="_token"', false);

        $content = $response->getContent();
        $headerStart = strpos($content, '<header class="header-bar"');
        $headerEnd = strpos($content, '</header>', $headerStart);
        $sidebarEnd = strpos($content, '</aside>');
        $this->assertNotFalse($headerStart);
        $this->assertNotFalse($headerEnd);
        $this->assertGreaterThan($sidebarEnd, $headerStart);
        $header = substr($content, $headerStart, $headerEnd - $headerStart);
        $sidebar = substr($content, 0, $sidebarEnd);
        $this->assertStringNotContainsString('themeToggle', $header);
        $this->assertStringNotContainsString('Sign Out', $header);
        $this->assertStringNotContainsString('Sign out', $header);
        $this->assertStringContainsString('themeToggle', $sidebar);
        $this->assertStringContainsString('Sign Out', $sidebar);
        $this->assertStringNotContainsString('View website', substr($content, 0, $sidebarEnd));
        $this->assertStringContainsString('localStorage.getItem(\'admin-theme\')', $content);
        $this->assertStringContainsString('localStorage.setItem(\'admin-theme\'', $content);
        $this->assertSame('Asia/Manila', config('app.timezone'));
        Carbon::setTestNow();
    }

    public function test_admin_header_scrolls_naturally_with_main_content(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations'));

        $response->assertOk()
            ->assertSee('<header class="header-bar">', false)
            ->assertSee('<div class="admin-page-content">', false)
            ->assertDontSee('data-scroll-header', false)
            ->assertDontSee('js/scroll-header.js', false)
            ->assertDontSee('css/scroll-header.css', false);

        $styles = file_get_contents(public_path('css/admin-workspace.css'));
        $this->assertIsString($styles);
        $headerStyles = substr(
            $styles,
            strpos($styles, '.header-bar {'),
            strpos($styles, '.admin-heading {') - strpos($styles, '.header-bar {')
        );
        $this->assertStringContainsString('position: relative;', $headerStyles);
        $this->assertStringContainsString('margin: var(--shell-inset) var(--shell-inset) var(--section-gap);', $headerStyles);
        $this->assertStringNotContainsString('position: fixed', $headerStyles);
        $this->assertStringNotContainsString('position: sticky', $headerStyles);
        $this->assertStringContainsString('.admin-layout { display: flex; width: 100%; height: 100vh;', $styles);
        $this->assertStringContainsString('height: 100vh; margin-left: calc(var(--sidebar-w) + var(--shell-inset) * 2); overflow-x: hidden; overflow-y: auto;', $styles);
        $this->assertStringContainsString('margin: 0 auto; padding: 0 var(--shell-inset) 40px;', $styles);
        $this->assertStringContainsString('@media (max-width: 991.98px)', $styles);
        $this->assertStringContainsString('.header-bar { margin: 10px 12px 16px; }', $styles);
        $response->assertDontSee('ResizeObserver', false);
    }

    public function test_header_name_tracks_the_current_database_user_name(): void
    {
        $user = User::create([
            'name' => 'Current Account Name',
            'email' => 'header-name@example.com',
            'password' => 'test-password',
            'role' => 'full',
            'is_active' => true,
        ]);
        $session = [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_auth_source' => 'database',
            'admin_user_id' => $user->id,
            'admin_session_version' => $user->session_version,
            'admin_name' => 'Stale Session Name',
        ];

        $this->withSession($session)
            ->get(route('admin.reservations'))
            ->assertOk()
            ->assertSee('Welcome, <span class="admin-header-name">Current Account Name</span>', false)
            ->assertDontSee('Stale Session Name');

        $user->update(['name' => 'Updated Account Name']);

        $this->withSession($session)
            ->get(route('admin.reservations'))
            ->assertOk()
            ->assertSee('Welcome, <span class="admin-header-name">Updated Account Name</span>', false)
            ->assertDontSee('Current Account Name');
    }
}
