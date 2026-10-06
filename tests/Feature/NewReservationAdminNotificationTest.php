<?php

namespace Tests\Feature;

use App\Mail\NewReservationAdminMail;
use App\Mail\ReservationConfirmationMail;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\User;
use App\Services\PrimaryAdminReservationNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NewReservationAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function primaryAdmin(string $name, string $email, bool $active = true): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'test-password',
            'role' => 'full',
            'is_active' => $active,
        ]);
    }

    private function package(): Package
    {
        return Package::create([
            'name' => 'Garden Celebration Package',
            'slug' => 'garden-celebration-'.uniqid(),
            'price' => 600,
        ]);
    }

    private function mockSuccessfulRecaptcha(int $times = 1): void
    {
        $result = \Mockery::mock();
        $result->shouldReceive('isSuccess')->times($times)->andReturn(true);

        \Mockery::mock('overload:ReCaptcha\ReCaptcha')
            ->shouldReceive('verify')
            ->times($times)
            ->andReturn($result);
    }

    private function reservationSubmission(int $packageId, string $email = 'client@example.com'): array
    {
        return [
            'full_name' => 'Jamie Client',
            'contact_number' => '09171234567',
            'email' => $email,
            'address' => '123 Example Avenue',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 85,
            'package_id' => $packageId,
            'additional_services' => 'Buffet setup',
            'submission_key' => Str::random(64),
            'form_started' => now()->subSeconds(5)->timestamp,
            'g-recaptcha-response' => 'valid-test-token',
        ];
    }

    public function test_successful_public_submission_notifies_each_active_primary_admin_once(): void
    {
        Mail::fake();
        $disabled = $this->primaryAdmin('Disabled Admin', 'disabled-primary@example.com', false);
        $primaryAdmin = $this->primaryAdmin('Current Primary', 'current-primary@example.com');
        $secondPrimaryAdmin = $this->primaryAdmin('Second Primary', 'second-primary@example.com');
        $this->primaryAdmin('Team Admin', 'team-admin@example.com')->update(['role' => 'limited']);
        $package = $this->package();
        $eventDate = now()->addDays(10)->toDateString();

        for ($i = 0; $i < Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE - 1; $i++) {
            Reservation::create([
                'package_id' => $package->id,
                'full_name' => "Existing Client {$i}",
                'contact_number' => '09171234567',
                'email' => "existing-{$i}@example.com",
                'address' => '123 Existing Street',
                'event_type' => 'Birthday',
                'event_date' => $eventDate,
                'event_time' => '18:00',
                'venue' => 'Existing Hall',
                'guest_count' => 20,
                'estimated_budget' => 12000,
                'status' => Reservation::STATUS_PENDING,
            ]);
        }

        $this->mockSuccessfulRecaptcha();
        $payload = $this->reservationSubmission($package->id);
        $this->post(route('reservation.store'), $payload)->assertRedirect();

        $reservation = Reservation::query()->where('email', 'client@example.com')->firstOrFail();
        Mail::assertQueuedTimes(NewReservationAdminMail::class, 2);
        Mail::assertQueued(NewReservationAdminMail::class, function (NewReservationAdminMail $mail) use ($primaryAdmin, $reservation): bool {
            $rendered = $mail->render();

            return $mail->hasTo($primaryAdmin->email)
                && $mail->connection === 'background'
                && $mail->envelope()->subject === 'New Reservation Received – 3YOS Catering Management System'
                && str_contains($rendered, 'Jamie Client')
                && str_contains($rendered, (string) $reservation->id)
                && str_contains($rendered, $reservation->reservation_code)
                && str_contains($rendered, 'Wedding')
                && str_contains($rendered, Carbon::parse($reservation->event_date)->format('F j, Y'))
                && str_contains($rendered, '18:00')
                && str_contains($rendered, 'Garden Hall')
                && str_contains($rendered, '85')
                && str_contains($rendered, 'Garden Celebration Package')
                && str_contains($rendered, 'Buffet setup')
                && str_contains($rendered, 'Under Review')
                && str_contains($rendered, $reservation->created_at->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A'))
                && str_contains($rendered, route('admin.reservations.show', $reservation));
        });
        Mail::assertQueued(NewReservationAdminMail::class, fn (NewReservationAdminMail $mail): bool => $mail->hasTo($secondPrimaryAdmin->email)
            && $mail->connection === 'background');
        Mail::assertNotQueued(NewReservationAdminMail::class, fn (NewReservationAdminMail $mail): bool => $mail->hasTo($disabled->email));
        Mail::assertNotQueued(NewReservationAdminMail::class, fn (NewReservationAdminMail $mail): bool => $mail->hasTo('team-admin@example.com'));
        Mail::assertQueued(ReservationConfirmationMail::class, fn (ReservationConfirmationMail $mail): bool => $mail->hasTo('client@example.com')
            && $mail->connection === 'background');
        $this->assertNotSame($disabled->email, $primaryAdmin->email);

        $this->from(route('reservation'))->post(route('reservation.store'), $payload)
            ->assertRedirect(route('reservation', ['code' => $reservation->reservation_code]));

        Mail::assertQueuedTimes(NewReservationAdminMail::class, 2);
        $this->assertSame(4, Reservation::whereDate('event_date', $eventDate)->count());
        Mail::assertQueued(ReservationConfirmationMail::class, 1);
    }

    public function test_primary_admin_handover_changes_the_recipient_for_future_reservations(): void
    {
        Mail::fake();
        $firstAdmin = $this->primaryAdmin('Initial Primary', 'initial-primary@example.com');
        $nextAdmin = $this->primaryAdmin('New Primary', 'new-primary@example.com');
        $reservation = Reservation::create([
            'full_name' => 'Jamie Client',
            'contact_number' => '09171234567',
            'email' => 'handover-client@example.com',
            'address' => '123 Example Avenue',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 85,
            'status' => Reservation::STATUS_PENDING,
        ]);

        app(PrimaryAdminReservationNotifier::class)->notify($reservation);
        $firstAdmin->update(['is_active' => false]);
        app(PrimaryAdminReservationNotifier::class)->notify($reservation);

        Mail::assertQueuedTimes(NewReservationAdminMail::class, 3);
        Mail::assertQueued(NewReservationAdminMail::class, fn (NewReservationAdminMail $mail): bool => $mail->hasTo($firstAdmin->email)
            && $mail->connection === 'background');
        $this->assertSame(
            2,
            Mail::queued(NewReservationAdminMail::class)->filter(
                fn (NewReservationAdminMail $mail): bool => $mail->hasTo($nextAdmin->email)
            )->count(),
        );
        Mail::assertNotQueued(NewReservationAdminMail::class, fn (NewReservationAdminMail $mail): bool => $mail->hasTo('team-admin@example.com'));
    }

    public function test_invalid_reservation_submission_does_not_send_a_notification(): void
    {
        Mail::fake();

        $this->post(route('reservation.store'), [
            'full_name' => 'Jamie Client',
            'event_date' => now()->addDays(10)->toDateString(),
        ])->assertSessionHasErrors();

        Mail::assertNothingOutgoing();
        $this->assertSame(0, Reservation::count());
    }

    public function test_mail_delivery_failure_does_not_remove_or_change_a_saved_reservation(): void
    {
        $primaryAdmin = $this->primaryAdmin('Current Primary', 'current-primary@example.com');
        $reservation = Reservation::create([
            'full_name' => 'Saved Client',
            'contact_number' => '09171234567',
            'email' => 'saved-client@example.com',
            'address' => '123 Example Avenue',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 85,
            'status' => Reservation::STATUS_PENDING,
        ]);
        Mail::shouldReceive('to')
            ->once()
            ->with($primaryAdmin->email, $primaryAdmin->name)
            ->andThrow(new \RuntimeException('SMTP connection refused'));

        app(PrimaryAdminReservationNotifier::class)->notify($reservation);

        $this->assertSame(Reservation::STATUS_PENDING, $reservation->fresh()->status);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }

    public function test_one_admin_delivery_failure_does_not_prevent_delivery_to_other_primary_admins(): void
    {
        $failedAdmin = $this->primaryAdmin('Unavailable Primary', 'unavailable-primary@example.com');
        $deliveredAdmin = $this->primaryAdmin('Available Primary', 'available-primary@example.com');
        $reservation = Reservation::create([
            'full_name' => 'Saved Client',
            'contact_number' => '09171234567',
            'email' => 'saved-client@example.com',
            'address' => '123 Example Avenue',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Garden Hall',
            'guest_count' => 85,
            'status' => Reservation::STATUS_PENDING,
        ]);
        $pendingMail = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pendingMail->shouldReceive('queue')
            ->once()
            ->with(\Mockery::type(NewReservationAdminMail::class));

        Mail::shouldReceive('to')
            ->once()
            ->with($failedAdmin->email, $failedAdmin->name)
            ->andThrow(new \RuntimeException('SMTP connection refused'));
        Mail::shouldReceive('to')
            ->once()
            ->with($deliveredAdmin->email, $deliveredAdmin->name)
            ->andReturn($pendingMail);

        app(PrimaryAdminReservationNotifier::class)->notify($reservation);

        $this->assertSame(Reservation::STATUS_PENDING, $reservation->fresh()->status);
        $this->assertDatabaseHas('reservations', ['id' => $reservation->id]);
    }
}
