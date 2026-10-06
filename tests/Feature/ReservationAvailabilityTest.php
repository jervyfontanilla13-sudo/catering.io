<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReservationAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2030-10-08';

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Silver', 'slug' => 'silver-'.uniqid(), 'price' => 750, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Availability Client',
            'contact_number' => '09171234567',
            'email' => 'availability-'.uniqid().'@example.com',
            'address' => '1 Availability Street',
            'event_type' => 'Wedding',
            'event_date' => self::DATE,
            'event_time' => '18:00',
            'venue' => 'Availability Hall',
            'guest_count' => 80,
            'estimated_budget' => 60000,
            'total_cost' => 60000,
            'status' => 'pending',
            'reservation_code' => 'RES-AVAIL'.random_int(100000, 999999),
        ]);
    }

    private function checkAvailability(): array
    {
        return $this->get(route('reservation.availability', ['date' => self::DATE]))->json();
    }

    #[DataProvider('activeCapacityProvider')]
    public function test_availability_counts_pending_and_confirmed_reservations(int $acceptedCount, int $pendingCount, bool $expectedAvailable, int $expectedRemaining): void
    {
        for ($i = 0; $i < $acceptedCount; $i++) {
            $this->reservation(['status' => 'confirmed']);
        }
        for ($i = 0; $i < $pendingCount; $i++) {
            $this->reservation(['status' => 'pending']);
        }

        $response = $this->checkAvailability();

        $this->assertSame($expectedAvailable, $response['available']);
        $this->assertSame($acceptedCount + $pendingCount, $response['bookings']);
        $this->assertSame(Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE, $response['capacity']);
        $this->assertSame($expectedRemaining, $response['remaining']);
    }

    public static function activeCapacityProvider(): array
    {
        return [
            'no active reservations' => [0, 0, true, 4],
            'one accepted' => [1, 0, true, 3],
            'two accepted' => [2, 0, true, 2],
            'three accepted' => [3, 0, true, 1],
            'three accepted and one pending' => [3, 1, false, 0],
            'one accepted and three pending' => [1, 3, false, 0],
            'two accepted and two pending' => [2, 2, false, 0],
            'four accepted' => [4, 0, false, 0],
            'four pending' => [0, 4, false, 0],
            'one accepted and two pending' => [1, 2, true, 1],
        ];
    }

    public function test_three_accepted_plus_one_pending_is_full(): void
    {
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'pending']);

        $response = $this->checkAvailability();

        $this->assertFalse($response['available']);
        $this->assertSame(4, $response['bookings']);
        $this->assertSame(0, $response['remaining']);
    }

    public function test_case_7_three_accepted_plus_one_cancelled_is_available(): void
    {
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'cancelled']);

        $response = $this->checkAvailability();

        $this->assertTrue($response['available']);
        $this->assertSame(3, $response['bookings']);
    }

    public function test_case_8_four_accepted_plus_pending_and_cancelled_is_fully_booked(): void
    {
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'confirmed']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'pending']);
        $this->reservation(['status' => 'cancelled']);

        $response = $this->checkAvailability();

        $this->assertFalse($response['available']);
        $this->assertSame(6, $response['bookings']);
        $this->assertSame(0, $response['remaining']);
    }

    public function test_completed_bookings_do_not_count_toward_availability(): void
    {
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'completed']);

        $response = $this->checkAvailability();

        $this->assertTrue($response['available']);
        $this->assertSame(0, $response['bookings']);
    }

    public function test_cancelled_and_rejected_bookings_do_not_count_toward_availability(): void
    {
        $this->reservation(['status' => 'cancelled']);
        $this->reservation(['status' => 'rejected']);
        $this->reservation(['status' => 'completed']);
        $this->reservation(['status' => 'confirmed']);

        $response = $this->checkAvailability();

        $this->assertTrue($response['available']);
        $this->assertSame(1, $response['bookings']);
        $this->assertSame(3, $response['remaining']);
    }

    public function test_availability_uses_the_shared_active_reservation_limit(): void
    {
        $this->assertSame(4, Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE);

        for ($i = 0; $i < Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE; $i++) {
            $this->reservation(['status' => 'confirmed']);
        }

        $response = $this->checkAvailability();

        $this->assertFalse($response['available']);
    }

    public function test_fully_booked_date_rejects_reservation_submission_before_recaptcha(): void
    {
        $eventDate = now()->addDays(7)->toDateString();
        $package = Package::create([
            'name' => 'Submission Package',
            'slug' => 'submission-package',
            'price' => 750,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);

        for ($i = 0; $i < Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE - 1; $i++) {
            $this->reservation([
                'event_date' => $eventDate,
                'status' => Reservation::STATUS_CONFIRMED,
            ]);
        }
        $this->reservation([
            'event_date' => $eventDate,
            'status' => Reservation::STATUS_PENDING,
        ]);

        $response = $this->from('/reservation')->post(route('reservation.store'), [
            'full_name' => 'Test Client',
            'contact_number' => '09171234567',
            'email' => 'submission@example.com',
            'address' => '123 Garden Street',
            'event_type' => 'Wedding',
            'event_date' => $eventDate,
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 80,
            'package_id' => $package->id,
            'form_started' => now()->subSeconds(5)->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors([
            'event_date' => 'This date is fully booked. Please choose another date.',
        ]);
        $this->assertSame(4, Reservation::whereDate('event_date', $eventDate)->count());

        $this->get('/reservation')
            ->assertOk()
            ->assertSee('This date is currently fully booked. Please select another available date.')
            ->assertDontSee('This date is fully booked. Please choose another date.');
    }

    public function test_capacity_date_lock_is_idempotent_inside_a_transaction(): void
    {
        $capacity = app(\App\Services\ReservationCapacityService::class);

        \Illuminate\Support\Facades\DB::transaction(function () use ($capacity): void {
            $capacity->lockDates([self::DATE, self::DATE]);
            $this->assertDatabaseHas('reservation_capacity_locks', ['event_date' => self::DATE]);
            $this->assertSame(1, \Illuminate\Support\Facades\DB::table('reservation_capacity_locks')
                ->where('event_date', self::DATE)
                ->count());
        });
    }
}
