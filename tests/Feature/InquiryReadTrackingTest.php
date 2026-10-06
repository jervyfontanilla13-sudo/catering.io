<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InquiryReadTrackingTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    private function inquiry(array $overrides = []): Inquiry
    {
        return Inquiry::create($overrides + [
            'full_name' => 'Inquiry Client',
            'contact_number' => '09171234567',
            'email' => 'inquiry-'.uniqid().'@example.com',
            'subject' => 'Inquiry subject',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'new',
        ]);
    }

    public function test_priority_column_and_update_route_are_removed(): void
    {
        $this->assertFalse(Schema::hasColumn('inquiries', 'priority'));
        $this->assertFalse(Route::has('admin.inquiries.priority'));
    }

    public function test_inquiry_list_and_detail_no_longer_render_priority_controls_or_labels(): void
    {
        $inquiry = $this->inquiry();

        $this->withSession(self::ADMIN)
            ->get(route('admin.inquiries'))
            ->assertOk()
            ->assertDontSee('Set priority')
            ->assertDontSee('inquiry-priority')
            ->assertDontSee('priority-badge');

        $this->withSession(self::ADMIN)
            ->get(route('admin.inquiries.show', $inquiry))
            ->assertOk()
            ->assertDontSee('Set priority')
            ->assertDontSee('priority-badge');
    }

    public function test_viewing_an_inquiry_marks_it_read_but_does_not_change_status(): void
    {
        $inquiry = $this->inquiry(['status' => 'new']);
        $this->assertTrue($inquiry->isUnread());

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry))->assertOk();

        $inquiry->refresh();
        $this->assertFalse($inquiry->isUnread());
        $this->assertSame('new', $inquiry->status, 'Viewing must not silently change the status.');
    }

    public function test_viewing_twice_keeps_the_original_viewed_at_timestamp(): void
    {
        $inquiry = $this->inquiry(['status' => 'new']);

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry));
        $firstViewedAt = $inquiry->fresh()->viewed_at;

        $this->travel(5)->minutes();
        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry));

        $this->assertTrue($firstViewedAt->equalTo($inquiry->fresh()->viewed_at));
    }

    public function test_a_failed_reply_attempt_moves_new_to_in_progress(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $inquiry = $this->inquiry(['status' => 'new']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => 'Trying to reply.']);

        $inquiry->refresh();
        $this->assertSame('in_progress', $inquiry->status);
        $this->assertSame('Trying to reply.', $inquiry->admin_reply);
        $this->assertNull($inquiry->replied_at);
    }

    public function test_a_failed_reply_attempt_never_touches_a_responded_or_closed_inquiry(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $inquiry = $this->inquiry(['status' => 'closed']);

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => 'Trying to reply.']);

        $this->assertSame('closed', $inquiry->fresh()->status);
    }

    public function test_unread_dot_shows_for_unviewed_inquiries_and_disappears_after_viewing(): void
    {
        $inquiry = $this->inquiry(['status' => 'new']);

        // The CSS for .inquiry-card--unread always ships in the page's <style> block, so check for
        // the class actually applied to an element rather than the bare string appearing anywhere.
        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertSee('class="inquiry-card inquiry-card--unread"', false);

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry));

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertDontSee('class="inquiry-card inquiry-card--unread"', false);
    }
}
