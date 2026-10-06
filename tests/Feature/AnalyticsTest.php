<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Carbon\Carbon;
use Tests\TestCase;

class AnalyticsTest extends TestCase
{
    public function test_full_admin_can_view_analytics_with_fully_paid_completed_revenue(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        $package = Package::create([
            'name' => 'Celebration Package',
            'slug' => 'celebration-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);

        $this->createReservation($package->id, [
            'status' => 'confirmed',
            'estimated_budget' => 15000,
            'total_cost' => 22000,
            'payment_status' => 'Fully Paid',
            'amount_paid' => 22000,
        ]);
        $this->createReservation($package->id, [
            'status' => 'completed',
            'estimated_budget' => 18000,
            'total_cost' => null,
            'payment_status' => 'Fully Paid',
            'amount_paid' => 18000,
        ]);
        $this->createReservation($package->id, [
            'status' => 'pending',
            'estimated_budget' => 9000,
            'total_cost' => 12000,
            'payment_status' => 'Fully Paid',
            'amount_paid' => 12000,
        ]);
        $this->createReservation($package->id, [
            'status' => 'confirmed',
            'estimated_budget' => 50000,
            'total_cost' => 30000,
            'payment_status' => 'Downpayment',
            'amount_paid' => 6000,
        ]);

        $response = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.analytics'));

        $response->assertOk();
        $totals = $response->viewData('totals');
        $this->assertSame(4, $totals['reservations']);
        $this->assertEquals(58000.0, $totals['paid']);
        $this->assertEquals(0.0, $totals['refunded']);
        $this->assertEquals(58000.0, $totals['net']);
        $response->assertSee('Celebration Package');
        $response->assertSee('&#8369;58,000.00', false);
        $response->assertSee("new Chart(document.getElementById('revenueChart')", false);
    }

    public function test_analytics_account_for_refunds_and_match_reports(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        $package = Package::create([
            'name' => 'Celebration Package',
            'slug' => 'celebration-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);
        $this->createReservation($package->id, ['status' => 'confirmed', 'total_cost' => 50000]);
        $reservation = Reservation::first();
        $reservation->payments()->create(['payment_date' => '2026-09-20', 'payment_type' => 'Downpayment', 'amount' => 10000, 'payment_method' => 'Cash']);
        $reservation->refunds()->create(['refund_date' => '2026-09-21', 'amount' => 3000, 'refund_method' => 'Cash', 'status' => 'completed', 'request_key' => (string) \Illuminate\Support\Str::uuid()]);

        $response = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])->get(route('admin.analytics'));

        $response->assertOk();
        $totals = $response->viewData('totals');
        $this->assertEquals(10000.0, $totals['paid']);
        $this->assertEquals(3000.0, $totals['refunded']);
        $this->assertEquals(7000.0, $totals['net']);
        $this->assertEquals(43000.0, $totals['outstanding']);
        $this->assertSame(1, $totals['refunded_reservations']);
        $september = $response->viewData('monthly')->last();
        $this->assertEquals([10000.0, 3000.0, 7000.0], [$september->paid, $september->refunded, $september->net]);

        $summary = app(\App\Services\ReportService::class)->getSummary('yearly');
        $this->assertEquals($summary['gross_paid'], $totals['paid']);
        $this->assertEquals($summary['total_refunded'], $totals['refunded']);
        $this->assertEquals($summary['net_paid'], $totals['net']);
    }

    public function test_analytics_custom_range_filters_reservation_event_dates_and_transaction_dates(): void
    {
        Carbon::setTestNow('2026-09-23 12:00:00');

        $package = Package::create([
            'name' => 'Celebration Package',
            'slug' => 'celebration-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);

        $inRangeReservation = Reservation::create(array_merge([
            'package_id' => $package->id,
            'full_name' => 'Analytics Client',
            'contact_number' => '09171234567',
            'email' => 'analytics@example.com',
            'address' => '123 Analytics Street',
            'event_type' => 'Wedding',
            'event_date' => '2026-09-10',
            'event_time' => '18:00',
            'venue' => 'Analytics Hall',
            'guest_count' => 100,
            'estimated_budget' => 0,
            'status' => 'confirmed',
            'total_cost' => 30000,
        ], []));
        $inRangeReservation->payments()->create(['payment_date' => '2026-09-12', 'payment_type' => 'Downpayment', 'amount' => 12000, 'payment_method' => 'Cash']);
        $inRangeReservation->refunds()->create(['refund_date' => '2026-09-15', 'amount' => 3000, 'refund_method' => 'Cash', 'status' => 'completed', 'request_key' => (string) \Illuminate\Support\Str::uuid()]);

        $outOfRangeReservation = Reservation::create(array_merge([
            'package_id' => $package->id,
            'full_name' => 'Analytics Client',
            'contact_number' => '09171234567',
            'email' => 'analytics@example.com',
            'address' => '123 Analytics Street',
            'event_type' => 'Wedding',
            'event_date' => '2026-10-20',
            'event_time' => '18:00',
            'venue' => 'Analytics Hall',
            'guest_count' => 100,
            'estimated_budget' => 0,
            'status' => 'confirmed',
            'total_cost' => 20000,
        ], []));
        $outOfRangeReservation->payments()->create(['payment_date' => '2026-10-21', 'payment_type' => 'Downpayment', 'amount' => 5000, 'payment_method' => 'Cash']);

        $response = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.analytics', ['range' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']));

        $response->assertOk();
        $this->assertSame('custom', $response->viewData('selectedRange'));
        $totals = $response->viewData('totals');
        $this->assertSame(1, $totals['reservations']);
        $this->assertEquals(12000.0, $totals['paid']);
        $this->assertEquals(3000.0, $totals['refunded']);
        $this->assertEquals(9000.0, $totals['net']);
        $this->assertEquals(21000.0, $totals['outstanding']);
        $this->assertSame(1, $totals['refunded_reservations']);
    }

    public function test_analytics_rejects_invalid_custom_range_dates(): void
    {
        $response = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->from(route('admin.analytics'))
            ->get(route('admin.analytics', ['range' => 'custom', 'from' => '2026-09-30', 'to' => '2026-09-01']));

        $response->assertRedirect(route('admin.analytics'));
        $response->assertSessionHasErrors(['to']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createReservation(int $packageId, array $overrides): void
    {
        Reservation::create(array_merge([
            'package_id' => $packageId,
            'full_name' => 'Analytics Client',
            'contact_number' => '09171234567',
            'email' => 'analytics@example.com',
            'address' => '123 Analytics Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Analytics Hall',
            'guest_count' => 100,
            'estimated_budget' => 0,
            'status' => 'pending',
        ], $overrides));
    }
}
