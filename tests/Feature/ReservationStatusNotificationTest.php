<?php

namespace Tests\Feature;

use App\Mail\ReservationAcceptedMail;
use App\Mail\ReservationCancelledMail;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationStatusNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationStatusNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Status Tester', 'admin_email' => 'tester@3yos.com'];

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Silver', 'slug' => 'silver-'.uniqid(), 'price' => 750, 'min_guests' => 20, 'max_guests' => 200]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Status Client',
            'contact_number' => '09171234567',
            'email' => 'status@example.com',
            'address' => '1 Status Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addMonth()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Status Hall',
            'guest_count' => 80,
            'estimated_budget' => 60000,
            'total_cost' => 60000,
            'status' => 'pending',
            'reservation_code' => 'RES-STAT'.random_int(1000, 9999),
        ]);
    }

    public function test_accepting_a_reservation_sends_and_logs_an_email(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $reservation))
            ->assertSessionHas('success', 'Reservation accepted and notification email sent.');

        Mail::assertSent(ReservationAcceptedMail::class, fn ($mail) => $mail->hasTo('status@example.com'));
        $this->assertSame(1, ReservationStatusNotification::where('notification_type', 'accepted')->where('status', 'sent')->count());
    }

    public function test_cancelling_a_reservation_sends_and_logs_an_email(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.cancel', $reservation))
            ->assertSessionHas('success', 'Reservation cancelled and notification email sent.');

        Mail::assertSent(ReservationCancelledMail::class, fn ($mail) => $mail->hasTo('status@example.com'));
        $this->assertSame(1, ReservationStatusNotification::where('notification_type', 'cancelled')->where('status', 'sent')->count());
    }

    public function test_saving_without_changing_status_does_not_send_an_email(): void
    {
        Mail::fake();
        $reservation = $this->reservation(['status' => 'confirmed']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $reservation))
            ->assertSessionHas('success', 'Reservation saved successfully.');

        Mail::assertNothingSent();
        $this->assertSame(0, ReservationStatusNotification::count());
    }

    public function test_repeating_the_same_status_after_it_already_applied_does_not_resend(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)->post(route('admin.reservations.accept', $reservation));
        $this->withSession(self::ADMIN)->post(route('admin.reservations.accept', $reservation))
            ->assertSessionHas('success', 'Reservation saved successfully.');

        Mail::assertSent(ReservationAcceptedMail::class, 1);
        $this->assertSame(1, ReservationStatusNotification::count());
    }

    public function test_direct_status_submission_is_rejected_without_sending_a_notification(): void
    {
        Mail::fake();
        $reservation = $this->reservation();

        foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $status) {
            $this->withSession(self::ADMIN)
                ->patch(route('admin.reservations.update', $reservation), ['status' => $status])
                ->assertSessionHasErrors('status');
        }
        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $reservation), ['status' => 'completed'])
            ->assertSessionHasErrors('status');
        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.complete', $reservation), ['status' => 'cancelled'])
            ->assertSessionHasErrors('status');
        $this->withSession(self::ADMIN)
            ->patch('/admin/reservations/'.$reservation->id.'/status', ['status' => 'cancelled'])
            ->assertNotFound();

        Mail::assertNothingSent();
        $this->assertSame(0, ReservationStatusNotification::count());
        $this->assertSame('pending', $reservation->fresh()->status);
    }

    public function test_email_failure_reports_the_error_without_blocking_the_status_change(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $reservation = $this->reservation();

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.accept', $reservation))
            ->assertSessionHas('success', 'Reservation accepted, but the notification email could not be sent.');

        $this->assertSame('confirmed', $reservation->fresh()->status);
        $log = ReservationStatusNotification::first();
        $this->assertSame('failed', $log->status);
        $this->assertSame('SMTP connection refused', $log->error_message);
    }
}
