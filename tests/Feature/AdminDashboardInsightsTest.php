<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminDashboardInsightsTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Dashboard Package', 'slug' => 'dashboard-'.uniqid(), 'price' => 900, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Dashboard Client',
            'contact_number' => '09171234567',
            'email' => 'dashboard-'.uniqid().'@example.com',
            'address' => '1 Dashboard Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Dashboard Hall',
            'guest_count' => 50,
            'estimated_budget' => 45000,
            'status' => 'pending',
            'reservation_code' => 'RES-DASH'.random_int(100000, 999999),
        ]);
    }

    public function test_dashboard_shows_nothing_needs_attention_when_clean(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Needs Attention');
        $response->assertSee('Nothing needs attention right now.');
    }

    public function test_pending_reservation_appears_in_needs_attention_and_links_to_filtered_list(): void
    {
        $this->reservation(['status' => 'pending']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Pending reservations need a decision');
        $response->assertSee('1 OPEN ITEMS');
        $response->assertSee(route('admin.reservations', ['status' => 'pending']), false);
    }

    public function test_inquiry_needing_response_appears_in_needs_attention_and_today(): void
    {
        Inquiry::create([
            'full_name' => 'Dashboard Inquirer',
            'contact_number' => '09171234567',
            'email' => 'inquirer@example.com',
            'subject' => 'Question',
            'category' => 'General',
            'message' => 'Hello',
            'status' => 'new',
        ]);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Inquiries need a response');
        $response->assertSee('Inquiries needing response');
    }

    public function test_accepted_reservation_with_no_payment_appears_as_unpaid_accepted(): void
    {
        $this->reservation(['status' => 'confirmed', 'total_cost' => 45000]);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Accepted reservations with no payment on file');
    }

    public function test_accepted_attention_counts_use_net_financials_and_exclude_closed_or_unactionable_reservations(): void
    {
        $fullyRefunded = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 1000,
            'payment_status' => 'Fully Paid',
            'full_name' => 'Fully Refunded Guest',
        ]);
        $fullyRefunded->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Full Payment',
            'amount' => 1000,
            'payment_method' => 'Cash',
        ]);
        $fullyRefunded->refunds()->create([
            'refund_date' => now()->toDateString(),
            'amount' => 1000,
            'refund_method' => 'Cash',
            'status' => 'completed',
            'request_key' => (string) Str::uuid(),
        ]);

        $paidWithCurrentContract = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 1000,
            'payment_status' => 'Unpaid',
            'amount_paid' => 0,
            'service_contracts' => ['service-contracts/current-contract.png'],
            'full_name' => 'Paid Contract Guest',
        ]);
        $paidWithCurrentContract->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Full Payment',
            'amount' => 1000,
            'payment_method' => 'Cash',
        ]);

        $noContractPrice = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => null,
            'full_name' => 'No Contract Price Guest',
        ]);
        $zeroContractPrice = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 0,
            'full_name' => 'Zero Contract Price Guest',
        ]);
        $this->reservation(['status' => 'completed', 'total_cost' => 1000, 'full_name' => 'Completed Guest']);
        $this->reservation(['status' => 'cancelled', 'total_cost' => 1000, 'full_name' => 'Cancelled Guest']);
        $this->reservation(['status' => 'pending', 'total_cost' => 1000, 'full_name' => 'Pending Guest']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk()->assertViewHas('needsAttention', function (array $counts): bool {
            return $counts['unpaid_accepted'] === 3
                && $counts['missing_contracts'] === 3
                && $counts['outstanding_balances'] === 1
                && $counts['events_awaiting_completion'] === 0;
        });
        $response->assertSee(route('admin.reservations', [
            'status' => 'confirmed',
            'attention' => 'no_payment',
        ]));
        $response->assertSee(route('admin.reservations', [
            'status' => 'confirmed',
            'attention' => 'missing_contract',
        ]));
        $response->assertSee(route('admin.reservations', [
            'status' => 'confirmed',
            'attention' => 'outstanding_balance',
        ]));

        $this->assertSame('Unpaid', $fullyRefunded->fresh()->financials()['payment_status']);
        $this->assertSame(0, $fullyRefunded->fresh()->financials()['net_paid_cents']);
        $this->assertNull($noContractPrice->fresh()->financials()['remaining_balance_cents']);
        $this->assertSame(0, $zeroContractPrice->fresh()->financials()['remaining_balance_cents']);
    }

    public function test_events_awaiting_completion_counts_only_past_confirmed_date_and_time(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::parse('2026-10-03 14:00:00', config('app.timezone')));

        $past = $this->reservation([
            'status' => Reservation::STATUS_CONFIRMED,
            'event_date' => '2026-10-02',
            'event_time' => '18:00',
            'full_name' => 'Past accepted event',
            'service_contracts' => ['contracts/first.pdf', 'contracts/second.pdf'],
        ]);
        $past->payments()->createMany([
            ['payment_date' => '2026-09-01', 'payment_type' => 'Downpayment', 'amount' => 100, 'payment_method' => 'Cash'],
            ['payment_date' => '2026-09-02', 'payment_type' => 'Partial Payment', 'amount' => 100, 'payment_method' => 'Cash'],
        ]);
        $past->refunds()->createMany([
            ['refund_date' => '2026-09-03', 'amount' => 10, 'refund_method' => 'Cash', 'status' => 'completed', 'request_key' => (string) Str::uuid()],
            ['refund_date' => '2026-09-04', 'amount' => 10, 'refund_method' => 'Cash', 'status' => 'completed', 'request_key' => (string) Str::uuid()],
        ]);

        $todayPast = $this->reservation([
            'status' => Reservation::STATUS_CONFIRMED,
            'event_date' => '2026-10-03',
            'event_time' => '10:00',
            'full_name' => 'Today past-time event',
        ]);
        $todayFuture = $this->reservation([
            'status' => Reservation::STATUS_CONFIRMED,
            'event_date' => '2026-10-03',
            'event_time' => '22:00',
            'full_name' => 'Today future-time event',
        ]);
        $sameTime = $this->reservation([
            'status' => Reservation::STATUS_CONFIRMED,
            'event_date' => '2026-10-03',
            'event_time' => '14:00',
            'full_name' => 'Event at current time',
        ]);
        $future = $this->reservation([
            'status' => Reservation::STATUS_CONFIRMED,
            'event_date' => '2026-10-04',
            'event_time' => '10:00',
            'full_name' => 'Future event',
        ]);
        $completed = $this->reservation([
            'status' => Reservation::STATUS_COMPLETED,
            'event_date' => '2026-10-02',
            'event_time' => '10:00',
            'full_name' => 'Completed past event',
        ]);
        $cancelled = $this->reservation([
            'status' => Reservation::STATUS_CANCELLED,
            'event_date' => '2026-10-02',
            'event_time' => '10:00',
            'full_name' => 'Cancelled past event',
        ]);
        $rejected = $this->reservation([
            'status' => 'rejected',
            'event_date' => '2026-10-02',
            'event_time' => '10:00',
            'full_name' => 'Rejected past event',
        ]);
        $pending = $this->reservation([
            'status' => Reservation::STATUS_PENDING,
            'event_date' => '2026-10-02',
            'event_time' => '10:00',
            'full_name' => 'Pending past event',
        ]);
        $invalidTime = $this->reservation([
            'status' => Reservation::STATUS_CONFIRMED,
            'event_date' => '2026-10-02',
            'event_time' => 'not-a-time',
            'full_name' => 'Past event with invalid time',
        ]);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $response->assertOk()
            ->assertSee('Events Awaiting Completion')
            ->assertSee('<span class="attention-item-count">2</span>', false)
            ->assertViewHas('needsAttention', function (array $counts): bool {
                return $counts['events_awaiting_completion'] === 2;
            });
        $response->assertSee(array_sum($response->viewData('needsAttention')).' OPEN ITEMS');

        $filtered = $this->withSession(self::ADMIN)->get(route('admin.reservations', [
            'status' => Reservation::STATUS_CONFIRMED,
            'attention' => \App\Services\ReservationNeedsAttentionService::EVENTS_AWAITING_COMPLETION,
        ]));
        $filtered->assertOk()
            ->assertSee('Past accepted event')
            ->assertSee('Today past-time event')
            ->assertDontSee('Today future-time event')
            ->assertDontSee('Event at current time')
            ->assertDontSee('Future event')
            ->assertDontSee('Completed past event')
            ->assertDontSee('Cancelled past event')
            ->assertDontSee('Rejected past event')
            ->assertDontSee('Pending past event')
            ->assertDontSee('Past event with invalid time');

        $this->assertSame(2, $filtered->viewData('matchingReservationCount'));
        $this->assertNotContains($sameTime->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($todayFuture->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($future->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($completed->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($cancelled->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($rejected->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($pending->id, $filtered->viewData('reservations')->pluck('id')->all());
        $this->assertNotContains($invalidTime->id, $filtered->viewData('reservations')->pluck('id')->all());
    }

    public function test_invalid_refund_history_is_not_classified_as_unpaid_or_outstanding_and_does_not_break_dashboard(): void
    {
        $reservation = $this->reservation([
            'status' => 'confirmed',
            'total_cost' => 1000,
        ]);
        $payment = $reservation->payments()->create([
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 100,
            'payment_method' => 'Cash',
        ]);
        $reservation->refunds()->create([
            'payment_id' => $payment->id,
            'refund_date' => now()->toDateString(),
            'amount' => 200,
            'refund_method' => 'Cash',
            'status' => 'completed',
            'request_key' => (string) Str::uuid(),
        ]);

        $this->withSession(self::ADMIN)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('needsAttention', function (array $counts): bool {
                return $counts['unpaid_accepted'] === 0
                    && $counts['missing_contracts'] === 1
                    && $counts['outstanding_balances'] === 0;
            });
    }

    public function test_overview_keeps_daily_operational_summary_without_business_kpis(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed', 'total_cost' => 45000]);
        // Record through the real endpoint so payment_status is recalculated, not the raw model.
        $this->withSession(self::ADMIN)->post(route('admin.reservations.payments.store', $reservation), [
            'payment_date' => now()->toDateString(),
            'payment_type' => 'Downpayment',
            'amount' => 15000,
            'payment_method' => 'Cash',
        ]);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Today / Upcoming');
        $response->assertSee('Events today');
        $response->assertSee('Payments due soon');
        $response->assertDontSee('Business overview');
        $response->assertDontSee('Lifetime totals');
        $response->assertDontSee('Net payments received, all time');
        // Once a payment is recorded the reservation no longer counts as "no payment on file".
        $response->assertDontSee('Accepted reservations with no payment on file');
    }

    public function test_calendar_now_includes_pending_reservations(): void
    {
        $reservation = $this->reservation(['status' => 'pending']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee($reservation->full_name);
        $response->assertSee('"status":"pending"', false);
    }
}
