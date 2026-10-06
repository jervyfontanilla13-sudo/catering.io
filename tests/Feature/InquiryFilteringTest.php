<?php

namespace Tests\Feature;

use App\Models\Inquiry;
use Tests\TestCase;

class InquiryFilteringTest extends TestCase
{
    private const ADMIN = ['is_admin' => true, 'admin_role' => 'full'];

    private function inquiry(array $overrides = []): Inquiry
    {
        return Inquiry::create($overrides + [
            'full_name' => 'Filter Client',
            'contact_number' => '09171234567',
            'email' => 'filter-'.uniqid().'@example.com',
            'subject' => 'Filter subject',
            'category' => 'Catering',
            'message' => 'Please send details.',
            'status' => 'new',
        ]);
    }

    /** Already-handled fixture that never qualifies for "Needs attention", so it can't leak
     *  into assertions meant to test the independent "All inquiries" list/filters. */
    private function handledInquiry(array $overrides = []): Inquiry
    {
        return $this->inquiry($overrides + [
            'status' => 'responded',
            'admin_reply' => 'Thanks for reaching out.',
            'replied_at' => now(),
        ]);
    }

    /** Everything from the "All inquiries" results container onward, since that section (unlike
     *  "Needs attention") is the one actually affected by the view and search filters. */
    private function allInquiriesSection(string $html): string
    {
        $position = strpos($html, 'id="inquiry-results"');
        $this->assertNotFalse($position, 'Expected to find the #inquiry-results container.');

        return substr($html, $position);
    }

    public function test_view_tab_narrows_the_all_inquiries_list(): void
    {
        $this->handledInquiry(['status' => 'closed', 'subject' => 'Closed one']);
        $this->handledInquiry(['subject' => 'Responded one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['view' => 'responded']));
        $response->assertOk();

        $section = $this->allInquiriesSection($response->getContent());
        $this->assertStringContainsString('Responded one', $section);
        $this->assertStringNotContainsString('Closed one', $section);
    }

    public function test_search_matches_subject_and_email(): void
    {
        $this->handledInquiry(['subject' => 'Wedding catering question', 'email' => 'bride@example.com']);
        $this->handledInquiry(['subject' => 'Corporate event pricing', 'email' => 'office@example.com']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['search' => 'wedding']));

        $response->assertOk();
        $response->assertSee('Wedding catering question');
        $response->assertDontSee('Corporate event pricing');
    }

    public function test_search_matches_inquiry_id(): void
    {
        // Fixed (non-random) emails: the default helper email uses uniqid(), which can coincidentally
        // contain the same digits as a small auto-increment ID and produce an unrelated LIKE match.
        $match = $this->handledInquiry(['subject' => 'Findable by ID', 'email' => 'match@example.com']);
        $this->handledInquiry(['subject' => 'Not this one', 'email' => 'other@example.com']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['search' => (string) $match->id]));

        $response->assertOk();
        $response->assertSee('Findable by ID');
        $response->assertDontSee('Not this one');
    }

    public function test_needs_attention_section_shows_new_and_unreplied_in_progress_only(): void
    {
        $this->inquiry(['status' => 'new', 'subject' => 'Needs attention A']);
        $this->inquiry(['status' => 'in_progress', 'admin_reply' => null, 'subject' => 'Needs attention B']);
        $this->handledInquiry(['subject' => 'Already handled']);
        $this->handledInquiry(['status' => 'closed', 'subject' => 'Closed one']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertOk();

        $html = $response->getContent();
        $needsAttentionSection = substr($html, 0, strpos($html, 'All inquiries'));

        $this->assertStringContainsString('Needs attention A', $needsAttentionSection);
        $this->assertStringContainsString('Needs attention B', $needsAttentionSection);
        $this->assertStringNotContainsString('Already handled', $needsAttentionSection);
        $this->assertStringNotContainsString('Closed one', $needsAttentionSection);
    }

    public function test_needs_attention_count_and_empty_state(): void
    {
        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertOk();
        $response->assertSee("You're all caught up", false);

        $this->inquiry(['status' => 'new']);
        $this->inquiry(['status' => 'new']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries'));
        $response->assertOk();
        $response->assertSee('2 inquiries need your response');
    }

    public function test_needs_attention_is_unaffected_by_all_inquiries_filters(): void
    {
        $this->inquiry(['status' => 'new', 'subject' => 'Always visible when needed']);

        $response = $this->withSession(self::ADMIN)->get(route('admin.inquiries', ['view' => 'closed', 'search' => 'nonsense']));

        $response->assertOk();
        // The "All inquiries" list is filtered down to nothing, but Needs Attention still shows it.
        $response->assertSee('Always visible when needed');
    }
}
