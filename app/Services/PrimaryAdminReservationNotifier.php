<?php

namespace App\Services;

use App\Mail\NewReservationAdminMail;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PrimaryAdminReservationNotifier
{
    public function notify(Reservation $reservation): void
    {
        $primaryAdmins = User::query()
            ->where('role', 'full')
            ->where('is_active', true)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->orderBy('id')
            ->get();

        if ($primaryAdmins->isEmpty()) {
            Log::warning('New reservation notification was not sent because there is no active Primary Admin account.', [
                'reservation_id' => $reservation->id,
            ]);

            return;
        }

        $reservation->loadMissing('package');

        foreach ($primaryAdmins as $primaryAdmin) {
            if (! filter_var($primaryAdmin->email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('New reservation notification was skipped because a Primary Admin account has an invalid email address.', [
                    'reservation_id' => $reservation->id,
                    'primary_admin_id' => $primaryAdmin->id,
                ]);

                continue;
            }

            try {
                Mail::to($primaryAdmin->email, $primaryAdmin->name)
                    ->send(new NewReservationAdminMail($reservation));
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
}
