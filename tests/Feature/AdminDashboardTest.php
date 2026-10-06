<?php

namespace Tests\Feature;

use App\Models\Reservation;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    public function test_admin_dashboard_restores_the_calendar_without_a_sidebar_link(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Calendar');
        $response->assertSee('id="reservationCalendar"', false);
        $response->assertSee('Previous month');
        $response->assertSee('Next month');
        $response->assertSee('Monthly schedule · Capacity');
        $response->assertDontSee('href="'.route('admin.dashboard').'#reservation-calendar"', false);
    }

    public function test_overview_uses_the_operational_command_center_hierarchy_and_quick_actions(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Welcome,')
            ->assertSee('Today / Upcoming')
            ->assertSee('Events today')
            ->assertSee('Payments due soon')
            ->assertSee('Next 7 days')
            ->assertSee('Inquiries needing response')
            ->assertSee('Quick Actions')
            ->assertSee('Review Reservations')
            ->assertSee('Client Inquiries')
            ->assertSee('Manage Packages')
            ->assertSee('View Reports')
            ->assertDontSee('Business overview')
            ->assertDontSee('Priority workspace');

        $content = $response->getContent();
        $this->assertLessThan(strpos($content, 'id="reservation-calendar"'), strpos($content, 'Welcome,'));
        $this->assertLessThan(strpos($content, 'Needs Attention'), strpos($content, 'id="reservation-calendar"'));
        $this->assertLessThan(strpos($content, 'Today / Upcoming'), strpos($content, 'Needs Attention'));
        $this->assertLessThan(strpos($content, 'Quick Actions'), strpos($content, 'Today / Upcoming'));

        $response->assertSee('href="'.route('admin.reservations').'"', false)
            ->assertSee('href="'.route('admin.inquiries').'"', false)
            ->assertSee('href="'.route('admin.packages.index').'"', false)
            ->assertSee('href="'.route('admin.reports').'"', false);
    }

    public function test_limited_admin_quick_actions_only_include_permitted_destinations(): void
    {
        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'limited',
        ])->get(route('admin.dashboard'));

        $response->assertOk()
            ->assertSee('Review Reservations')
            ->assertSee('Client Inquiries')
            ->assertDontSee('Manage Packages')
            ->assertDontSee('View Reports');
    }

    public function test_calendar_includes_event_details_without_a_duplicate_month_list(): void
    {
        Reservation::create([
            'full_name' => 'Calendar Test Client',
            'contact_number' => '+639171234567',
            'email' => 'calendar@example.com',
            'address' => '123 Main Street',
            'event_type' => 'Anniversary',
            'event_date' => now()->startOfMonth()->addDays(9)->toDateString(),
            'event_time' => '18:30',
            'venue' => 'Celebration Hall',
            'guest_count' => 80,
            'estimated_budget' => 56000,
            'status' => 'confirmed',
            'reservation_code' => 'RES-CALENDAR-01',
        ]);

        $response = $this->withSession([
            'is_admin' => true,
            'admin_role' => 'full',
        ])->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Calendar Test Client');
        $response->assertSee('Anniversary');
        $response->assertSee('Celebration Hall');
        $response->assertSee('calendar-hover-item', false);
        $response->assertSee('Open reservation detail.', false);
        $response->assertDontSee('id="calendarEvents"', false);
        $response->assertDontSee('<a class="calendar-event-detail', false);
    }
}
