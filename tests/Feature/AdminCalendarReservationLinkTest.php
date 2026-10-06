<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Tests\TestCase;

class AdminCalendarReservationLinkTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Calendar Package', 'slug' => 'calendar-'.uniqid(), 'price' => 900, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Calendar Client',
            'contact_number' => '09171234567',
            'email' => 'calendar-'.uniqid().'@example.com',
            'address' => '1 Calendar Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Calendar Hall',
            'guest_count' => 50,
            'estimated_budget' => 45000,
            'status' => 'pending',
            'reservation_code' => 'RES-CAL'.random_int(100000, 999999),
        ]);
    }

    public function test_calendar_payload_carries_the_real_reservation_id_for_every_status(): void
    {
        $pending = $this->reservation(['status' => 'pending', 'event_type' => 'Graduation']);
        $confirmed = $this->reservation(['status' => 'confirmed', 'event_type' => 'Wedding']);
        $completed = $this->reservation(['status' => 'completed', 'event_type' => 'Birthday']);
        $cancelled = $this->reservation(['status' => 'cancelled', 'event_type' => 'Anniversary']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $response->assertOk();

        foreach ([$pending, $confirmed, $completed, $cancelled] as $reservation) {
            $response->assertSee('"id":'.$reservation->id, false);
        }
    }

    public function test_calendar_capacity_counts_pending_and_accepted_but_not_terminal_reservations(): void
    {
        $this->reservation(['event_date' => '2030-10-08', 'status' => 'confirmed']);
        $this->reservation(['event_date' => '2030-10-08', 'status' => 'confirmed']);
        $this->reservation(['event_date' => '2030-10-08', 'status' => 'confirmed']);
        $this->reservation(['event_date' => '2030-10-08', 'status' => 'pending']);
        $this->reservation(['event_date' => '2030-10-08', 'status' => 'cancelled']);
        $this->reservation(['event_date' => '2030-10-08', 'status' => 'completed']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $response->assertOk();
        $response->assertSee('"2030-10-08":4', false);
        $response->assertSee('const capacityByDate =', false);
        $response->assertSee('const maximumCapacity = 4;', false);
        $response->assertSee('Math.max(0, maximumCapacity - occupied)', false);
        $response->assertSee('`${occupied}/${maximumCapacity} · FULL`', false);
        $response->assertSee('`${occupied}/${maximumCapacity} · ${remaining} slot${remaining === 1 ? \'\' : \'s\'} open`', false);
        $response->assertSee('`${date}: ${occupied} of ${maximumCapacity} active reservations, ${remaining} slots available.`', false);
    }

    public function test_two_reservations_sharing_date_and_event_type_keep_distinct_ids(): void
    {
        // Mirrors the real scenario this feature targets: two separate "Graduation" bookings
        // on the same date are two different reservations, not a rendering duplicate.
        $first = $this->reservation(['event_date' => '2026-10-01', 'event_type' => 'Graduation']);
        $second = $this->reservation(['event_date' => '2026-10-01', 'event_type' => 'Graduation']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee('"id":'.$first->id, false);
        $response->assertSee('"id":'.$second->id, false);
        $this->assertNotEquals($first->id, $second->id);
    }

    public function test_calendar_links_to_the_real_reservation_detail_route(): void
    {
        $reservation = $this->reservation();

        $response = $this->withSession(self::ADMIN)->get(route('admin.dashboard'));
        $response->assertOk();

        // The JS builds each event's href from this exact route template at runtime.
        // @json() escapes forward slashes (json_encode's default), so match it the same way.
        $template = str_replace('/', '\/', route('admin.reservations.show', ['reservation' => '__ID__']));
        $response->assertSee($template, false);
    }

    public function test_clicking_through_a_calendar_event_opens_the_correct_reservation_regardless_of_status(): void
    {
        $graduation = $this->reservation(['event_type' => 'Graduation', 'full_name' => 'Juan Dela Cruz', 'status' => 'confirmed']);
        $wedding = $this->reservation(['event_type' => 'Wedding', 'full_name' => 'Maria Santos', 'status' => 'cancelled']);

        $graduationPage = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $graduation));
        $graduationPage->assertOk();
        $graduationPage->assertSee('Juan Dela Cruz');
        $graduationPage->assertDontSee('Maria Santos');

        $weddingPage = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $wedding));
        $weddingPage->assertOk();
        $weddingPage->assertSee('Maria Santos');
        $weddingPage->assertDontSee('Juan Dela Cruz');
    }

    public function test_reservation_detail_opened_from_the_calendar_still_shows_its_activity_history(): void
    {
        $reservation = $this->reservation();
        $this->withSession(self::ADMIN)->post(route('admin.reservations.accept', $reservation));

        $response = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $reservation));

        $response->assertOk();
        $response->assertSee('Activity');
    }

    public function test_guest_cannot_reach_a_reservation_detail_page_via_the_calendar_link_pattern(): void
    {
        $reservation = $this->reservation();

        $this->get(route('admin.reservations.show', $reservation))
            ->assertRedirect(route('admin.login'));
    }
}
