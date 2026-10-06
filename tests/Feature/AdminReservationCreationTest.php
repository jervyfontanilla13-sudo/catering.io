<?php

namespace Tests\Feature;

use App\Mail\ReservationConfirmationMail;
use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminReservationCreationTest extends TestCase
{
    public function test_admin_reservations_page_has_an_add_booking_action(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.reservations'));

        $response->assertOk();
        $response->assertSee('Add reservation');
        $response->assertSee(route('admin.reservations.create'));
    }

    public function test_admin_reservation_form_uses_the_styled_clock_picker(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.reservations.create'));

        $response->assertOk();
        $response->assertSee('clock-time-panel');
        $response->assertSee('name="event_time"', false);
        $response->assertSee('clock-time-numbers');
        $response->assertSee('placeholder="Juan dela Cruz"', false);
        $response->assertSee('pattern="(?:\\+63[0-9]{10}|09[0-9]{9})"', false);
    }

    public function test_admin_can_create_a_booking_and_send_its_reservation_id(): void
    {
        Mail::fake();
        $package = Package::create([
            'name' => 'Admin booking package',
            'slug' => 'admin-booking-package',
            'price' => 600,
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->post(route('admin.reservations.store'), [
            'full_name' => 'Admin Booking Client',
            'contact_number' => '09682676371',
            'email' => 'admin-booking@example.com',
            'address' => '123 Main Street, Quezon City',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 40,
            'package_id' => $package->id,
            'additional_services' => 'Buffet setup',
            'special_requests' => 'Vegetarian option',
        ]);

        $response->assertRedirect(route('admin.reservations'));
        $this->assertDatabaseHas('reservations', [
            'email' => 'admin-booking@example.com',
            'contact_number' => '09682676371',
            'package_id' => $package->id,
            'guest_count' => 40,
            'estimated_budget' => 24000,
            'status' => 'pending',
        ]);

        $reservation = Reservation::where('email', 'admin-booking@example.com')->firstOrFail();
        $this->assertMatchesRegularExpression('/^RES-[A-Z0-9]{8}$/', $reservation->reservation_code);
        Mail::assertSent(ReservationConfirmationMail::class, fn (ReservationConfirmationMail $mail) => $mail->hasTo('admin-booking@example.com')
            && str_contains($mail->render(), $reservation->reservation_code));
    }

    public function test_admin_cannot_create_a_pending_booking_when_pending_reservations_fill_capacity(): void
    {
        Mail::fake();
        $eventDate = '2030-10-08';
        $package = Package::create([
            'name' => 'Full date package',
            'slug' => 'full-date-package',
            'price' => 600,
        ]);

        for ($i = 0; $i < Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE; $i++) {
            Reservation::create([
                'package_id' => $package->id,
                'full_name' => "Existing Client {$i}",
                'contact_number' => '09682676371',
                'email' => "existing-{$i}@example.com",
                'address' => '123 Main Street, Quezon City',
                'event_type' => 'Wedding',
                'event_date' => $eventDate,
                'event_time' => '18:00',
                'venue' => 'Garden Hall',
                'guest_count' => 40,
                'estimated_budget' => 24000,
                'status' => 'pending',
                'reservation_code' => "RES-FULL{$i}",
            ]);
        }

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->from(route('admin.reservations.create'))->post(route('admin.reservations.store'), [
            'full_name' => 'Blocked Admin Client',
            'contact_number' => '09682676371',
            'email' => 'blocked-admin-booking@example.com',
            'address' => '123 Main Street, Quezon City',
            'event_type' => 'Birthday',
            'event_date' => $eventDate,
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 40,
            'package_id' => $package->id,
        ]);

        $response->assertRedirect(route('admin.reservations.create'));
        $response->assertSessionHasErrors([
            'event_date' => 'This date is fully booked with 4 active reservations. Choose another date.',
        ]);
        $this->assertDatabaseMissing('reservations', ['email' => 'blocked-admin-booking@example.com']);
        $this->assertDatabaseMissing('clients', ['email' => 'blocked-admin-booking@example.com']);
    }
}