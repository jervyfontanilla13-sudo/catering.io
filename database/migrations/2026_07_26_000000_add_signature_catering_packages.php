<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            ['name' => 'Silver', 'slug' => 'silver', 'description' => 'A generous, polished buffet for relaxed celebrations and intimate gatherings.', 'price' => 550, 'min_guests' => 30, 'max_guests' => 80, 'menu' => '2 mains, pasta or noodles, vegetables, rice, dessert, and iced tea', 'freebies' => 'Basic buffet styling, menu labels, and trained service crew', 'addons' => 'Dessert station, tablescape styling, and additional mains', 'event_type' => 'Birthdays, reunions, and simple celebrations', 'is_featured' => false],
            ['name' => 'Gold', 'slug' => 'gold', 'description' => 'A crowd-pleasing selection with elevated presentation for milestone moments.', 'price' => 750, 'min_guests' => 50, 'max_guests' => 150, 'menu' => '3 mains, pasta, vegetables, rice, dessert station, and refreshments', 'freebies' => 'Styled buffet setup, menu labels, basic floral accents, and service crew', 'addons' => 'Grazing table, mocktail bar, and upgraded styling', 'event_type' => 'Debuts, anniversaries, and company events', 'is_featured' => true],
            ['name' => 'Platinum', 'slug' => 'platinum', 'description' => 'A refined menu and fuller service for memorable celebrations with more to share.', 'price' => 950, 'min_guests' => 80, 'max_guests' => 250, 'menu' => '4 mains, pasta, vegetables, rice, premium dessert station, and drinks', 'freebies' => 'Enhanced tablescape, welcome drinks, menu labels, and dedicated event lead', 'addons' => 'Live station, mobile bar, and premium floral styling', 'event_type' => 'Weddings, launches, and formal celebrations', 'is_featured' => false],
            ['name' => 'Diamond', 'slug' => 'diamond', 'description' => 'Our most complete celebration experience, tailored for grand and unforgettable events.', 'price' => 1250, 'min_guests' => 120, 'max_guests' => 400, 'menu' => '5 mains, live station, pasta, vegetables, rice, premium desserts, and drinks', 'freebies' => 'Full event styling consultation, upgraded tablescape, service team, and event lead', 'addons' => 'Custom menu development, lounge setup, and premium bar service', 'event_type' => 'Luxury weddings, gala dinners, and large-scale events', 'is_featured' => false],
        ] as $package) {
            DB::table('packages')->updateOrInsert(
                ['slug' => $package['slug']],
                [...$package, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('packages')->whereIn('slug', ['silver', 'gold', 'platinum', 'diamond'])->delete();
    }
};
