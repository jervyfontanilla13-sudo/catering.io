<?php

namespace Tests\Feature;

use App\Models\GalleryItem;
use App\Models\Package;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicCatalogFeaturesTest extends TestCase
{
    public function test_reservation_time_has_a_native_required_input_and_desktop_clock_fallback(): void
    {
        $response = $this->get(route('reservation'));

        $response->assertOk();
        $response->assertSee('for="event_time">Event time</label>', false);
        $response->assertSee('type="time" name="event_time" id="event_time"', false);
        $response->assertSee('id="event_time" value="" class="form-control" required', false);
        $response->assertSee('id="clock-time-picker" hidden', false);
    }

    public function test_reservation_review_cards_keep_readable_text_colors_in_both_themes(): void
    {
        $response = $this->get(route('reservation'));

        $response->assertOk();
        $response->assertSee('.review-block{padding:1.1rem 1.25rem;border:1px solid #e7ddd0;border-radius:16px;background:#fff;color:#20201d}', false);
        $response->assertSee('.review-block-head h3{margin:0;font-family:\'Playfair Display\',Georgia,serif;font-size:1.05rem;color:#6d3024}', false);
        $response->assertSee('.review-edit{border:0;background:transparent;color:#6d3024;font-size:.76rem;font-weight:700;text-decoration:underline;cursor:pointer;padding:0}', false);
        $response->assertSee('.review-list dt{font-size:.68rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#6f6d66}', false);
        $response->assertSee('.review-list dd{margin:.15rem 0 0;overflow-wrap:anywhere;color:#20201d}', false);
        $response->assertSee('id="review-event_type"', false);
        $response->assertSee('id="review-package"', false);
        $response->assertSee('id="review-full_name"', false);
        $response->assertSee('class="review-edit" data-edit-step="1"', false);
        $response->assertSee('class="review-edit" data-edit-step="2"', false);
        $response->assertSee('class="review-edit" data-edit-step="3"', false);
    }

    public function test_homepage_replaces_unverified_metrics_with_service_and_package_sections(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Beautiful food for life’s important moments.',
            'Our services',
            'Why Choose 3YOS',
            'A smoother event planning process.',
            'Find a package for your event.',
            'Let’s make your next event feel beautifully easy.',
        ]);
        $response->assertSee('Custom Catering Packages');
        $response->assertSee('Catering options designed around the needs and style of your event.');
        $response->assertSee('Flexible Event Options');
        $response->assertSee('Suitable options for different event types, guest counts, and requirements.');
        $response->assertSee('Professional Event Support');
        $response->assertSee('Support throughout the reservation and event planning process.');
        $response->assertSee('Easy Reservation Process');
        $response->assertSee('A simple way to explore packages, submit event details, and make a reservation.');
        $response->assertSee('View all packages');

        foreach (['1500+', '1,500+', 'events hosted', '12 yrs', '12 years', '24/7', 'planning support', '4.9/5'] as $claim) {
            $response->assertDontSee($claim);
        }
    }

    public function test_package_image_is_rendered_without_showing_a_per_guest_price(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('packages/garden.jpg', 'fake-image-content');

        $package = Package::create([
            'name' => 'Garden Package',
            'slug' => 'garden-package',
            'description' => 'A garden event menu.',
            'price' => 600,
            'min_guests' => 20,
            'max_guests' => 100,
            'image_path' => 'packages/garden.jpg',
        ]);

        $catalog = $this->get(route('packages'));
        $catalog->assertOk();
        $catalog->assertSee('package-images/packages/garden.jpg');
        $catalog->assertDontSee('/ guest');
        $catalog->assertSee('Marikina City, Metro Manila');
        $catalog->assertSee('Choose Garden Package');
        $catalog->assertSee(route('reservation', ['package' => $package->id]), false);

        $detail = $this->get(route('packages.show', $package->slug));
        $detail->assertOk();
        $detail->assertSee('package-images/packages/garden.jpg');
        $detail->assertSee('Choose this package');
    }

    public function test_gallery_shows_all_images_without_a_filter_or_metadata_panel(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('gallery/reunion.jpg', 'reunion-image');
        Storage::disk('public')->put('gallery/wedding.jpg', 'wedding-image');

        GalleryItem::create([
            'title' => 'Family reunion',
            'image_path' => 'gallery/reunion.jpg',
            'event_type' => 'Other Events',
        ]);
        GalleryItem::create([
            'title' => 'Wedding reception',
            'image_path' => 'gallery/wedding.jpg',
            'event_type' => 'Wedding',
        ]);

        $response = $this->get(route('gallery'));

        $response->assertOk();
        $response->assertSee('gallery/reunion.jpg');
        $response->assertSee('gallery/wedding.jpg');
        $response->assertDontSee('Filter by event');
        $response->assertDontSee('gallery-card-body');
    }

    public function test_admin_gallery_keeps_image_management_available_in_collapsed_controls(): void
    {
        $admin = User::factory()->create(['role' => 'full']);
        GalleryItem::create([
            'title' => 'Admin gallery photo',
            'image_path' => 'gallery/admin-photo.jpg',
            'event_type' => 'Wedding',
        ]);

        $response = $this->withSession($this->websiteSession($admin))->get(route('admin.gallery.index'));

        $response->assertOk();
        $response->assertSee('gallery-admin-preview');
        $response->assertSee('Replace image');
        $response->assertSee('id="galleryCropModal"', false);
        $response->assertSee('Apply Crop');
        $response->assertSee('aspect-ratio: 4 / 3');
        $response->assertSee('data-gallery-crop-input', false);
        $response->assertDontSee('Image controls');
        $response->assertDontSee('name="title"');
        $response->assertDontSee('name="event_type"');
        $response->assertDontSee('name="description"');
        $response->assertDontSee('data-password-message="Delete this gallery image?', false);
    }

    public function test_admin_can_add_gallery_photo_without_metadata_fields(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('gallery-admin-password'),
        ]);

        $response = $this->withSession($this->websiteSession($admin))
            ->from(route('admin.gallery.index'))->post(route('admin.gallery.store'), [
                'current_admin_password' => 'gallery-admin-password',
                'is_featured' => '1',
            ]);

        $response->assertRedirect(route('admin.gallery.index'));
        $response->assertSessionHasErrors(['image']);
        $response->assertSessionDoesntHaveErrors(['title', 'event_type', 'description']);
    }

    public function test_uploaded_package_and_gallery_images_are_saved_and_served_from_the_public_disk(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('image-admin-password'),
        ]);
        $session = $this->websiteSession($admin);
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lb8AAAAASUVORK5CYII=');

        $packageResponse = $this->withSession($session)->post(route('admin.packages.store'), [
            'current_admin_password' => 'image-admin-password',
            'name' => 'Image package',
            'price' => 500,
            'image' => UploadedFile::fake()->createWithContent('package.png', $image),
        ]);
        $packageResponse->assertRedirect(route('admin.packages.index'));

        $package = Package::where('name', 'Image package')->firstOrFail();
        $this->assertSame('packages/', substr($package->image_path, 0, 9));
        Storage::disk('public')->assertExists($package->image_path);

        $packageImage = $this->get(route('package.image', ['path' => $package->image_path]));
        $packageImage->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent($image);

        $catalog = $this->get(route('packages'));
        $catalog->assertOk()->assertSee(route('package.image', ['path' => $package->image_path]), false);

        $galleryResponse = $this->withSession($session)->from(route('admin.gallery.index'))->post(route('admin.gallery.store'), [
            'current_admin_password' => 'image-admin-password',
            'image' => UploadedFile::fake()->createWithContent('gallery.png', $image),
        ]);
        $galleryResponse->assertRedirect(route('admin.gallery.index'));

        $gallery = GalleryItem::latest()->firstOrFail();
        Storage::disk('public')->assertExists($gallery->image_path);
        $galleryImage = $this->get(route('gallery.image', ['path' => $gallery->image_path]));
        $galleryImage->assertOk()->assertHeader('Content-Type', 'image/png')->assertStreamedContent($image);
    }

    public function test_gallery_edit_keeps_an_unchanged_image_and_replaces_it_safely(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'full']);
        $originalPath = 'gallery/original.png';
        Storage::disk('public')->put($originalPath, 'original image');
        $gallery = GalleryItem::create([
            'title' => 'Gallery image',
            'image_path' => $originalPath,
        ]);
        $session = $this->websiteSession($admin);

        $this->withSession($session)
            ->from(route('admin.gallery.index'))
            ->put(route('admin.gallery.update', $gallery), ['is_featured' => '1'])
            ->assertRedirect(route('admin.gallery.index'));
        $this->assertSame($originalPath, $gallery->fresh()->image_path);
        Storage::disk('public')->assertExists($originalPath);

        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lb8AAAAASUVORK5CYII=');
        $this->withSession($session)
            ->from(route('admin.gallery.index'))
            ->put(route('admin.gallery.update', $gallery), [
                'image' => UploadedFile::fake()->createWithContent('replacement.png', $image),
                'is_featured' => '1',
            ])
            ->assertRedirect(route('admin.gallery.index'));

        $replacementPath = $gallery->fresh()->image_path;
        $this->assertNotSame($originalPath, $replacementPath);
        Storage::disk('public')->assertMissing($originalPath);
        Storage::disk('public')->assertExists($replacementPath);
    }

    public function test_package_estimate_uses_the_package_rate_times_guest_count(): void
    {
        $package = new Package(['price' => 600]);

        $this->assertSame(48000.0, $package->estimatedTotalFor(80));
    }

    public function test_package_guest_ranges_are_absent_from_public_and_admin_screens(): void
    {
        $package = Package::create([
            'name' => 'Open guest package',
            'slug' => 'open-guest-package',
            'price' => 600,
            'min_guests' => 20,
            'max_guests' => 100,
        ]);

        $catalog = $this->get(route('packages'));
        $catalog->assertOk();
        $catalog->assertDontSee('For 20–100 guests');

        $detail = $this->get(route('packages.show', $package->slug));
        $detail->assertOk();
        $detail->assertDontSee('Guests:');

        $admin = User::factory()->create(['role' => 'full']);
        $adminSession = $this->websiteSession($admin);
        $adminList = $this->withSession($adminSession)->get(route('admin.packages.index'));
        $adminList->assertOk();
        $adminList->assertDontSee('Guest range');
        $adminList->assertDontSee('20-100');

        $adminForm = $this->withSession($adminSession)->get(route('admin.packages.edit', $package));
        $adminForm->assertOk();
        $adminForm->assertDontSee('Minimum guests');
        $adminForm->assertDontSee('Maximum guests');
    }

    private function websiteSession(User $admin): array
    {
        return [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_auth_source' => 'database',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
            'manage_website_auth_user_id' => $admin->id,
            'manage_website_auth_source' => 'database',
            'manage_website_auth_email' => strtolower($admin->email),
            'manage_website_auth_expires_at' => now()->addMinutes((int) config('session.lifetime'))->timestamp,
        ];
    }

    public function test_reservation_shows_only_a_simple_selected_package_summary(): void
    {
        $package = Package::create([
            'name' => 'Preview package',
            'slug' => 'preview-package',
            'price' => 700,
            'description' => 'A generous menu for milestone events.',
            'menu' => 'Three mains, pasta, dessert, and refreshments.',
            'freebies' => 'Buffet styling and service crew.',
            'addons' => 'Optional dessert station.',
        ]);

        $response = $this->get(route('reservation', ['package' => $package->id]));

        $response->assertOk();
        $response->assertSee('id="selected-package-card"', false);
        $response->assertSee('Preview package');
        $response->assertSee('&#8369;700.00 / person', false);
        $response->assertSee('Choose a different package');
        $response->assertSee('<button id="change-package" class="btn btn-outline-primary" type="button" aria-controls="package_id" aria-expanded="false">Choose a different package</button>', false);
        $response->assertDontSee('id="change-package" class="package-selection-link" href=', false);
        $response->assertSee('id="package_id"', false);
        $response->assertSee('isChangingPackage = true;', false);
        $response->assertSee('packageInput?.scrollIntoView({ block: \'nearest\' });', false);
        $response->assertSee('packageInput?.focus();', false);
        $response->assertSee('packagePriceFormatter.format(Number(option.dataset.price))', false);
        $response->assertSee('View package details');
        $response->assertDontSee('data-package-option', false);
        $response->assertDontSee('Three mains, pasta, dessert, and refreshments.');
        $response->assertDontSee('Buffet styling and service crew.');
        $response->assertDontSee('Optional dessert station.');
    }

    public function test_reservation_reports_a_preselected_package_that_is_no_longer_available(): void
    {
        $package = Package::create([
            'name' => 'Unavailable package',
            'slug' => 'unavailable-package',
            'price' => 700,
        ]);
        $packageId = $package->id;
        $package->delete();

        $response = $this->get(route('reservation', ['package' => $packageId]));

        $response->assertOk();
        $response->assertSee('The selected package is no longer available. Please choose another package.');
    }

    public function test_reservation_requires_a_catering_package(): void
    {
        $response = $this->post(route('reservation.store'), []);

        $response->assertSessionHasErrors([
            'package_id' => 'Please select a catering package.',
        ]);
    }

    public function test_reservation_reports_a_package_that_is_no_longer_available(): void
    {
        $response = $this->post(route('reservation.store'), [
            'package_id' => PHP_INT_MAX,
        ]);

        $response->assertSessionHasErrors([
            'package_id' => 'The selected package is no longer available. Please choose another package.',
        ]);
    }
}
