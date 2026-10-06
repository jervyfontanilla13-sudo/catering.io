<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewReservationAdminMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New Reservation Received – 3YOS Catering Management System');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-reservation-admin',
            with: ['reservation' => $this->reservation],
        );
    }
}
