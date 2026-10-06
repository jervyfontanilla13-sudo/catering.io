<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Tests\TestCase;

class ReservationConfirmationViewTest extends TestCase
{
    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Confirmation Package', 'slug' => 'confirmation-'.uniqid(), 'price' => 700, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Maria Santos',
            'contact_number' => '09171234567',
            'email' => 'confirmation-'.uniqid().'@example.com',
            'address' => '1 Confirmation Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:30',
            'venue' => 'Confirmation Hall',
            'guest_count' => 80,
            'estimated_budget' => 56000,
            'status' => 'pending',
            'reservation_code' => 'RES-CONF'.random_int(100000, 999999),
        ]);
    }

    public function test_fresh_reservation_page_shows_the_wizard_not_a_confirmation(): void
    {
        $response = $this->get('/reservation');

        $response->assertOk();
        $response->assertSee('class="wizard-stepper"', false);
        $response->assertDontSee('Reservation request received');
    }

    public function test_looking_up_a_reservation_by_code_shows_the_confirmation_view(): void
    {
        $reservation = $this->reservation();

        $response = $this->get('/reservation?code='.$reservation->reservation_code);

        $response->assertOk();
        $response->assertSee('Reservation request received');
        $response->assertSee($reservation->reservation_code);
        $response->assertSee('Wedding');
        $response->assertSee('Confirmation Hall');
        $response->assertSee('Confirmation Package');
        $response->assertSee('does not mean your event is confirmed yet');
        $response->assertSee('What happens next');
        $response->assertSee('Check status');
        $response->assertSee('Back to website');
        $response->assertSee('Submitted');
        $response->assertSee('Under Review');
        // The wizard form itself should not render alongside the confirmation (the CSS still ships
        // either way, so check for the actual markup rather than the class name alone).
        $response->assertDontSee('class="wizard-stepper"', false);
    }

    public function test_confirmation_view_reflects_accepted_status(): void
    {
        $reservation = $this->reservation(['status' => 'confirmed']);

        $response = $this->get('/reservation?code='.$reservation->reservation_code);

        $response->assertOk();
        $response->assertSeeInOrder(['Current status', 'Accepted']);
    }
}
