<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Reservation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CompleteEndedReservations extends Command
{
    protected $signature = 'reservations:auto-complete-ended';

    protected $description = 'Automatically complete accepted reservations whose event date has ended';

    public function handle(): int
    {
        $timezone = config('app.timezone');
        $today = now($timezone)->toDateString();
        $reservationIds = Reservation::query()
            ->where('status', Reservation::STATUS_CONFIRMED)
            ->whereDate('event_date', '<=', $today)
            ->orderBy('id')
            ->pluck('id');
        $completedCount = 0;

        foreach ($reservationIds as $reservationId) {
            $completed = DB::transaction(function () use ($reservationId, $timezone): bool {
                $reservation = Reservation::query()
                    ->whereKey($reservationId)
                    ->lockForUpdate()
                    ->first();

                if (! $reservation || $reservation->status !== Reservation::STATUS_CONFIRMED) {
                    return false;
                }

                $timestamp = now($timezone);
                if ($reservation->event_date > $timestamp->toDateString()) {
                    return false;
                }

                $reservation->update(['status' => Reservation::STATUS_COMPLETED]);

                ActivityLog::create([
                    'user_id' => null,
                    'actor_name' => 'System',
                    'actor_role' => 'system',
                    'action' => 'Reservation automatically completed',
                    'method' => 'SCHEDULE',
                    'activity_date' => $timestamp->toDateString(),
                    'activity_time' => $timestamp->toTimeString(),
                    'description' => 'Reservation #'.$reservation->id.' automatically completed by system at '.$timestamp->format('g:i A').' because the scheduled reservation date has ended.',
                ]);

                return true;
            });

            if ($completed) {
                $completedCount++;
            }
        }

        $this->info("Automatically completed {$completedCount} reservation(s).");

        return self::SUCCESS;
    }
}
