@extends('layouts.app')

@section('title', 'Catering packages | 3YOS Catering')

@section('content')
<section class="container py-5">
    <div class="page-heading">
        <div class="eyebrow mb-2">Find your fit</div>
        <h1>Packages for every kind of celebration.</h1>
        <p>Beautifully planned catering, with clear inclusions and room to make each event your own.</p>
    </div>

    <section class="recommendation mb-5" aria-labelledby="recommendation-title">
        <div class="row align-items-center g-4">
            <div class="col-lg-5"><div class="eyebrow text-white-50 mb-2">Not sure where to begin?</div><h2 id="recommendation-title">Find the right package in seconds.</h2><p class="mb-0 text-white-50">Share your estimated budget and guest count. We’ll recommend the best tier for your event.</p></div>
            <div class="col-lg-7"><div class="recommendation-form"><div><label for="budget">Total budget (&#8369;)</label><input id="budget" type="number" min="1" placeholder="e.g. 60000"></div><div><label for="guests">Number of guests</label><input id="guests" type="number" min="1" placeholder="e.g. 80"></div><button id="recommend-button" type="button">Recommend a package</button></div><div id="recommendation-result" class="recommendation-result" aria-live="polite">Enter your details to see a recommendation.</div></div>
        </div>
    </section>

    <div class="row g-4 mb-5">
        <div class="col-md-4"><div class="feature-box h-100"><div class="feature-icon">🍽️</div><h3>Custom menus</h3><p>Flexible menus built around your guests, your venue, and the feeling you want for your celebration.</p></div></div>
        <div class="col-md-4"><div class="feature-box h-100"><div class="feature-icon">🎉</div><h3>Event-ready setup</h3><p>From elegant buffet lines to plated service, we handle the presentation and timing with care.</p></div></div>
        <div class="col-md-4"><div class="feature-box h-100"><div class="feature-icon">🤝</div><h3>Planning support</h3><p>We help you tailor the package to your guest count, guest preferences, and event flow.</p></div></div>
    </div>

    <div class="row g-4">
        @forelse($packages as $package)
            @php($tier = strtolower($package->name))
            <div class="col-md-6 col-xl-3 package-column" data-package-card data-name="{{ $package->name }}" data-price="{{ $package->price }}">
                <article class="package-card h-100 {{ $package->is_featured ? 'is-featured' : '' }}">
                    @if($package->is_featured)<div class="package-ribbon">Most popular</div>@endif
                    <div class="package-image-wrap">
                        @if($package->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($package->image_path))
                            <img src="{{ route('package.image', ['path' => $package->image_path]) }}" alt="{{ $package->name }} catering package" loading="lazy">
                        @else
                            <span>{{ $package->name }} package</span>
                        @endif
                    </div>
                    <div class="package-tier package-tier--{{ $tier }}">{{ $package->name }}</div>
                    <p class="package-description">{{ $package->description }}</p>
                    <div class="package-price">&#8369;{{ number_format($package->price, 2) }} <small>/ person</small></div>
                    <div class="package-rule"></div>
                    <p class="package-inclusion"><strong>Includes</strong>{{ $package->menu }}</p>
                    <div class="package-actions mt-auto">
                        <a href="{{ route('reservation', ['package' => $package->id]) }}" class="btn btn-primary w-100">Choose {{ $package->name }}</a>
                        <a href="{{ route('packages.show', $package->slug) }}" class="btn btn-outline-primary w-100">View package details</a>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><div class="soft-card p-5 text-center"><h3 class="mb-2">Our packages are being prepared.</h3><p class="text-muted mb-0">Run the latest database migration to add Silver, Gold, Platinum, and Diamond packages.</p></div></div>
        @endforelse
    </div>
</section>

<section class="py-5 py-lg-6 bg-paper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8 text-center">
                <div class="eyebrow mb-2">Frequently asked</div>
                <h2 class="section-title mb-4">Questions people ask before booking.</h2>
            </div>
        </div>
        <div class="row g-4">
            <div class="col-md-6"><div class="faq-item h-100"><h3>How far in advance should I book?</h3><p>We recommend booking at least 2–3 months ahead for weddings and larger celebrations, though we also accommodate shorter timelines when possible.</p></div></div>
            <div class="col-md-6"><div class="faq-item h-100"><h3>Can you customize the menu?</h3><p>Yes. We can tailor each package based on your event style, guest preferences, and budget to create a menu that feels personal.</p></div></div>
            <div class="col-md-6"><div class="faq-item h-100"><h3>Do you provide staffing and setup?</h3><p>Absolutely. We offer setup, service, and event support to help your celebration run smoothly.</p></div></div>
            <div class="col-md-6"><div class="faq-item h-100"><h3>Do you handle smaller events too?</h3><p>Yes. We cater intimate family gatherings, birthdays, debuts, and private celebrations as well as larger events.</p></div></div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-6">
    <div class="container">
        <div class="cta-panel text-center">
            <div class="eyebrow mb-2">Ready to plan?</div>
            <h2 class="section-title mb-3">Let’s build the right fit for your event.</h2>
            <p class="mb-4">Tell us what you’re planning and we’ll help recommend a package, menu, and service setup that matches your celebration.</p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3"><a href="{{ route('inquiry') }}" class="btn btn-primary">Request a quote</a><a href="{{ route('reservation') }}" class="btn btn-outline-primary">Book a reservation</a></div>
        </div>
    </div>
</section>

<style>
    .recommendation{background:#6d3024;color:#fff;padding:clamp(1.6rem,4vw,3rem)}.recommendation h2{font-size:clamp(1.85rem,3vw,2.65rem);line-height:1.08}.recommendation-form{display:grid;grid-template-columns:1fr 1fr auto;gap:.65rem}.recommendation-form label{display:block;margin-bottom:.32rem;font-size:.75rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#f2d8c7}.recommendation-form input{width:100%;border:1px solid rgba(255,255,255,.28);background:#fffdf9;color:#20201d;padding:.78rem}.recommendation-form button{align-self:end;border:0;background:#d7a766;color:#332116;padding:.83rem 1rem;font-weight:800;white-space:nowrap}.recommendation-form button:hover{background:#ecc384}.recommendation-result{min-height:1.4rem;margin-top:1rem;color:#fff7ef;font-weight:600}.package-card{display:flex;flex-direction:column;position:relative;height:100%;padding:2rem 1.5rem 1.5rem;background:var(--paper);border:1px solid var(--line);color:var(--ink);transition:.2s ease}.package-card:hover,.package-card.is-recommended{transform:translateY(-5px);box-shadow:0 18px 34px rgba(70,42,24,.12)}.package-card.is-recommended{border:2px solid var(--terracotta)}.package-ribbon{position:absolute;right:0;top:0;background:#b66545;color:#fff;padding:.36rem .7rem;font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase}.package-tier{font-family:'Playfair Display',Georgia,serif;font-size:2.3rem;font-weight:700}.package-tier--silver{color:#737a7f}.package-tier--gold{color:#aa7926}.package-tier--platinum{color:#68727a}.package-tier--diamond{color:#6d3024}.package-description{color:var(--muted);line-height:1.55;min-height:5rem;margin:1rem 0}.package-price{font-family:'Playfair Display',Georgia,serif;color:var(--wine);font-size:1.9rem;font-weight:700}.package-price span,.package-price small{font-family:'DM Sans',sans-serif;font-size:.72rem;font-weight:700}.package-guests{margin-top:.4rem;color:var(--muted);font-size:.85rem;font-weight:600}.package-rule{height:1px;background:var(--line);margin:1.35rem 0}.package-inclusion{font-size:.85rem;color:var(--muted);line-height:1.6}.package-inclusion strong{display:block;margin-bottom:.35rem;color:var(--ink);font-size:.73rem;letter-spacing:.08em;text-transform:uppercase}body.dark-mode .package-tier--silver,body.dark-mode .package-tier--platinum{color:#d8e0e5}body.dark-mode .package-tier--gold{color:#f1c96d}body.dark-mode .package-tier--diamond{color:#efb19b}@media(max-width:767px){.recommendation-form{grid-template-columns:1fr}.recommendation-form button{width:100%}}
</style>
<style>
    .package-image-wrap{display:grid;place-items:center;aspect-ratio:16/9;margin:-2rem -1.5rem 1.2rem;overflow:hidden;background:#e8dfd0;color:#5d5146;font-size:.82rem;font-weight:700}
    .package-image-wrap img{width:100%;height:100%;object-fit:cover}
    .package-actions{display:grid;gap:.65rem}
</style>
<script>
    document.getElementById('recommend-button').addEventListener('click', function () {
        const budget = Number(document.getElementById('budget').value);
        const guests = Number(document.getElementById('guests').value);
        const result = document.getElementById('recommendation-result');
        const cards = Array.from(document.querySelectorAll('[data-package-card]'));
        cards.forEach(card => card.querySelector('.package-card').classList.remove('is-recommended'));
        if (!budget || !guests) { result.textContent = 'Please enter both your total budget and number of guests.'; return; }
        const eligible = cards.filter(card => budget >= Number(card.dataset.price) * guests);
        const choice = eligible[eligible.length - 1];
        if (!choice) {
            result.textContent = cards.length ? 'Your budget is below the estimated total for available packages. Increase your budget or send an inquiry for options.' : 'No packages are currently available. Please send an inquiry for options.';
            return;
        }
        choice.querySelector('.package-card').classList.add('is-recommended');
        const estimatedTotal = (Number(choice.dataset.price) * guests).toLocaleString('en-PH');
        result.innerHTML = `<strong>${choice.dataset.name}</strong> is our recommendation for ${guests} guests, with an estimated package total of ₱${estimatedTotal}.`;
        choice.scrollIntoView({behavior: 'smooth', block: 'center'});
    });
</script>
@endsection
