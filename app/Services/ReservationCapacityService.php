<?php

namespace App\Services;

use App\Models\Reservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class ReservationCapacityService
{
    public function countForDate(string $eventDate, ?int $excludeReservationId = null): int
    {
        return Reservation::query()
            ->occupyingCapacity()
            ->whereDate('event_date', $eventDate)
            ->when($excludeReservationId !== null, fn ($query) => $query->whereKeyNot($excludeReservationId))
            ->count();
    }

    public function countsForDates(array $eventDates): Collection
    {
        if ($eventDates === []) {
            return collect();
        }

        return Reservation::query()
            ->occupyingCapacity()
            ->whereIn('event_date', array_values(array_unique($eventDates)))
            ->selectRaw('event_date, COUNT(*) as occupied')
            ->groupBy('event_date')
            ->pluck('occupied', 'event_date');
    }

    public function snapshotForDate(string $eventDate): array
    {
        $occupied = $this->countForDate($eventDate);

        return [
            'bookings' => $occupied,
            'capacity' => Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE,
            'remaining' => max(0, Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE - $occupied),
            'available' => $occupied < Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE,
        ];
    }

    public function lockDates(array $eventDates): void
    {
        $connection = DB::connection();
        if ($connection->transactionLevel() === 0) {
            throw new LogicException('Reservation capacity dates must be locked inside a database transaction.');
        }

        $dates = collect($eventDates)
            ->filter()
            ->map(fn ($date) => \Illuminate\Support\Carbon::parse($date)->toDateString())
            ->unique()
            ->sort()
            ->values();

        foreach ($dates as $date) {
            DB::table('reservation_capacity_locks')->insertOrIgnore(['event_date' => $date]);

            // The update also obtains a write lock on SQLite, where SELECT FOR UPDATE is unsupported.
            DB::table('reservation_capacity_locks')
                ->where('event_date', $date)
                ->update(['event_date' => $date]);

            if (! DB::table('reservation_capacity_locks')->where('event_date', $date)->lockForUpdate()->first()) {
                throw new RuntimeException('Unable to acquire the reservation capacity lock for '.$date.'.');
            }
        }
    }
}
