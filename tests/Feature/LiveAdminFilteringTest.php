<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use Tests\TestCase;

class LiveAdminFilteringTest extends TestCase
{
    public function test_reservation_search_is_case_insensitive_and_matches_all_relevant_fields(): void
    {
        $package = Package::create([
            'name' => 'Silver Reception Package',
            'slug' => 'silver-reception-package',
            'price' => 650,
        ]);
        Reservation::create([
            'package_id' => $package->id,
            'full_name' => 'JAN RAYVER ARCEGA FLORES',
            'contact_number' => '+639171234567',
            'email' => 'jan@example.com',
            'address' => 'Marikina City, Metro Manila',
            'event_type' => 'Wedding Celebration',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Marikina Garden Hall',
            'guest_count' => 80,
            'estimated_budget' => 52000,
            'service_contract' => 'contracts/jan-menu.pdf',
            'status' => 'confirmed',
            'reservation_code' => 'RES-JANRAYV1',
        ]);
        $session = ['is_admin' => true, 'admin_role' => 'full'];

        foreach (['jan', 'janr', 'example.com', '917123', 'silver reception', 'wedding celebra', 'marikina garden', 'accept', 'contracts/jan'] as $term) {
            $response = $this->withSession($session)->get(route('admin.reservations', ['search' => $term]));

            $response->assertOk();
            $response->assertViewHas('reservations', fn ($reservations) => $reservations->count() === 1);
        }

        $empty = $this->withSession($session)->get(route('admin.reservations', ['search' => 'no matching value']));
        $empty->assertViewHas('reservations', fn ($reservations) => $reservations->isEmpty());
    }

    public function test_admin_filter_forms_expose_live_result_targets(): void
    {
        $session = ['is_admin' => true, 'admin_role' => 'full'];

        $reservations = $this->withSession($session)->get(route('admin.reservations'));
        $reservations->assertSee('data-live-filter', false);
        $reservations->assertSee('id="reservation-results"', false);

        $logs = $this->withSession($session)->get(route('admin.activity-logs'));
        $logs->assertSee('data-live-filter', false);
        $logs->assertSee('id="activity-log-results"', false);
    }
}