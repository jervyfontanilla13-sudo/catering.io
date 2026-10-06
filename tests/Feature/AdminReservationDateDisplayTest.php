<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Tests\TestCase;

class AdminReservationDateDisplayTest extends TestCase
{
    public function test_desktop_table_and_mobile_card_show_event_and_booking_dates(): void
    {
        $reservation = Reservation::create([
            'full_name' => 'Date Display Client',
            'contact_number' => '09171234567',
            'email' => 'date-display@example.com',
            'address' => '123 Example Street',
            'event_type' => 'Wedding',
            'event_date' => '2026-10-22',
            'event_time' => '18:00',
            'venue' => 'Example Hall',
            'guest_count' => 50,
            'estimated_budget' => 25000,
            'status' => 'pending',
            'reservation_code' => 'RES-DATE-DISPLAY',
        ]);
        $reservation->forceFill(['created_at' => '2026-10-06 09:30:00'])->save();

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.reservations'));

        $response->assertOk()
            ->assertSee('Oct 22, 2026')
            ->assertSee('Added: Oct 6, 2026')
            ->assertSee('<small class="text-muted">Added: Oct 6, 2026</small>', false)
            ->assertSee('<small class="d-block text-muted">Added: Oct 6, 2026</small>', false)
            ->assertViewHas('reservations', fn ($reservations) => $reservations->first()->created_at->format('M j, Y') === 'Oct 6, 2026');
    }
}
