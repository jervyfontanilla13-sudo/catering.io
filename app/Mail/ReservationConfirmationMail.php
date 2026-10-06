<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ReservationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reservation received: '.$this->reservation->reservation_code);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.reservation-confirmation',
            with: ['reservation' => $this->reservation],
        );
    }

    public function failed(Throwable $exception): void
    {
        report($exception);
    }
}
