@extends('layouts.app')

@section('title', 'About 3YOS Catering | Thoughtful celebrations')

@section('content')
<section class="about-hero">
    <div class="container py-5 py-lg-6">
        <div class="row align-items-center g-5">
            <div class="col-lg-7">
                <div class="eyebrow mb-3">The 3YOS approach</div>
                <h1>Good food is only the beginning of a great celebration.</h1>
                <p class="about-lead">3YOS Catering Services &amp; Party Needs brings together generous food, graceful styling, and dependable coordination so you can be fully present for the moments you planned.</p>
                <div class="d-flex flex-column flex-sm-row gap-3 mt-4"><a href="{{ route('packages') }}" class="btn btn-primary">Explore packages</a><a href="{{ route('inquiry') }}" class="btn btn-outline-primary">Tell us about your event</a></div>
            </div>
            <div class="col-lg-5">
                <div class="about-mark-panel">
                    <div class="about-mark-orbit"></div>
                    <img src="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}" alt="3YOS Catering Services" class="about-logo">
                    <span class="about-panel-label">Catering &amp; party needs</span>
                    <strong>Made for the way you celebrate.</strong>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="container py-5 py-lg-6">
    <div class="row g-5 align-items-start">
        <div class="col-lg-5"><div class="eyebrow mb-2">What we believe</div><h2 class="section-title">The best events feel effortless because every detail has a place.</h2></div>
        <div class="col-lg-7"><p class="about-copy">We care about the full experience: how guests are welcomed, how the table looks, how smoothly the day moves, and how much room you have to enjoy it. Our team works with you to shape menus and service around your people, your venue, and your vision.</p><p class="about-copy mb-0">Whether it is an intimate gathering or a milestone celebration, we bring the same thoughtful preparation and warm hospitality to the table.</p></div>
    </div>

    <div class="row g-4 g-lg-5 mt-5 about-values">
        <div class="col-12 col-md-6 col-lg-4"><article class="about-value h-100"><h3>Thoughtful menus</h3><p>Food that feels generous, considered, and right for the people around your table.</p></article></div>
        <div class="col-12 col-md-6 col-lg-4"><article class="about-value h-100"><h3>Polished details</h3><p>Styling and presentation that make the room feel ready before your guests arrive.</p></article></div>
        <div class="col-12 col-md-6 col-lg-4"><article class="about-value h-100"><h3>Steady support</h3><p>A dependable event team that helps the day move beautifully from first welcome to final toast.</p></article></div>
    </div>
</section>

<section class="about-cta py-5 py-lg-6">
    <div class="container text-center"><div class="eyebrow mb-2">Start with your occasion</div><h2 class="section-title mb-3">Let’s make space for the celebration.</h2><p class="mb-4">Share your date, guest count, and ideas. We’ll help you find the right place to begin.</p><a href="{{ route('reservation') }}" class="btn btn-primary">Book an event</a></div>
</section>

<style>
    .py-lg-6{padding-top:6rem!important;padding-bottom:6rem!important}.about-hero{background:linear-gradient(120deg,#f3e7d9 0%,#fbf7f0 62%,#ead7c4 100%);overflow:hidden}.about-hero h1{max-width:740px;font-size:clamp(2.8rem,6vw,5.4rem);line-height:.98;margin:0 0 1.25rem;color:var(--wine)}.about-lead{max-width:650px;color:#655950;font-size:1.12rem;line-height:1.7}.about-mark-panel{position:relative;display:flex;min-height:390px;align-items:center;justify-content:center;flex-direction:column;overflow:hidden;background:#6d3024;color:#fff;padding:2rem;box-shadow:0 20px 42px rgba(70,42,24,.16)}.about-mark-orbit{position:absolute;width:310px;height:310px;border:1px solid rgba(255,255,255,.35);border-radius:50%;box-shadow:0 0 0 28px rgba(255,255,255,.06),0 0 0 56px rgba(255,255,255,.04)}.about-logo{position:relative;width:150px;height:150px;object-fit:contain;border-radius:50%;margin-bottom:1.5rem}.about-panel-label{position:relative;color:#f1c89e;font-size:.7rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}.about-mark-panel strong{position:relative;margin-top:.55rem;font-family:'Playfair Display',Georgia,serif;font-size:1.55rem;text-align:center}.about-copy{color:var(--muted);font-size:1.05rem;line-height:1.8}.about-value{padding:.25rem 0}.about-value h3{margin:0 0 .6rem;color:var(--wine);font-family:'Playfair Display',Georgia,serif;font-size:1.4rem}.about-value p{max-width:23rem;color:var(--muted);line-height:1.7;margin:0}.about-cta{background:#f1e4d5}.about-cta p{color:var(--muted);font-size:1.05rem}
    body.dark-mode .about-hero{background:linear-gradient(120deg,#2d211c 0%,#201f1d 70%,#3a2a22 100%)}body.dark-mode .about-lead{color:#d5c9bc}body.dark-mode .about-cta{background:#201f1d}
    @media(max-width:767px){.py-lg-6{padding-top:4rem!important;padding-bottom:4rem!important}.about-mark-panel{min-height:300px}.about-logo{width:120px;height:120px}.about-hero h1{font-size:clamp(2.6rem,13vw,4rem)}}
</style>
@endsection
