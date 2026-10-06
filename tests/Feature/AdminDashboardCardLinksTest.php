<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Reservation;
use Tests\TestCase;

class AdminDashboardCardLinksTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Card Package', 'slug' => 'card-'.uniqid(), 'price' => 900, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Card Link Client',
            'contact_number' => '09171234567',
            'email' => 'card-'.uniqid().'@example.com',
            'address' => '1 Card Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Card Hall',
            'guest_count' => 50,
            'estimated_budget' => 45000,
            'status' => 'pending',
            'reservation_code' => 'RES-CARD'.random_int(100000, 999999),
        ]);
    }

    public function test_events_today_card_links_to_the_scheduled_today_filtered_list(): void
    {
        $today = $this->reservation(['event_date' => now()->toDateString(), 'full_name' => 'Today Guest']);
        $this->reservation(['event_date' => now()->addDays(3)->toDateString(), 'full_name' => 'Later Guest']);

        $dashboard = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $expectedHref = route('admin.reservations', ['scope' => 'scheduled', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]);
        $dashboard->assertSee(htmlspecialchars($expectedHref), false);

        $list = $this->withSession(self::ADMIN)->get($expectedHref);
        $list->assertOk();
        $list->assertSee('Today Guest');
        $list->assertDontSee('Later Guest');
    }

    public function test_upcoming_card_links_to_the_next_seven_days_filtered_list_excluding_today(): void
    {
        $this->reservation(['event_date' => now()->toDateString(), 'full_name' => 'Today Guest']);
        $upcoming = $this->reservation(['event_date' => now()->addDays(3)->toDateString(), 'full_name' => 'Upcoming Guest']);
        $this->reservation(['event_date' => now()->addDays(20)->toDateString(), 'full_name' => 'Far Future Guest']);

        $dashboard = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $expectedHref = route('admin.reservations', [
            'scope' => 'scheduled',
            'date_from' => now()->addDay()->toDateString(),
            'date_to' => now()->addDays(7)->toDateString(),
        ]);
        $dashboard->assertSee(htmlspecialchars($expectedHref), false);

        $list = $this->withSession(self::ADMIN)->get($expectedHref);
        $list->assertOk();
        $list->assertSee('Upcoming Guest');
        $list->assertDontSee('Today Guest');
        $list->assertDontSee('Far Future Guest');
    }

    public function test_scheduled_scope_excludes_cancelled_and_completed_even_within_the_date_range(): void
    {
        $this->reservation(['event_date' => now()->toDateString(), 'status' => 'cancelled', 'full_name' => 'Cancelled Guest']);
        $pending = $this->reservation(['event_date' => now()->toDateString(), 'status' => 'pending', 'full_name' => 'Pending Guest']);

        $href = route('admin.reservations', ['scope' => 'scheduled', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]);
        $list = $this->withSession(self::ADMIN)->get($href);

        $list->assertOk();
        $list->assertSee('Pending Guest');
        $list->assertDontSee('Cancelled Guest');
    }

    public function test_payments_due_soon_card_count_matches_its_filtered_list(): void
    {
        $dueSoon = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 50000,
            'payment_due_date' => now()->addDays(3)->toDateString(),
            'full_name' => 'Due Soon Guest',
        ]);
        $notDueSoon = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 50000,
            'payment_due_date' => now()->addDays(30)->toDateString(),
            'full_name' => 'Not Due Soon Guest',
        ]);

        $dashboard = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('1', false);
        $expectedHref = route('admin.reservations', ['payment_due' => 'soon']);
        $dashboard->assertSee($expectedHref, false);

        $list = $this->withSession(self::ADMIN)->get($expectedHref);
        $list->assertOk();
        $list->assertSee('Due Soon Guest');
        $list->assertDontSee('Not Due Soon Guest');
    }

    public function test_payments_due_soon_excludes_balances_already_settled(): void
    {
        $reservation = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 50000,
            'payment_due_date' => now()->addDays(3)->toDateString(),
            'full_name' => 'Settled Guest',
        ]);
        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Full Payment',
            'amount' => 50000,
            'payment_method' => 'Cash',
        ]);

        $href = route('admin.reservations', ['payment_due' => 'soon']);
        $list = $this->withSession(self::ADMIN)->get($href);

        $list->assertOk();
        $list->assertDontSee('Settled Guest');
    }

    public function test_needs_attention_reservation_links_match_the_exact_live_financial_and_contract_filters(): void
    {
        $unpaid = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 5000,
            'payment_status' => 'Fully Paid',
            'full_name' => 'Unpaid Attention Guest',
        ]);
        $unpaid->update(['service_contract' => 'service-contracts/current-contract.png']);

        $paid = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 5000,
            'payment_status' => 'Unpaid',
            'full_name' => 'Paid Guest',
        ]);
        $paid->update(['service_contracts' => ['service-contracts/current-contract.png']]);
        $paid->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Full Payment',
            'amount' => 5000,
            'payment_method' => 'Cash',
        ]);

        $missingContract = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => null,
            'payment_status' => 'Downpayment',
            'full_name' => 'Missing Contract Guest',
        ]);

        $outstanding = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 5000,
            'service_contracts' => ['service-contracts/current-contract.png'],
            'full_name' => 'Outstanding Guest',
        ]);
        $outstanding->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 1000,
            'payment_method' => 'Cash',
        ]);

        $this->reservation([
            'status' => 'cancelled',
            'total_cost' => 5000,
            'full_name' => 'Closed Unpaid Guest',
        ]);

        $noPaymentList = $this->withSession(self::ADMIN)->get(route('admin.reservations', [
            'status' => 'confirmed',
            'attention' => 'no_payment',
        ]));
        $noPaymentList->assertOk();
        $noPaymentList->assertSee('Unpaid Attention Guest');
        $noPaymentList->assertSee('Missing Contract Guest');
        $noPaymentList->assertDontSee('Paid Guest');
        $noPaymentList->assertDontSee('Closed Unpaid Guest');

        $missingContractList = $this->withSession(self::ADMIN)->get(route('admin.reservations', [
            'status' => 'confirmed',
            'attention' => 'missing_contract',
        ]));
        $missingContractList->assertOk();
        $missingContractList->assertSee('Missing Contract Guest');
        $missingContractList->assertDontSee('Paid Guest');
        $missingContractList->assertDontSee('Outstanding Guest');

        $outstandingList = $this->withSession(self::ADMIN)->get(route('admin.reservations', [
            'status' => 'confirmed',
            'attention' => 'outstanding_balance',
        ]));
        $outstandingList->assertOk();
        $outstandingList->assertSee('Outstanding Guest');
        $outstandingList->assertDontSee('Paid Guest');
        $outstandingList->assertDontSee('Missing Contract Guest');
    }

    public function test_inquiries_needing_response_card_links_to_needs_attention_view(): void
    {
        Inquiry::create([
            'full_name' => 'Needs Response Client',
            'contact_number' => '09171234567',
            'email' => 'needsresponse@example.com',
            'subject' => 'Question about catering',
            'category' => 'General',
            'message' => 'Hello',
            'status' => 'new',
        ]);
        Inquiry::create([
            'full_name' => 'Already Handled Client',
            'contact_number' => '09171234567',
            'email' => 'handled@example.com',
            'subject' => 'Thanks',
            'category' => 'General',
            'message' => 'Thanks for the info',
            'status' => 'responded',
            'admin_reply' => 'You are welcome.',
            'replied_at' => now(),
        ]);

        $dashboard = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $expectedHref = route('admin.inquiries', ['view' => 'needs_attention']);
        $dashboard->assertSee($expectedHref, false);

        $list = $this->withSession(self::ADMIN)->get($expectedHref);
        $list->assertOk();
        $list->assertSee('Needs Response Client');
    }

    public function test_zero_count_cards_are_still_clickable_and_show_an_empty_state(): void
    {
        $dashboard = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee(route('admin.reservations', ['payment_due' => 'soon']), false);
        $dashboard->assertSee(route('admin.inquiries', ['view' => 'needs_attention']), false);

        $list = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['view' => 'needs_attention']));
        $list->assertOk();
        $list->assertSee('No inquiries match these filters');
    }

    public function test_dashboard_card_destinations_still_require_admin_authentication(): void
    {
        $this->get(route('admin.reservations', ['scope' => 'scheduled']))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reservations', ['payment_due' => 'soon']))->assertRedirect(route('admin.login'));
        $this->get(route('admin.inquiries', ['view' => 'needs_attention']))->assertRedirect(route('admin.login'));
    }
}
