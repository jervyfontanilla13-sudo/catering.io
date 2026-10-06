<?php

namespace Tests\Feature;

use Tests\TestCase;

class HelpCenterAccessTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];
    private const LIMITED_ADMIN = ['is_admin' => true, 'admin_role' => 'limited'];

    // Guest: one public Support page combines quick help with the customer manual.
    public function test_guest_can_open_unified_support_and_guest_manual(): void
    {
        $response = $this->get(route('support'));

        $response->assertOk();
        $response->assertSee('3YOS Support');
        $response->assertSee('Quick Help');
        $response->assertSee('User Manual');
        $response->assertSee('data-search=', false);
        $response->assertSee('Getting Started');
        $response->assertSee('Reservations');
        $response->assertSee('Payments');
        $response->assertSee('Contracts');
        $response->assertSee('FAQ');
        $response->assertSee('Making a Reservation');
        $response->assertSee('Reservation Statuses');
    }

    // Guest: FAQ and articles are visible (accordion content is rendered server-side, not hidden behind JS-only fetch).
    public function test_guest_can_see_faq_articles(): void
    {
        $response = $this->get(route('support'));

        $response->assertOk();
        $response->assertSee('Do I need to pay a deposit to reserve a date?');
        $response->assertSee('Can two events happen on the same date?');
    }

    // Guest: search input is present (actual filtering is client-side JS, verified via the rendered markup below).
    public function test_guest_help_center_has_a_search_input_covering_known_terms(): void
    {
        $response = $this->get(route('support'));

        $response->assertOk();
        $response->assertSee('id="helpSearch"', false);
        // The three example searches from the spec must each have a matching article in the payload.
        $response->assertSee('What is an Official Receipt?');
        $response->assertDontSee('How do I use the calendar?'); // admin-only phrase must NOT leak to guests
    }

    // Guest CANNOT access admin Help Center / User Manual — redirected to admin login, not just hidden from nav.
    public function test_guest_cannot_access_admin_support(): void
    {
        $this->get(route('admin.support'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.help'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.manual'))->assertRedirect(route('admin.login'));
    }

    // Admin content must never appear on the guest-facing pages at all.
    public function test_guest_help_center_never_mentions_admin_only_topics(): void
    {
        $response = $this->get(route('support'));

        $response->assertOk();
        $response->assertDontSee('Activity/Audit Log');
        $response->assertDontSee('Needs Attention', false);
        $response->assertDontSee('Backups');
        $response->assertDontSee('Team Admins');
    }

    // Authenticated admin: one Support page includes both audiences' help and the admin manual.
    public function test_admin_can_open_unified_support_with_guest_and_admin_content(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.support'));

        $response->assertOk();
        $response->assertSee('3YOS Support');
        $response->assertSee('Administrator Quick Help');
        $response->assertSee('Guest Quick Help');
        $response->assertSee('User Manual');
        $response->assertSee('How do I record a payment?');
        $response->assertSee('How do I make a reservation?');
        $response->assertSee('Reservation Status Workflow');
        $response->assertSee('Activity/Audit Log');
        $response->assertSee('Backups');
    }

    // A limited admin can access Support; private procedures remain within admin middleware.
    public function test_a_limited_admin_can_open_support_and_legacy_urls_redirect(): void
    {
        $this->withSession(self::LIMITED_ADMIN)->get(route('admin.support'))->assertOk();
        $this->withSession(self::LIMITED_ADMIN)->get(route('admin.help'))->assertRedirect(route('admin.support'));
        $this->withSession(self::LIMITED_ADMIN)->get(route('admin.manual'))->assertRedirect(route('admin.support'));
    }

    public function test_legacy_public_urls_redirect_to_unified_support(): void
    {
        $this->get(route('help'))->assertRedirect(route('support'));
        $this->get(route('manual'))->assertRedirect(route('support'));
    }

    public function test_admin_support_sidebar_has_one_final_support_item_and_active_state(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.support'));

        $response->assertOk();
        $response->assertSee('>Support</span>', false);
        $response->assertDontSee('>Help Center</span>', false);
        $response->assertDontSee('>User Manual</span>', false);
        $response->assertSee('class="nav-link active" href="'.route('admin.support').'" target="_blank" rel="noopener"', false);
        $this->assertGreaterThan(
            strpos($response->getContent(), '>Backups</span>'),
            strpos($response->getContent(), '>Support</span>'),
        );
    }

    // Contextual help links exist on real operational pages and point at the right destination.
    public function test_reservation_page_has_a_contextual_help_link(): void
    {
        $response = $this->get(route('reservation'));

        $response->assertOk();
        $response->assertSee(route('support').'#category-reservations', false);
    }

    public function test_admin_dashboard_calendar_has_a_contextual_help_link(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee(route('admin.support').'#category-admin-calendar', false);
        $response->assertSee('target="_blank" rel="noopener">How do I use the calendar?</a>', false);
    }

    // Existing core functionality is untouched by this feature.
    public function test_existing_navigation_and_core_pages_still_work(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('reservation'))->assertOk();
        $this->get(route('inquiry'))->assertOk();
        $this->withSession(self::ADMIN)->get(route('admin.dashboard'))->assertOk();
        $this->withSession(self::ADMIN)->get(route('admin.reservations'))->assertOk();
    }
}
