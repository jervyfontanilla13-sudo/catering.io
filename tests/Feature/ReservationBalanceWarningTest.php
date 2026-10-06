<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Tests\TestCase;

class ReservationBalanceWarningTest extends TestCase
{
    public function test_admin_sees_an_unpaid_balance_warning_for_an_outstanding_reservation(): void
    {
        $package = Package::create([
            'name' => 'Warning Package',
            'slug' => 'warning-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);

        $reservation = Reservation::create([
            'package_id' => $package->id,
            'full_name' => 'Balance Client',
            'contact_number' => '09171234567',
            'email' => 'balance@example.com',
            'address' => '123 Balance Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Balance Hall',
            'guest_count' => 100,
            'estimated_budget' => 25000,
            'total_cost' => 30000,
            'amount_paid' => 8000,
            'balance' => 22000,
            'payment_status' => 'Downpayment',
            'status' => 'completed',
        ]);

        // The reservation list shows the status and payment-status badges only; the balance
        // breakdown lives on the dedicated reservation detail page.
        $listResponse = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.reservations'));

        $listResponse->assertOk();
        $listResponse->assertSee('status-badge--completed', false);

        $detailResponse = $this->withSession(['is_admin' => true, 'admin_role' => 'full'])
            ->get(route('admin.reservations.show', $reservation));

        $detailResponse->assertOk();
        $detailResponse->assertSee('summary-item--warn', false);
        $detailResponse->assertSee('&#8369;22,000.00', false);
    }
}
