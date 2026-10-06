<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Reservation;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogDuplicateNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_unique_package_can_be_created_and_duplicate_names_are_rejected_after_normalization(): void
    {
        $session = $this->websiteSession();
        $initialPackageCount = Package::query()->count();
        $this->withSession($session)
            ->post(route('admin.packages.store'), ['name' => 'Wedding Package', 'price' => 500])
            ->assertRedirect(route('admin.packages.index'));

        $package = Package::query()->where('name', 'Wedding Package')->firstOrFail();
        $reservation = $this->reservationFor($package);

        foreach (['Wedding Package', 'wedding package', 'WEDDING PACKAGE', "  Wedding\t Package  "] as $duplicateName) {
            $this->withSession($session)
                ->from(route('admin.packages.create'))
                ->post(route('admin.packages.store'), ['name' => $duplicateName, 'price' => 600])
                ->assertRedirect(route('admin.packages.create'))
                ->assertSessionHasErrors([
                    'name' => 'A package with this name already exists. Please choose a different name.',
                ]);
        }

        $this->assertSame($initialPackageCount + 1, Package::query()->count());
        $this->assertSame(1, Package::query()->where('name', 'Wedding Package')->count());
        $this->assertSame($package->id, $reservation->fresh()->package_id);
    }

    public function test_package_edit_allows_its_own_name_but_rejects_another_packages_name(): void
    {
        $session = $this->websiteSession();
        $wedding = Package::create(['name' => 'Wedding Package', 'slug' => 'wedding-package', 'price' => 500]);
        $birthday = Package::create(['name' => 'Birthday Package', 'slug' => 'birthday-package', 'price' => 600]);

        $this->withSession($session)
            ->put(route('admin.packages.update', $birthday), ['name' => ' Birthday   Package ', 'price' => 600])
            ->assertRedirect(route('admin.packages.index'));

        $this->assertSame('Birthday Package', $birthday->fresh()->name);

        $this->withSession($session)
            ->from(route('admin.packages.edit', $birthday))
            ->put(route('admin.packages.update', $birthday), ['name' => ' wedding  package ', 'price' => 650])
            ->assertRedirect(route('admin.packages.edit', $birthday))
            ->assertSessionHasErrors([
                'name' => 'A package with this name already exists. Please choose a different name.',
            ]);

        $this->assertSame('Birthday Package', $birthday->fresh()->name);
        $this->assertSame('Wedding Package', $wedding->fresh()->name);
    }

    public function test_unique_service_can_be_created_and_duplicate_names_are_rejected_after_normalization(): void
    {
        $session = $this->websiteSession();
        $this->withSession($session)
            ->post(route('admin.services.store'), ['name' => 'Event Decoration'])
            ->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('services', ['name' => 'Event Decoration']);

        foreach (['Event Decoration', 'event decoration', 'EVENT DECORATION', " Event\t Decoration "] as $duplicateName) {
            $this->withSession($session)
                ->from(route('admin.services.create'))
                ->post(route('admin.services.store'), ['name' => $duplicateName])
                ->assertRedirect(route('admin.services.create'))
                ->assertSessionHasErrors([
                    'name' => 'A service with this name already exists. Please choose a different name.',
                ]);
        }

        $this->assertSame(1, Service::query()->count());
    }

    public function test_service_edit_allows_its_own_name_but_rejects_another_services_name(): void
    {
        $session = $this->websiteSession();
        $decoration = Service::create(['name' => 'Event Decoration', 'slug' => 'event-decoration']);
        $sound = Service::create(['name' => 'Sound System', 'slug' => 'sound-system']);

        $this->withSession($session)
            ->put(route('admin.services.update', $sound), ['name' => ' Sound   System ', 'is_enabled' => '1'])
            ->assertRedirect(route('admin.services.index'));

        $this->assertSame('Sound System', $sound->fresh()->name);

        $this->withSession($session)
            ->from(route('admin.services.edit', $sound))
            ->put(route('admin.services.update', $sound), ['name' => ' event decoration '])
            ->assertRedirect(route('admin.services.edit', $sound))
            ->assertSessionHasErrors([
                'name' => 'A service with this name already exists. Please choose a different name.',
            ]);

        $this->assertSame('Sound System', $sound->fresh()->name);
        $this->assertSame('Event Decoration', $decoration->fresh()->name);
    }

    private function websiteSession(): array
    {
        $admin = User::factory()->create([
            'role' => 'full',
            'is_active' => true,
        ]);

        return [
            'is_admin' => true,
            'admin_role' => 'full',
            'admin_auth_source' => 'database',
            'admin_user_id' => $admin->id,
            'admin_email' => $admin->email,
            'admin_session_version' => $admin->session_version,
            'manage_website_auth_user_id' => $admin->id,
            'manage_website_auth_source' => 'database',
            'manage_website_auth_email' => strtolower($admin->email),
            'manage_website_auth_expires_at' => now()->addMinutes(15)->timestamp,
        ];
    }

    private function reservationFor(Package $package): Reservation
    {
        return Reservation::create([
            'package_id' => $package->id,
            'full_name' => 'Historical Client',
            'contact_number' => '09171234567',
            'email' => 'historical-client@example.com',
            'address' => '1 Existing Street',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Existing Hall',
            'guest_count' => 50,
            'status' => 'pending',
        ]);
    }
}
