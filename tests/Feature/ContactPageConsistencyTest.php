<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContactPageConsistencyTest extends TestCase
{
    public function test_contact_page_shows_the_same_details_as_the_sitewide_footer(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee('3yoscatering@gmail.com');
        $response->assertSee('0998 242 2719');
        $response->assertSee('Marikina City, Metro Manila');
        // The old placeholder-looking values must be gone.
        $response->assertDontSee('info@3yos.com');
        $response->assertDontSee('+63 912 345 6789');
    }

    public function test_contact_page_links_to_the_inquiry_form(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();
        $response->assertSee(route('inquiry'), false);
    }
}
