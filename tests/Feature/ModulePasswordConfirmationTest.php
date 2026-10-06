<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Service;
use App\Models\User;
use App\Models\GalleryItem;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModulePasswordConfirmationTest extends TestCase
{
    public function test_package_service_gallery_create_and_edit_do_not_require_extra_password_confirmation(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('the-correct-password'),
        ]);
        $session = $this->websiteSession($admin);

        $package = Package::create([
            'name' => 'Existing package',
            'slug' => 'existing-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
        ]);
        $service = Service::create([
            'name' => 'Protected service',
            'slug' => 'protected-service',
            'is_enabled' => true,
        ]);

        $packageResponse = $this->withSession($session)
            ->post(route('admin.packages.store'), [
                'name' => 'New package without password',
                'price' => 550,
            ]);
        $packageResponse->assertRedirect(route('admin.packages.index'));
        $this->assertDatabaseHas('packages', ['name' => 'New package without password']);

        $packageUpdateResponse = $this->withSession($session)
            ->put(route('admin.packages.update', $package), [
                'name' => 'Updated package without password',
                'price' => 600,
            ]);
        $packageUpdateResponse->assertRedirect(route('admin.packages.index'));
        $this->assertDatabaseHas('packages', ['id' => $package->id, 'name' => 'Updated package without password']);

        $serviceResponse = $this->withSession($session)
            ->post(route('admin.services.store'), [
                'name' => 'New service without password',
                'slug' => 'new-service-without-password',
            ]);
        $serviceResponse->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseHas('services', ['name' => 'New service without password']);

        $serviceUpdateResponse = $this->withSession($session)
            ->put(route('admin.services.update', $service), [
                'name' => 'Updated service without password',
                'slug' => 'updated-service-without-password',
            ]);
        $serviceUpdateResponse->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseHas('services', ['id' => $service->id, 'name' => 'Updated service without password']);
    }

    public function test_package_service_and_gallery_actions_do_not_require_a_second_password(): void
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'password' => Hash::make('the-correct-password'),
        ]);
        $session = $this->websiteSession($admin);

        $package = Package::create([
            'name' => 'Package to delete',
            'slug' => 'package-to-delete',
            'price' => 400,
        ]);
        $service = Service::create([
            'name' => 'Protected service',
            'slug' => 'protected-service',
            'is_enabled' => true,
        ]);
        $gallery = GalleryItem::create([
            'title' => 'Gallery image',
            'image_path' => 'gallery/delete-after-auth.jpg',
            'event_type' => 'Wedding',
        ]);

        $toggleResponse = $this->withSession($session)
            ->from(route('admin.services.index'))
            ->patch(route('admin.services.toggle', $service));
        $toggleResponse->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_enabled' => false]);

        $this->from(route('admin.packages.index'))
            ->delete(route('admin.packages.destroy', $package))
            ->assertRedirect(route('admin.packages.index'));
        $this->assertDatabaseMissing('packages', ['id' => $package->id]);

        $this->from(route('admin.services.index'))
            ->delete(route('admin.services.destroy', $service))
            ->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseMissing('services', ['id' => $service->id]);

        $this->put(route('admin.gallery.update', $gallery), ['is_featured' => '1'])
            ->assertRedirect();
        $this->assertDatabaseHas('gallery_items', ['id' => $gallery->id, 'is_featured' => true]);

        $this->from(route('admin.gallery.index'))
            ->delete(route('admin.gallery.destroy', $gallery))
            ->assertRedirect(route('admin.gallery.index'));
        $this->assertDatabaseMissing('gallery_items', ['id' => $gallery->id]);
    }

    public function test_package_and_service_edit_forms_do_not_trigger_the_shared_password_dialog(): void
    {
        $package = Package::create([
            'name' => 'Existing package',
            'slug' => 'existing-package',
            'price' => 500,
            'min_guests' => 10,
            'max_guests' => 50,
        ]);
        $service = Service::create([
            'name' => 'Existing service',
            'slug' => 'existing-service',
            'is_enabled' => true,
        ]);

        $admin = User::factory()->create(['role' => 'full']);
        $session = $this->websiteSession($admin);

        $packageResponse = $this->withSession($session)->get(route('admin.packages.edit', $package));
        $packageResponse->assertOk();
        $packageResponse->assertSee('data-password-confirm', false);

        $serviceResponse = $this->withSession($session)->get(route('admin.services.edit', $service));
        $serviceResponse->assertOk();
        $serviceResponse->assertSee('data-password-confirm', false);
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
}
