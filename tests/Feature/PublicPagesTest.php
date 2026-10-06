<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Inquiry;
use App\Mail\InquiryReplyMail;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    public function test_public_home_page_is_accessible(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_reservation_page_is_accessible(): void
    {
        $response = $this->get('/reservation');

        $response->assertStatus(200);
        $response->assertSee('placeholder="Juan dela Cruz"', false);
        $response->assertSee('pattern="(?:\\+63[0-9]{10}|09[0-9]{9})"', false);
        $response->assertSee('aria-describedby="date-availability"', false);
        $response->assertSee('data-next="2" disabled', false);
        $response->assertSee("dateAvailabilityState !== 'available'", false);
        $response->assertSee("setDateAvailability('available', selectedDate, ''", false);
        $response->assertSee('This date is currently fully booked. Please select another available date.', false);
        $response->assertDontSee('A maximum of 4 active reservations/events', false);
        $response->assertDontSee('${data.bookings}/${data.capacity}', false);
        $response->assertDontSee('event slot', false);
        $response->assertDontSee('occupied', false);
    }

    public function test_inquiry_page_is_accessible(): void
    {
        $response = $this->get('/inquiry');

        $response->assertStatus(200);
        $response->assertSee('pattern="(?:\\+63[0-9]{10}|09[0-9]{9})"', false);
    }

    public function test_inquiry_submission_requires_a_captcha_token(): void
    {
        $response = $this->from('/inquiry')->post(route('inquiry.store'), [
            'full_name' => 'Test Guest',
            'contact_number' => '09171234567',
            'email' => 'guest@example.com',
            'subject' => 'Catering question',
            'category' => 'Packages',
            'message' => 'Please share package details.',
            'website' => '',
            'form_started' => now()->timestamp,
        ]);

        $response->assertRedirect('/inquiry');
        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_reservation_submission_requires_a_captcha_token(): void
    {
        $package = Package::create([
            'name' => 'Classic Package',
            'slug' => 'classic-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
            'event_type' => 'Wedding',
        ]);

        $response = $this->from('/reservation')->post(route('reservation.store'), [
            'full_name' => 'Test Guest',
            'contact_number' => '09171234567',
            'email' => 'guest@example.com',
            'address' => '123 Example Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Community Hall',
            'guest_count' => 50,
            'package_id' => $package->id,
            'website' => '',
            'form_started' => now()->timestamp,
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors('g-recaptcha-response');
        $this->assertDatabaseCount('reservations', 0);
    }

    public function test_reservation_requires_two_day_lead_time(): void
    {
        Package::create([
            'name' => 'Classic Package',
            'slug' => 'classic-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
            'event_type' => 'Wedding',
        ]);

        $response = $this->from('/reservation')->post('/reservation', [
            'full_name' => 'Test User',
            'contact_number' => '09171234567',
            'email' => 'test@example.com',
            'address' => '123 Main Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDay()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Sample Venue',
            'guest_count' => 50,
            'estimated_budget' => 10000,
            'package_id' => 1,
            'website' => '',
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors('event_date');
    }

    public function test_reservation_rejects_invalid_contact_email_address_and_venue(): void
    {
        Package::create([
            'name' => 'Classic Package',
            'slug' => 'classic-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
            'event_type' => 'Wedding',
        ]);

        $response = $this->from('/reservation')->post('/reservation', [
            'full_name' => 'Test User',
            'contact_number' => '123',
            'email' => 'not-an-email',
            'address' => 'Apt',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'X',
            'guest_count' => 50,
            'estimated_budget' => 10000,
            'package_id' => 1,
            'website' => '',
            'form_started' => now()->timestamp,
            'g-recaptcha-response' => 'test',
        ]);

        $response->assertRedirect('/reservation');
        $response->assertSessionHasErrors(['contact_number', 'email', 'address', 'venue']);
    }

    public function test_admin_report_csv_export_uses_the_selected_period_summary(): void
    {
        $controller = app(\App\Http\Controllers\ReportController::class);
        $response = $controller->export(app(\App\Services\ReportService::class), 'daily');

        $csv = $response->getContent();

        $this->assertStringContainsString('Period', $csv);
        $this->assertStringContainsString('daily', strtolower($csv));
        $this->assertStringContainsString('Reservations', $csv);
    }

    public function test_admin_inquiry_reply_is_sent_to_the_customer_email(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        $inquiry = Inquiry::create([
            'full_name' => 'Inquiry Client',
            'contact_number' => '09171234567',
            'email' => 'customer@example.com',
            'subject' => 'Event inquiry',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'in_progress',
        ]);

        app(\App\Http\Controllers\AdminController::class)->replyToInquiry(
            new \Illuminate\Http\Request(['reply' => 'Thank you for reaching out.']),
            $inquiry,
        );

        Mail::assertSent(InquiryReplyMail::class, function (InquiryReplyMail $mail) {
            return $mail->hasTo('customer@example.com')
                && $mail->subjectLine === 'Re: Event inquiry'
                && $mail->reply === 'Thank you for reaching out.';
        });
    }

    public function test_admin_can_track_reservation_payment_status_and_balance(): void
    {
        $reservation = \App\Models\Reservation::create([
            'client_id' => null,
            'package_id' => 1,
            'full_name' => 'Test Client',
            'contact_number' => '09814542318',
            'email' => 'client@example.com',
            'address' => '123 Test Street, Cebu City',
            'event_type' => 'Wedding',
            'event_date' => now()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Nustad Hall',
            'guest_count' => 100,
            'estimated_budget' => 25000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-TEST-001',
        ]);

        $request = new \Illuminate\Http\Request([
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'total_cost' => 30000,
            'amount_paid' => 8000,
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->updateReservation(
            $request,
            $reservation,
            app(\App\Services\ReservationCapacityService::class),
        );

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $this->assertSame('Partially Paid', $reservation->fresh()->payment_status);
        $this->assertSame('Downpayment', $reservation->fresh()->payment_type);
        $this->assertSame(30000.0, (float) $reservation->fresh()->total_cost);
        $this->assertSame(8000.0, (float) $reservation->fresh()->amount_paid);
        $this->assertSame(22000.0, (float) $reservation->fresh()->balance);
        $this->assertNotNull($response);
    }

    public function test_admin_can_edit_accepted_reservation_schedule_and_package(): void
    {
        $originalPackage = Package::create([
            'name' => 'Original Package',
            'slug' => 'original-package',
            'price' => 500,
            'min_guests' => 20,
            'max_guests' => 200,
        ]);
        $updatedPackage = Package::create([
            'name' => 'Updated Package',
            'slug' => 'updated-package',
            'price' => 750,
            'min_guests' => 30,
            'max_guests' => 250,
        ]);
        $reservation = \App\Models\Reservation::create([
            'package_id' => $originalPackage->id,
            'full_name' => 'Accepted Client',
            'contact_number' => '09171234567',
            'email' => 'accepted@example.com',
            'address' => '123 Accepted Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Accepted Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-EDIT-001',
        ]);

        $request = new \Illuminate\Http\Request([
            'package_id' => $updatedPackage->id,
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '19:30',
        ]);

        app(\App\Http\Controllers\AdminController::class)->updateReservation(
            $request,
            $reservation,
            app(\App\Services\ReservationCapacityService::class),
        );

        $updated = $reservation->fresh();
        $this->assertSame($updatedPackage->id, $updated->package_id);
        $this->assertSame(now()->addDays(10)->toDateString(), $updated->event_date);
        $this->assertSame('19:30', $updated->event_time);
    }

    public function test_admin_reservations_can_be_filtered_by_status_and_payment_status(): void
    {
        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09171234567',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'amount_paid' => 6000,
            'balance' => 14000,
            'reservation_code' => 'RES-FILT-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09181234567',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(7)->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-FILT-002',
        ]);

        $request = new \Illuminate\Http\Request([
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->reservations($request);
        $data = $response->getData(true);

        $this->assertCount(1, $data['reservations']);
        $this->assertSame('Alice Client', $data['reservations'][0]->full_name);
        $this->assertSame('confirmed', $data['filterStatus']);
        $this->assertSame('Downpayment', $data['filterPaymentStatus']);
    }

    public function test_admin_reservations_can_be_filtered_by_date_range_and_customer_search(): void
    {
        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09170000001',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Downpayment',
            'payment_type' => 'Downpayment',
            'amount_paid' => 3000,
            'balance' => 17000,
            'reservation_code' => 'RES-FILT-SEARCH-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09180000002',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(15)->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-FILT-SEARCH-002',
        ]);

        $request = new \Illuminate\Http\Request([
            'search' => 'alice',
            'date_from' => now()->addDays(3)->toDateString(),
            'date_to' => now()->addDays(10)->toDateString(),
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->reservations($request);
        $data = $response->getData(true);

        $this->assertCount(1, $data['reservations']);
        $this->assertSame('Alice Client', $data['reservations'][0]->full_name);
        $this->assertSame('alice', $data['search']);
        $this->assertSame(now()->addDays(3)->toDateString(), $data['dateFrom']);
    }

    public function test_admin_reservations_export_uses_filtered_results(): void
    {
        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Alice Client',
            'contact_number' => '09170000003',
            'email' => 'alice@example.com',
            'address' => '123 Alice St',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Alice Venue',
            'guest_count' => 80,
            'estimated_budget' => 20000,
            'status' => 'confirmed',
            'payment_status' => 'Fully Paid',
            'payment_type' => 'Full Payment',
            'amount_paid' => 20000,
            'balance' => 0,
            'reservation_code' => 'RES-FILT-EXPORT-001',
        ]);

        \App\Models\Reservation::create([
            'package_id' => 1,
            'full_name' => 'Bob Client',
            'contact_number' => '09180000004',
            'email' => 'bob@example.com',
            'address' => '456 Bob St',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(12)->toDateString(),
            'event_time' => '17:00',
            'venue' => 'Bob Venue',
            'guest_count' => 50,
            'estimated_budget' => 12000,
            'status' => 'pending',
            'payment_status' => 'Unpaid',
            'payment_type' => 'Unpaid',
            'amount_paid' => 0,
            'balance' => 12000,
            'reservation_code' => 'RES-FILT-EXPORT-002',
        ]);

        $response = app(\App\Http\Controllers\AdminController::class)->exportReservationsCsv(new \Illuminate\Http\Request([
            'status' => 'confirmed',
            'search' => 'alice',
        ]));

        $csv = $response->getContent();

        $this->assertStringContainsString('RES-FILT-EXPORT-001', $csv);
        $this->assertStringNotContainsString('RES-FILT-EXPORT-002', $csv);
    }
}
