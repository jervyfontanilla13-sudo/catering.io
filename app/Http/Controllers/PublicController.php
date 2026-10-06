<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\GalleryItem;
use App\Models\Reservation;
use App\Models\Service;
use App\Support\HelpCenterContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicController extends Controller
{
    public function home()
    {
        $galleryImages = GalleryItem::latest()->get();
        $packages = Package::orderByRaw("CASE name WHEN 'Silver' THEN 1 WHEN 'Gold' THEN 2 WHEN 'Platinum' THEN 3 WHEN 'Diamond' THEN 4 ELSE 5 END")
            ->orderBy('price')
            ->limit(4)
            ->get();

        return view('public.home', compact('galleryImages', 'packages'));
    }

    public function about()
    {
        return view('public.about');
    }

    public function services()
    {
        $services = Service::where('is_enabled', true)->latest()->get();

        return view('public.services', compact('services'));
    }

    public function packages()
    {
        $this->ensureSignaturePackages();
        $packages = Package::orderByRaw("CASE name WHEN 'Silver' THEN 1 WHEN 'Gold' THEN 2 WHEN 'Platinum' THEN 3 WHEN 'Diamond' THEN 4 ELSE 5 END")
            ->orderBy('price')
            ->get();

        return view('public.packages', compact('packages'));
    }

    public function packageShow(Package $package)
    {
        return view('public.package', compact('package'));
    }

    public function gallery()
    {
        $galleryItems = GalleryItem::latest()->get();

        return view('public.gallery', compact('galleryItems'));
    }

    public function galleryImage(string $path)
    {
        abort_unless(str_starts_with($path, 'gallery/') && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function packageImage(string $path)
    {
        abort_unless(str_starts_with($path, 'packages/') && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }

    public function reservation(Request $request)
    {
        $this->ensureSignaturePackages();
        $packages = Package::orderBy('price')->get();
        $reservation = null;
        $lookupCode = trim((string) $request->query('code', ''));

        if ($lookupCode !== '') {
            $reservation = Reservation::with('package')->where('reservation_code', strtoupper($lookupCode))->first();
        }

        $submissionKey = $reservation ? null : Str::random(64);

        return view('public.reservation', compact('packages', 'reservation', 'lookupCode', 'submissionKey'));
    }

    public function reservationStatus(Request $request)
    {
        $code = trim((string) $request->query('code', ''));
        $reservation = $code !== '' ? Reservation::where('reservation_code', strtoupper($code))->first() : null;

        return view('public.reservation-status', compact('reservation', 'code'));
    }

    public function inquiry()
    {
        return view('public.inquiry');
    }

    public function contact()
    {
        return view('public.contact');
    }

    public function support()
    {
        $categories = HelpCenterContent::guestHelpCategories();
        $chapters = HelpCenterContent::guestManualChapters();

        return view('public.help', compact('categories', 'chapters'));
    }

    public function help()
    {
        return redirect()->route('support');
    }

    public function manual()
    {
        return redirect()->route('support');
    }

    private function ensureSignaturePackages(): void
    {
        foreach ([
            ['name' => 'Silver', 'slug' => 'silver', 'description' => 'A generous buffet for relaxed celebrations and intimate gatherings.', 'price' => 550, 'menu' => '2 mains, pasta or noodles, vegetables, rice, dessert, and iced tea', 'freebies' => 'Basic buffet styling, menu labels, and trained service crew', 'addons' => 'Dessert station, tablescape styling, and additional mains', 'event_type' => 'Birthdays, reunions, and simple celebrations', 'is_featured' => false],
            ['name' => 'Gold', 'slug' => 'gold', 'description' => 'A crowd-pleasing selection with elevated presentation for milestone moments.', 'price' => 750, 'menu' => '3 mains, pasta, vegetables, rice, dessert station, and refreshments', 'freebies' => 'Styled buffet setup, menu labels, basic floral accents, and service crew', 'addons' => 'Grazing table, mocktail bar, and upgraded styling', 'event_type' => 'Debuts, anniversaries, and company events', 'is_featured' => true],
            ['name' => 'Platinum', 'slug' => 'platinum', 'description' => 'A refined menu and fuller service for memorable celebrations.', 'price' => 950, 'menu' => '4 mains, pasta, vegetables, rice, premium dessert station, and drinks', 'freebies' => 'Enhanced tablescape, welcome drinks, menu labels, and dedicated event lead', 'addons' => 'Live station, mobile bar, and premium floral styling', 'event_type' => 'Weddings, launches, and formal celebrations', 'is_featured' => false],
            ['name' => 'Diamond', 'slug' => 'diamond', 'description' => 'Our most complete celebration experience for grand events.', 'price' => 1250, 'menu' => '5 mains, live station, pasta, vegetables, rice, premium desserts, and drinks', 'freebies' => 'Full event styling consultation, upgraded tablescape, service team, and event lead', 'addons' => 'Custom menu development, lounge setup, and premium bar service', 'event_type' => 'Luxury weddings, gala dinners, and large-scale events', 'is_featured' => false],
        ] as $package) {
            // Only seed packages that do not exist. Admin edits must remain intact.
            Package::firstOrCreate(['slug' => $package['slug']], $package);
        }
    }

}
