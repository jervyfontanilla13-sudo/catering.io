<?php

namespace Tests\Feature;

use App\Mail\ReservationConfirmationMail;
use App\Models\Reservation;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReservationNotificationMailTest extends TestCase
{
    public function test_reservation_confirmation_email_contains_the_reservation_id(): void
    {
        Mail::fake();
        $reservation = new Reservation([
            'full_name' => 'Test Client',
            'email' => 'client@gmail.com',
            'event_type' => 'Wedding',
            'event_date' => '2027-01-15',
            'guest_count' => 80,
            'reservation_code' => 'RES-TEST1234',
        ]);

        Mail::to($reservation->email, $reservation->full_name)->send(new ReservationConfirmationMail($reservation));

        Mail::assertSent(ReservationConfirmationMail::class, function (ReservationConfirmationMail $mail): bool {
            return $mail->hasTo('client@gmail.com')
                && str_contains($mail->render(), 'RES-TEST1234');
        });
    }
}