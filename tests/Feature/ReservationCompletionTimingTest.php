<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Package;
use App\Models\Reservation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReservationCompletionTimingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Completion Tester', 'admin_email' => 'completion@3yos.com'];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reservation(array $overrides = []): Reservation
    {
        $package = Package::create(['name' => 'Completion Package', 'slug' => 'completion-'.uniqid(), 'price' => 800]);

        return Reservation::create($overrides + [
            'package_id' => $package->id,
            'full_name' => 'Completion Client',
            'contact_number' => '09171234567',
            'email' => 'completion-'.uniqid().'@example.com',
            'address' => '1 Completion Street',
            'event_type' => 'Wedding',
            'event_date' => now()->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Completion Hall',
            'guest_count' => 60,
            'estimated_budget' => 48000,
            'total_cost' => 48000,
            'status' => Reservation::STATUS_CONFIRMED,
            'reservation_code' => 'RES-COMP'.random_int(100000, 999999),
        ]);
    }

    public function test_admin_cannot_manually_complete_before_the_event_date(): void
    {
        $timezone = config('app.timezone');
        Carbon::setTestNow(Carbon::create(2026, 10, 19, 12, 0, 0, $timezone));
        $reservation = $this->reservation(['event_date' => '2026-10-20']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.complete', $reservation))
            ->assertSessionHasErrors('status');

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_admin_can_manually_complete_on_the_event_date_and_activity_is_logged_once(): void
    {
        $timezone = config('app.timezone');
        Carbon::setTestNow(Carbon::create(2026, 10, 20, 23, 58, 0, $timezone));
        $reservation = $this->reservation(['event_date' => '2026-10-20']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.complete', $reservation))
            ->assertSessionHas('success', 'Reservation marked as completed.');

        $this->assertSame(Reservation::STATUS_COMPLETED, $reservation->fresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'Reservation status changed')->count());
        $activity = ActivityLog::where('action', 'Reservation status changed')->firstOrFail();
        $this->assertSame('Changed reservation #'.$reservation->id.' status from Accepted to Completed.', $activity->description);
        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.complete', $reservation))
            ->assertSessionHasErrors('status');
        $this->assertSame(1, ActivityLog::where('action', 'Reservation status changed')->count());
    }

    public function test_admin_cannot_manually_complete_after_the_event_date(): void
    {
        $timezone = config('app.timezone');
        Carbon::setTestNow(Carbon::create(2026, 10, 21, 0, 1, 0, $timezone));
        $reservation = $this->reservation(['event_date' => '2026-10-20']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.reservations.complete', $reservation))
            ->assertSessionHasErrors('status');

        $this->assertSame(Reservation::STATUS_CONFIRMED, $reservation->fresh()->status);
        $this->assertSame(0, ActivityLog::where('action', 'Reservation status changed')->count());
    }

    public function test_complete_button_is_only_shown_for_accepted_reservation_on_event_date(): void
    {
        $timezone = config('app.timezone');
        Carbon::setTestNow(Carbon::create(2026, 10, 20, 12, 0, 0, $timezone));

        $accepted = $this->reservation(['event_date' => '2026-10-20']);
        $pending = $this->reservation([
            'event_date' => '2026-10-20',
            'status' => Reservation::STATUS_PENDING,
        ]);
        $pendingPage = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $pending));
        $pendingPage->assertOk()
            ->assertSee('Accept')
            ->assertSee('Cancel')
            ->assertDontSee(route('admin.reservations.complete', $pending), false);

        $page = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $accepted));
        $page->assertOk()
            ->assertSeeText('Reservation Status: Accepted')
            ->assertSee('Complete')
            ->assertSee('Cancel')
            ->assertSee(route('admin.reservations.complete', $accepted), false)
            ->assertSee(route('admin.reservations.cancel', $accepted), false);

        foreach ([Reservation::STATUS_COMPLETED, Reservation::STATUS_CANCELLED] as $finalStatus) {
            $finalReservation = $this->reservation([
                'event_date' => '2026-10-20',
                'status' => $finalStatus,
            ]);
            $finalPage = $this->withSession(self::ADMIN)->get(route('admin.reservations.show', $finalReservation));
            $finalPage->assertOk()
                ->assertDontSee(route('admin.reservations.complete', $finalReservation), false)
                ->assertDontSee(route('admin.reservations.cancel', $finalReservation), false);
        }
    }

    public function test_automatic_completion_at_1159_pm_logs_once_and_is_idempotent(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        Carbon::setTestNow(Carbon::create(2026, 10, 20, 23, 59, 0, 'Asia/Manila'));
        $reservation = $this->reservation(['event_date' => '2026-10-20']);

        $this->artisan('reservations:auto-complete-ended')->assertSuccessful();
        $this->artisan('reservations:auto-complete-ended')->assertSuccessful();

        $this->assertSame(Reservation::STATUS_COMPLETED, $reservation->fresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'Reservation automatically completed')->count());
        $activity = ActivityLog::where('action', 'Reservation automatically completed')->firstOrFail();
        $this->assertSame('System', $activity->actor_name);
        $this->assertSame('system', $activity->actor_role);
        $this->assertSame('2026-10-20', $activity->activity_date);
        $this->assertSame('23:59:00', $activity->activity_time);
        $this->assertSame(
            'Reservation #'.$reservation->id.' automatically completed by system at 11:59 PM because the scheduled reservation date has ended.',
            $activity->description,
        );
    }

    public function test_automatic_completion_catches_up_missed_event_dates_but_skips_pending_cancelled_and_completed(): void
    {
        $timezone = config('app.timezone');
        Carbon::setTestNow(Carbon::create(2026, 10, 22, 0, 5, 0, $timezone));
        $overdue = $this->reservation(['event_date' => '2026-10-20']);
        $pending = $this->reservation(['event_date' => '2026-10-20', 'status' => Reservation::STATUS_PENDING]);
        $cancelled = $this->reservation(['event_date' => '2026-10-20', 'status' => Reservation::STATUS_CANCELLED]);
        $completed = $this->reservation(['event_date' => '2026-10-20', 'status' => Reservation::STATUS_COMPLETED]);
        $future = $this->reservation(['event_date' => '2026-10-23']);

        $this->artisan('reservations:auto-complete-ended')->assertSuccessful();

        $this->assertSame(Reservation::STATUS_COMPLETED, $overdue->fresh()->status);
        $this->assertSame(Reservation::STATUS_PENDING, $pending->fresh()->status);
        $this->assertSame(Reservation::STATUS_CANCELLED, $cancelled->fresh()->status);
        $this->assertSame(Reservation::STATUS_COMPLETED, $completed->fresh()->status);
        $this->assertSame(Reservation::STATUS_CONFIRMED, $future->fresh()->status);
        $this->assertSame(1, ActivityLog::where('action', 'Reservation automatically completed')->count());
        $this->assertSame(0, ActivityLog::where('action', 'Reservation status changed')->count());
    }

    public function test_completion_schedule_uses_the_configured_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Manila']);
        $events = app(\Illuminate\Console\Scheduling\Schedule::class)->events();
        $event = collect($events)->first(fn ($scheduled) => str_contains($scheduled->command, 'reservations:auto-complete-ended'));

        $this->assertNotNull($event);
        $this->assertSame('Asia/Manila', $event->timezone);
        $this->assertTrue($event->expression === '59 23 * * *');
    }
}
