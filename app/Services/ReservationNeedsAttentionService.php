<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ReservationNeedsAttentionService
{
    public const NO_PAYMENT = 'no_payment';

    public const MISSING_CONTRACT = 'missing_contract';

    public const OUTSTANDING_BALANCE = 'outstanding_balance';

     public const EVENTS_AWAITING_COMPLETION = 'events_awaiting_completion';

     /**
      * @return array{
      *     counts: array{no_payment: int, missing_contract: int, outstanding_balance: int, events_awaiting_completion: int},
      *     reservation_ids: array{no_payment: array<int>, missing_contract: array<int>, outstanding_balance: array<int>, events_awaiting_completion: array<int>},
      *     financials: array<int, array|null>
      * }
     */
     public function analyze(Collection $reservations): array
     {
         $now = Carbon::now(config('app.timezone'));
         $reservationIds = [
             self::NO_PAYMENT => [],
             self::MISSING_CONTRACT => [],
             self::OUTSTANDING_BALANCE => [],
             self::EVENTS_AWAITING_COMPLETION => [],
         ];
         $financialsByReservation = [];

         foreach ($reservations as $reservation) {
             $isOpenAccepted = $reservation->status === Reservation::STATUS_CONFIRMED;
             if ($isOpenAccepted && $this->eventHasPassed($reservation, $now)) {
                 $reservationIds[self::EVENTS_AWAITING_COMPLETION][] = $reservation->id;
             }

             if ($isOpenAccepted && ! $reservation->hasCurrentContract()) {
                 $reservationIds[self::MISSING_CONTRACT][] = $reservation->id;
             }

            try {
                $financials = $reservation->financials();
            } catch (\LogicException $exception) {
                Log::warning('Dashboard omitted invalid reservation financials.', [
                    'reservation_id' => $reservation->id,
                    'reason' => $exception->getMessage(),
                ]);
                $financialsByReservation[$reservation->id] = null;
                continue;
            }

            $financialsByReservation[$reservation->id] = $financials;

            if (! $isOpenAccepted) {
                continue;
            }

            if ($financials['payment_status'] === 'Unpaid') {
                $reservationIds[self::NO_PAYMENT][] = $reservation->id;
            }

            if ($financials['contract_price_cents'] !== null
                && $financials['remaining_balance_cents'] !== null
                && $financials['remaining_balance_cents'] > 0) {
                $reservationIds[self::OUTSTANDING_BALANCE][] = $reservation->id;
            }
        }

        return [
            'counts' => array_map('count', $reservationIds),
            'reservation_ids' => $reservationIds,
            'financials' => $financialsByReservation,
        ];
    }

    /**
     * Compute category IDs from one canonical set of accepted, actionable reservations.
     *
     * @return array{no_payment: array<int>, missing_contract: array<int>, outstanding_balance: array<int>}
     */
    public function reservationIds(): array
    {
        $reservations = Reservation::query()
            ->openAccepted()
            ->with(['payments', 'refunds'])
            ->get();

        return $this->analyze($reservations)['reservation_ids'];
    }

    private function eventHasPassed(Reservation $reservation, Carbon $now): bool
    {
        if (! $reservation->event_date || ! $reservation->event_time) {
            return false;
        }

        $date = (string) $reservation->event_date;
        $time = (string) $reservation->event_time;
        $format = strlen($time) === 8 ? '!Y-m-d H:i:s' : '!Y-m-d H:i';
        $displayFormat = strlen($time) === 8 ? 'Y-m-d H:i:s' : 'Y-m-d H:i';

        try {
            $eventAt = Carbon::createFromFormat($format, $date.' '.$time, config('app.timezone'));
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $eventAt !== false
            && $eventAt->format($displayFormat) === $date.' '.$time
            && $eventAt->lt($now);
    }
}
