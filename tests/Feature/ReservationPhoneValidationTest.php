<?php

namespace Tests\Feature;

use Tests\TestCase;

class ReservationPhoneValidationTest extends TestCase
{
    public function test_reservation_accepts_local_09_phone_numbers(): void
    {
        $response = $this->from('/reservation')->post('/reservation', [
            'contact_number' => '09682676371',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionDoesntHaveErrors('contact_number');
    }

    public function test_reservation_accepts_exactly_ten_digits_after_plus_63_without_manual_budget(): void
    {
        \App\Models\Package::create([
            'name' => 'Test Package',
            'slug' => 'test-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);

        $response = $this->from('/reservation')->post('/reservation', [
            'full_name' => 'Test Client',
            'contact_number' => '+639171234567',
            'email' => 'client@example.com',
            'address' => '123 Main Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDay()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 50,
            'package_id' => 1,
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors(['event_date']);
        $response->assertSessionDoesntHaveErrors(['contact_number', 'estimated_budget']);
    }

    public function test_reservation_guest_count_is_not_limited_by_legacy_package_ranges(): void
    {
        \App\Models\Package::create([
            'name' => 'Legacy range package',
            'slug' => 'legacy-range-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 40,
        ]);

        $response = $this->from('/reservation')->post('/reservation', [
            'full_name' => 'Test Client',
            'contact_number' => '+639171234567',
            'email' => 'client@example.com',
            'address' => '123 Main Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDay()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 100,
            'package_id' => 1,
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertSessionHasErrors('event_date');
        $response->assertSessionDoesntHaveErrors('guest_count');
    }

    public function test_reservation_rejects_phone_numbers_with_extra_digits_or_spaces(): void
    {
        foreach (['+6391712345678', '+63 9171234567', '0917123456', '091712345678'] as $phoneNumber) {
            $response = $this->from('/reservation')->post('/reservation', [
                'contact_number' => $phoneNumber,
            ]);

            $response->assertSessionHasErrors('contact_number');
        }
    }

    public function test_inquiry_accepts_local_09_phone_numbers(): void
    {
        $response = $this->from('/inquiry')->post('/inquiry', [
            'contact_number' => '09682676371',
        ]);

        $response->assertRedirect('/inquiry');
        $response->assertSessionDoesntHaveErrors('contact_number');
    }
}