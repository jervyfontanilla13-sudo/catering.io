<?php

namespace Tests\Feature;

use App\Mail\InquiryReplyMail;
use App\Models\Inquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InquiryReplyStatusTest extends TestCase
{
    use RefreshDatabase;

    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full', 'admin_name' => 'Inquiry Tester', 'admin_email' => 'tester@3yos.com'];

    private function inquiry(array $overrides = []): Inquiry
    {
        return Inquiry::create($overrides + [
            'full_name' => 'Inquiry Client',
            'contact_number' => '09171234567',
            'email' => 'client@example.com',
            'subject' => 'Event inquiry',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'in_progress',
        ]);
    }

    public function test_sending_a_reply_marks_the_inquiry_responded_only_after_the_email_sends(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $inquiry = $this->inquiry();

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => 'Thank you for reaching out.'])
            ->assertSessionHas('success', 'Reply sent successfully. Inquiry marked as Responded.');

        $inquiry->refresh();
        $this->assertSame('responded', $inquiry->status);
        $this->assertSame('Thank you for reaching out.', $inquiry->admin_reply);
        $this->assertNotNull($inquiry->replied_at);
        Mail::assertSent(InquiryReplyMail::class);
    }

    public function test_an_empty_reply_is_rejected_without_sending_mail_or_changing_status(): void
    {
        Mail::fake();
        $inquiry = $this->inquiry();

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => ''])
            ->assertSessionHasErrors('reply');

        Mail::assertNothingSent();
        $this->assertSame('in_progress', $inquiry->fresh()->status);
    }

    public function test_a_failed_send_does_not_change_the_status(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP connection refused'));
        $inquiry = $this->inquiry();

        $this->withSession(self::ADMIN)
            ->post(route('admin.inquiries.reply', $inquiry), ['reply' => 'Trying to reply.'])
            ->assertSessionHas('error', 'Failed to send reply. The inquiry status was not changed. Details: SMTP connection refused');

        $inquiry->refresh();
        $this->assertSame('in_progress', $inquiry->status);
        $this->assertNull($inquiry->replied_at);
    }

    public function test_the_inquiry_list_no_longer_exposes_a_status_dropdown_or_save_button(): void
    {
        $inquiry = $this->inquiry();

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));

        $response->assertOk();
        $response->assertDontSee('inquiry-status-select', false);
        $response->assertSee('In Progress');
        $response->assertSee(route('admin.inquiries.show', $inquiry), false);
    }

    public function test_the_status_update_route_no_longer_exists(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('admin.inquiries.status'));
    }

    public function test_the_card_shows_the_status_without_a_dropdown(): void
    {
        $inquiry = $this->inquiry(['status' => 'responded', 'admin_reply' => 'Thanks', 'replied_at' => now()]);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));

        $response->assertOk();
        $response->assertSee('inquiry-card', false);
        $response->assertSeeInOrder([$inquiry->full_name, $inquiry->subject, 'Responded'], false);
        // The list page has status filter tabs but no per-row/per-card status editor.
        $response->assertDontSee('inquiry-status-select', false);
        preg_match('/<article class="inquiry-card[^"]*">.*?<\/article>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Expected to find a rendered inquiry card.');
        $this->assertStringNotContainsString('<select', $matches[0]);
    }

    public function test_the_inquiry_detail_page_shows_the_current_status(): void
    {
        $inquiry = $this->inquiry(['status' => 'responded']);

        $this->withSession(self::ADMIN)->get(route('admin.inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('status-badge--confirmed', false)
            ->assertSee('Responded');
    }
}
