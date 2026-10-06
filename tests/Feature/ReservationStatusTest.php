<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_reservation_status_can_be_looked_up_by_unique_code(): void
    {
        Package::create([
            'name' => 'Classic Package',
            'slug' => 'classic-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
            'event_type' => 'Wedding',
        ]);

        Reservation::create([
            'package_id' => 1,
            'full_name' => 'Maria Reyes',
            'contact_number' => '09171234567',
            'email' => 'maria@example.com',
            'address' => '123 Sample Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Grand Hall',
            'guest_count' => 80,
            'estimated_budget' => 25000,
            'status' => 'pending',
            'reservation_code' => 'RES-TEST-1234',
        ]);

        $response = $this->get('/reservation/status?code=RES-TEST-1234');

        $response->assertStatus(200);
        $response->assertSeeText('Maria Reyes');
        $response->assertSeeText('RES-TEST-1234');
    }
}
