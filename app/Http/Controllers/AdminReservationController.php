<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAdminReservationRequest;
use App\Mail\ReservationConfirmationMail;
use App\Models\Client;
use App\Models\Package;
use App\Models\Reservation;
use App\Services\ReservationCapacityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminReservationController extends Controller
{
    public function create()
    {
        $packages = Package::orderBy('price')->get(['id', 'name', 'price']);

        return view('admin.reservation-form', compact('packages'));
    }

    public function store(StoreAdminReservationRequest $request, ReservationCapacityService $capacity)
    {
        $data = $request->validated();
        $package = Package::findOrFail($data['package_id']);
        $reservationCode = $this->generateReservationCode();

        $reservation = DB::transaction(function () use ($data, $package, $reservationCode, $capacity) {
            $capacity->lockDates([$data['event_date']]);

            if ($capacity->countForDate($data['event_date']) >= Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE) {
                throw ValidationException::withMessages([
                    'event_date' => 'This date is fully booked with '.Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE.' active reservations. Choose another date.',
                ]);
            }

            $client = Client::firstOrCreate(
                ['email' => $data['email']],
                [
                    'name' => $data['full_name'],
                    'phone' => $data['contact_number'],
                    'address' => $data['address'],
                ],
            );

            return Reservation::create([
                'client_id' => $client->id,
                'package_id' => $package->id,
                'full_name' => $data['full_name'],
                'contact_number' => $data['contact_number'],
                'email' => $data['email'],
                'address' => $data['address'],
                'event_type' => $data['event_type'],
                'event_date' => $data['event_date'],
                'event_time' => $data['event_time'],
                'venue' => $data['venue'],
                'guest_count' => $data['guest_count'],
                'estimated_budget' => $package->estimatedTotalFor((int) $data['guest_count']),
                'additional_services' => $data['additional_services'] ?? null,
                'special_requests' => $data['special_requests'] ?? null,
                'additional_notes' => $data['additional_notes'] ?? null,
                'status' => 'pending',
                'reservation_code' => $reservationCode,
            ]);
        }, 3);

        try {
            Mail::to($reservation->email, $reservation->full_name)->send(new ReservationConfirmationMail($reservation));
            $mailMessage = in_array(config('mail.default'), ['log', 'array'], true)
                ? ' Email delivery is not enabled; the reservation ID is shown in this list.'
                : ' A confirmation email was sent to '.$reservation->email.'.';
        } catch (\Throwable $exception) {
            report($exception);
            $mailMessage = ' Email delivery failed; the reservation was still saved.';
        }

        return redirect()->route('admin.reservations')->with(
            'success',
            'Booking created. Reservation ID: '.$reservationCode.'.'.$mailMessage,
        );
    }

    private function generateReservationCode(): string
    {
        do {
            $code = 'RES-'.strtoupper(Str::random(8));
        } while (Reservation::where('reservation_code', $code)->exists());

        return $code;
    }
}