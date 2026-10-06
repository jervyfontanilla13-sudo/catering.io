@extends('layouts.app')

@section('title', 'Catering services | 3YOS Catering')

@section('content')
<section class="container py-5">
    <div class="page-heading">
        <div class="eyebrow mb-2">More than a menu</div>
        <h1>Thoughtful service for every detail.</h1>
        <p>From the first setup to the last guest, our team brings the practical care and polished finish that help celebrations feel effortless.</p>
    </div>

    <div class="row g-4 mb-5 service-features">
        <div class="col-md-4"><div class="feature-box h-100"><div class="feature-icon">01</div><h3>Plan with ease</h3><p>We help shape the right service mix around your guest count, venue, and celebration style.</p></div></div>
        <div class="col-md-4"><div class="feature-box h-100"><div class="feature-icon">02</div><h3>Arrive event-ready</h3><p>Our team takes care of setup, presentation, and the details that make your event feel considered.</p></div></div>
        <div class="col-md-4"><div class="feature-box h-100"><div class="feature-icon">03</div><h3>Enjoy the moment</h3><p>With dependable support in place, you can stay present with the people and memories that matter.</p></div></div>
    </div>

    <div class="service-intro mb-4">
        <div><div class="eyebrow text-white-50 mb-2">Choose your support</div><h2>Services that make the celebration flow.</h2></div>
        <p class="mb-0">Build a thoughtful event experience with practical help, graceful presentation, and a team that knows what happens next.</p>
    </div>

    <div class="row g-4">
        @forelse($services as $service)
            @php
                $serviceIcon = trim((string) $service->icon);
                $serviceName = trim((string) $service->name);
                if ($serviceIcon === '' || strcasecmp($serviceIcon, $serviceName) === 0 || mb_strlen($serviceIcon) > 4) {
                    $serviceIcon = collect(preg_split('/\s+/', $serviceName))->filter()->map(fn ($word) => mb_substr($word, 0, 1))->take(2)->implode('');
                }
                $serviceItems = collect(preg_split('/\r\n|\r|\n|\s+aa\s+|\s*-\s+/i', trim((string) $service->description), -1, PREG_SPLIT_NO_EMPTY))
                    ->map(fn ($item) => trim($item))
                    ->reject(fn ($item) => strtolower($item) === 'aa')
                    ->values();
            @endphp
            <div class="col-md-6 col-xl-4">
                <article class="service-card h-100 {{ $service->is_featured ? 'is-featured' : '' }}">
                    @if($service->is_featured)<div class="service-ribbon">Featured service</div>@endif
                    <div class="service-icon" aria-hidden="true">{{ $serviceIcon ?: '✦' }}</div>
                    <h2>{{ $service->name }}</h2>
                    @if($serviceItems)
                        <ul class="service-items">
                            @foreach($serviceItems as $serviceItem)
                                <li>{{ trim($serviceItem) }}</li>
                            @endforeach
                        </ul>
                    @else
                        <p class="service-empty-description">Thoughtful support tailored to the needs of your celebration.</p>
                    @endif
                    <div class="service-rule"></div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="soft-card p-5 text-center"><h3 class="mb-2">Our services are being prepared.</h3><p class="text-muted mb-0">Please check back soon or send us an inquiry for a custom event plan.</p></div></div>
        @endforelse
    </div>

    <div class="service-cta text-center mt-5">
        <div class="eyebrow mb-2">Ready to make it yours?</div>
        <h2 class="section-title mb-3">Let’s plan the details together.</h2>
        <p class="mb-4">Tell us what you’re celebrating and we’ll help you choose the right services for the day.</p>
        <div class="d-flex flex-column flex-sm-row justify-content-center gap-3"><a href="{{ route('inquiry') }}" class="btn btn-primary">Request a quote</a><a href="{{ route('reservation') }}" class="btn btn-outline-primary">Book an event</a></div>
    </div>
</section>
<style>
    .page-heading{max-width:700px;margin-bottom:3rem}.page-heading h1{font-size:clamp(2.35rem,5vw,4.35rem);line-height:1.02;margin-bottom:1rem}.page-heading p{max-width:610px;color:var(--muted);font-size:1.05rem;line-height:1.7}.feature-box{padding:1.5rem;background:var(--paper);border:1px solid var(--line);box-shadow:0 12px 28px rgba(70,42,24,.06)}.feature-icon{display:grid;place-items:center;width:42px;height:42px;margin-bottom:1.2rem;background:#f1dfc8;color:var(--wine);font-size:.72rem;font-weight:800;letter-spacing:.08em}.feature-box h3{font-size:1.35rem;margin-bottom:.55rem}.feature-box p{color:var(--muted);line-height:1.6;margin-bottom:0}.service-intro{display:flex;justify-content:space-between;align-items:end;gap:2rem;padding:1.8rem 2rem;background:#6d3024;color:#fff}.service-intro h2{font-size:clamp(1.7rem,3vw,2.6rem);margin:0}.service-intro p{max-width:410px;color:#f4dacc;line-height:1.6}.service-card{display:flex;flex-direction:column;position:relative;padding:1.6rem;background:var(--paper);border:1px solid var(--line);box-shadow:0 12px 28px rgba(70,42,24,.06);transition:.2s ease}.service-card:hover{transform:translateY(-5px);box-shadow:0 18px 34px rgba(70,42,24,.12)}.service-card.is-featured{border:2px solid var(--terracotta)}.service-ribbon{position:absolute;top:0;right:0;padding:.36rem .7rem;background:var(--terracotta);color:#fff;font-size:.65rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.service-icon{display:grid;place-items:center;width:52px;height:52px;margin-bottom:1.2rem;background:#f5eadb;color:var(--wine);font-size:1.2rem;font-weight:800}.service-card h2{font-size:1.55rem;margin-bottom:.7rem}.service-items{min-height:5.2rem;margin:0 0 1.2rem;padding-left:1.2rem;color:var(--muted);line-height:1.6}.service-items li{padding-left:.2rem;margin-bottom:.18rem}.service-empty-description{min-height:5.2rem;color:var(--muted);line-height:1.6;margin:0 0 1.2rem}.service-rule{height:1px;background:var(--line);margin-top:auto;margin-bottom:1rem}.service-link{color:var(--wine);font-size:.82rem;font-weight:800}.service-link span{margin-left:.35rem;color:var(--terracotta)}.service-cta{padding:3rem 1.5rem;background:var(--paper);border:1px solid var(--line)}.service-cta p{max-width:540px;margin-left:auto;margin-right:auto;color:var(--muted);line-height:1.6}
    body.dark-mode .feature-box,body.dark-mode .service-card,body.dark-mode .service-cta{background:var(--paper);border-color:var(--line)}body.dark-mode .feature-icon,body.dark-mode .service-icon{background:#3b3027;color:var(--gold)}
    @media(max-width:767px){.service-intro{display:block;padding:1.5rem}.service-intro p{margin-top:1rem}.service-items,.service-empty-description{min-height:0}}
</style>
@endsection
