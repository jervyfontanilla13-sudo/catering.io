<?php

namespace Tests\Unit;

use App\Models\Reservation;
use PHPUnit\Framework\TestCase;

class ReservationTimelineTest extends TestCase
{
    private function states(Reservation $reservation): array
    {
        return array_column($reservation->timelineSteps(), 'state', 'key');
    }

    public function test_pending_shows_under_review_as_current(): void
    {
        $states = $this->states(new Reservation(['status' => 'pending']));

        $this->assertSame([
            'submitted' => 'complete',
            'under_review' => 'current',
            'accepted' => 'upcoming',
            'completed' => 'upcoming',
        ], $states);
    }

    public function test_confirmed_shows_accepted_as_current(): void
    {
        $states = $this->states(new Reservation(['status' => 'confirmed']));

        $this->assertSame([
            'submitted' => 'complete',
            'under_review' => 'complete',
            'accepted' => 'current',
            'completed' => 'upcoming',
        ], $states);
    }

    public function test_completed_shows_completed_as_current(): void
    {
        $states = $this->states(new Reservation(['status' => 'completed']));

        $this->assertSame([
            'submitted' => 'complete',
            'under_review' => 'complete',
            'accepted' => 'complete',
            'completed' => 'current',
        ], $states);
    }

    public function test_cancelled_stops_after_under_review_and_does_not_show_accepted_or_completed(): void
    {
        $steps = (new Reservation(['status' => 'cancelled']))->timelineSteps();
        $keys = array_column($steps, 'key');
        $states = array_column($steps, 'state', 'key');

        $this->assertSame(['submitted', 'under_review', 'cancelled'], $keys);
        $this->assertSame('complete', $states['submitted']);
        $this->assertSame('complete', $states['under_review']);
        $this->assertSame('cancelled', $states['cancelled']);
    }

    public function test_every_step_carries_the_exact_specified_description(): void
    {
        $steps = (new Reservation(['status' => 'pending']))->timelineSteps();
        $descriptions = array_column($steps, 'description', 'key');

        $this->assertSame('Your reservation request has been received.', $descriptions['submitted']);
        $this->assertSame('Our team is reviewing your reservation details.', $descriptions['under_review']);
        $this->assertSame('Your reservation has been accepted.', $descriptions['accepted']);
        $this->assertSame('Your event has been completed. Thank you for choosing 3YOS Catering.', $descriptions['completed']);

        $cancelledDescriptions = array_column((new Reservation(['status' => 'cancelled']))->timelineSteps(), 'description', 'key');
        $this->assertSame('Your reservation has been cancelled.', $cancelledDescriptions['cancelled']);
    }
}
