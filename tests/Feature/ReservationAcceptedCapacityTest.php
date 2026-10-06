<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationAcceptedCapacityTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Capacity Tester', 'admin_email' => 'capacity@3yos.com'];
    private const DATE = '2030-10-08';

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Silver', 'slug' => 'silver-'.uniqid(), 'price' => 750, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Capacity Client',
            'contact_number' => '09171234567',
            'email' => 'capacity-'.uniqid().'@example.com',
            'address' => '1 Capacity Street',
            'event_type' => 'Wedding',
            'event_date' => self::DATE,
            'event_time' => '18:00',
            'venue' => 'Capacity Hall',
            'guest_count' => 80,
            'estimated_budget' => 60000,
            'total_cost' => 60000,
            'status' => 'pending',
            'reservation_code' => 'RES-CAP'.random_int(100000, 999999),
        ]);
    }

    public function test_zero_accepted_bookings_allows_accepting(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $reservation))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $reservation->fresh()->status);
    }

    public function test_three_accepted_bookings_allows_a_fourth(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $fourth = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $fourth))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $fourth->fresh()->status);
        $this->assertSame(4, Reservation::whereDate('event_date', self::DATE)->where('status', 'confirmed')->count());
    }

    public function test_four_accepted_bookings_blocks_a_fifth(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $fifth = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $fifth))
            ->assertSessionHasErrors(['status' => 'Maximum active reservations for this date has been reached. Only 4 active reservations are allowed per date.']);

        // Previous status must remain unchanged.
        $this->assertSame('pending', $fifth->fresh()->status);
        $this->assertSame(4, Reservation::whereDate('event_date', self::DATE)->where('status', 'confirmed')->count());
    }

    public function test_editing_an_already_accepted_booking_among_four_is_still_allowed(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $second = $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);

        $this->withSession(self::ADMIN)
            ->patch(route('admin.reservations.update', $second), [
                'event_date' => $second->event_date,
                'event_time' => '19:30',
                'venue' => 'Updated accepted venue',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $second->fresh()->status);
        $this->assertSame('Updated accepted venue', $second->fresh()->venue);
        $this->assertSame(4, Reservation::whereDate('event_date', self::DATE)->where('status', 'confirmed')->count());
    }

    public function test_accepting_a_pending_booking_when_three_are_already_accepted_is_allowed(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $pending = $this->reservation(['status' => 'pending']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $pending))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $pending->fresh()->status);
        $this->get(route('reservation.availability', ['date' => self::DATE]))
            ->assertOk()
            ->assertExactJson(['bookings' => 4, 'capacity' => 4, 'remaining' => 0, 'available' => false]);
    }

    public function test_cancelling_a_pending_booking_releases_capacity_immediately(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $pending = $this->reservation(['status' => 'pending']);

        $this->get(route('reservation.availability', ['date' => self::DATE]))
            ->assertExactJson(['bookings' => 4, 'capacity' => 4, 'remaining' => 0, 'available' => false]);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.cancel', $pending))
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame(4, Reservation::whereDate('event_date', self::DATE)->count());
        $this->get(route('reservation.availability', ['date' => self::DATE]))
            ->assertExactJson(['bookings' => 3, 'capacity' => 4, 'remaining' => 1, 'available' => true]);
    }

    public function test_cancelling_an_accepted_booking_releases_capacity_immediately(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $accepted = $this->reservation(['status' => 'confirmed']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.cancel', $accepted))
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $accepted->fresh()->status);
        $this->get(route('reservation.availability', ['date' => self::DATE]))
            ->assertExactJson(['bookings' => 3, 'capacity' => 4, 'remaining' => 1, 'available' => true]);
    }

    public function test_admin_cannot_accept_a_booking_when_four_pending_bookings_already_occupy_capacity(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $candidate = $this->reservation(['status' => 'cancelled']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $candidate))
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $candidate->fresh()->status);
        $this->assertSame(4, $this->get(route('reservation.availability', ['date' => self::DATE]))->json('bookings'));
    }

    public function test_cancelled_bookings_do_not_count_toward_the_limit(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'cancelled']);
        $this->reservation(['status' => 'cancelled']);
        $candidate = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $candidate))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $candidate->fresh()->status);
        $this->assertSame(4, Reservation::query()->occupyingCapacity()->whereDate('event_date', self::DATE)->count());
    }

    public function test_cancelled_booking_cannot_be_accepted_again(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $cancelled = $this->reservation(['status' => 'cancelled']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $cancelled))
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $cancelled->fresh()->status);
        $this->assertSame(1, Reservation::query()->where('status', 'cancelled')->whereDate('event_date', self::DATE)->count());
    }

    public function test_completed_bookings_do_not_count_toward_the_limit(): void
    {
        Mail::fake();
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $candidate = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $candidate))
            ->assertSessionHasNoErrors();

        $this->assertSame('confirmed', $candidate->fresh()->status);
    }
}
