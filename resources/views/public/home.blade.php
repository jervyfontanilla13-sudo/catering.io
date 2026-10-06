@extends('layouts.app')

@section('title', '3YOS Catering | Exceptional celebrations')

@section('content')
<section class="hero">
    <div class="container py-5 py-lg-0">
        <div class="row align-items-center min-vh-75 g-5">
            <div class="col-lg-7 py-lg-5">
                <div class="eyebrow mb-3">Catering · Styling · Celebration</div>
                <h1 class="hero-title mb-4">Beautiful food for life’s important moments.</h1>
                <p class="hero-copy mb-4">From weddings to birthdays and corporate events, we create memorable experiences with great food, warm service, and stress-free planning.</p>
                <div class="d-flex flex-column flex-sm-row gap-3">
                    <a href="{{ route('reservation') }}" class="btn btn-primary">Book an event</a>
                    <a href="{{ route('packages') }}" class="btn btn-outline-primary">View packages</a>
                </div>
            </div>
            <div class="col-lg-5">
                @php
                    $availableGalleryImages = $galleryImages
                        ->filter(fn ($image) => \Illuminate\Support\Facades\Storage::disk('public')->exists($image->image_path))
                        ->values();
                @endphp
                @if($availableGalleryImages->isNotEmpty())
                    <div class="hero-art hero-art--slideshow" data-gallery-slideshow>
                        @foreach($availableGalleryImages as $index => $galleryImage)
                            @php
                                $galleryFilename = basename($galleryImage->image_path);
                                $smallImagePath = public_path("images/home-gallery/640/{$galleryFilename}");
                                $largeImagePath = public_path("images/home-gallery/960/{$galleryFilename}");
                                $hasOptimizedImages = is_file($smallImagePath) && is_file($largeImagePath);
                                $smallGalleryImageUrl = $hasOptimizedImages
                                    ? asset("images/home-gallery/640/{$galleryFilename}") . '?v=' . filemtime($smallImagePath)
                                    : null;
                                $largeGalleryImageUrl = $hasOptimizedImages
                                    ? asset("images/home-gallery/960/{$galleryFilename}") . '?v=' . filemtime($largeImagePath)
                                    : null;
                                $galleryImageUrl = $smallGalleryImageUrl
                                    ?? route('gallery.image', ['path' => $galleryImage->image_path]);
                                $galleryFallbackUrl = $hasOptimizedImages
                                    ? route('gallery.image', ['path' => $galleryImage->image_path])
                                    : null;
                            @endphp
                            <img
                                @if($hasOptimizedImages)
                                    src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs="
                                    data-fallback-src="{{ $galleryFallbackUrl }}"
                                    @if($index === 0)
                                        srcset="{{ $smallGalleryImageUrl }} 640w, {{ $largeGalleryImageUrl }} 960w"
                                        sizes="(max-width: 575.98px) calc(100vw - 5.9rem), (max-width: 991.98px) calc(100vw - 3rem), 478px"
                                    @else
                                        data-srcset="{{ $smallGalleryImageUrl }} 640w, {{ $largeGalleryImageUrl }} 960w"
                                        data-sizes="(max-width: 575.98px) calc(100vw - 5.9rem), (max-width: 991.98px) calc(100vw - 3rem), 478px"
                                    @endif
                                @elseif($index === 0)
                                    src="{{ $galleryImageUrl }}"
                                @else
                                    data-src="{{ $galleryImageUrl }}"
                                @endif
                                alt="{{ $galleryImage->title ?: 'Gallery image' }}"
                                class="hero-art__slide {{ $index === 0 ? 'is-active' : '' }}"
                                loading="{{ $index === 0 ? 'eager' : 'lazy' }}"
                                @if($index === 0) fetchpriority="high" @endif
                                data-index="{{ $index }}"
                                data-loaded="{{ $index === 0 ? 'true' : 'false' }}"
                            >
                        @endforeach
                        <div class="hero-art__overlay"></div>
                        <div class="hero-art__label">
                            <span>Made for your moment</span>
                            <strong>Menus with heart.<br>Service with ease.</strong>
                        </div>
                    </div>
                @else
                    <div class="hero-art">
                        <div class="hero-art__label">
                            <span>Made for your moment</span>
                            <strong>Menus with heart.<br>Service with ease.</strong>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="home-services py-5 py-lg-5">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <div class="eyebrow mb-2">Our services</div>
            <h2 class="section-title mb-3">Thoughtful service, beautifully executed.</h2>
        </div>
        <div class="row g-4">
            <div class="col-12 col-md-6 col-lg-4">
                <article class="service-tile h-100">
                    <h3>Weddings</h3>
                    <p>Elegant catering for your most meaningful day.</p>
                </article>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="service-tile h-100">
                    <h3>Private events</h3>
                    <p>Birthdays, debuts, and family gatherings with ease.</p>
                </article>
            </div>
            <div class="col-12 col-md-6 col-lg-4">
                <article class="service-tile h-100">
                    <h3>Corporate events</h3>
                    <p>Professional service for meetings and company milestones.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5 bg-paper" aria-labelledby="why-choose-title">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <div class="eyebrow mb-2">Why choose us</div>
            <h2 class="section-title mb-3" id="why-choose-title">Why Choose 3YOS</h2>
        </div>
        <div class="row g-4">
            <div class="col-12 col-md-6 col-xl-3">
                <article class="service-tile why-card h-100">
                    <div class="why-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" focusable="false">
                            <path d="M4 7.5h16v12H4zM8 7.5V4h8v3.5M4 12h16M10 12v2h4v-2" />
                        </svg>
                    </div>
                    <h3>Custom Catering Packages</h3>
                    <p>Catering options designed around the needs and style of your event.</p>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="service-tile why-card h-100">
                    <div class="why-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" focusable="false">
                            <path d="M12 3v3M5.6 5.6l2.1 2.1M3 12h3M18 12h3M16.3 7.7l2.1-2.1M7 14a5 5 0 1 1 10 0c0 1.5-.7 2.8-1.8 3.7-.7.6-1.2 1.4-1.2 2.3h-4c0-.9-.5-1.7-1.2-2.3A5 5 0 0 1 7 14ZM10 22h4" />
                        </svg>
                    </div>
                    <h3>Flexible Event Options</h3>
                    <p>Suitable options for different event types, guest counts, and requirements.</p>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="service-tile why-card h-100">
                    <div class="why-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" focusable="false">
                            <path d="M12 21s-7-4.4-7-10V5l7-2 7 2v6c0 5.6-7 10-7 10Z" />
                            <path d="m9 11 2 2 4-4" />
                        </svg>
                    </div>
                    <h3>Professional Event Support</h3>
                    <p>Support throughout the reservation and event planning process.</p>
                </article>
            </div>
            <div class="col-12 col-md-6 col-xl-3">
                <article class="service-tile why-card h-100">
                    <div class="why-card__icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" focusable="false">
                            <path d="M5 4v3M19 4v3M4 8h16v12H4zM8 12h3M8 16h8" />
                            <path d="m14.5 12 1 1 2-2" />
                        </svg>
                    </div>
                    <h3>Easy Reservation Process</h3>
                    <p>A simple way to explore packages, submit event details, and make a reservation.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5 bg-paper">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <div class="eyebrow mb-2">How it works</div>
                <h2 class="section-title mb-3">A smoother event planning process.</h2>
                <div class="process-list">
                    <div class="process-item">
                        <span>1</span>
                        <div>
                            <strong>Share your vision</strong>
                            <p>Tell us your event type, guest count, budget, and preferred mood.</p>
                        </div>
                    </div>
                    <div class="process-item">
                        <span>2</span>
                        <div>
                            <strong>We recommend a package</strong>
                            <p>We match your needs with a proposal that feels practical and polished.</p>
                        </div>
                    </div>
                    <div class="process-item">
                        <span>3</span>
                        <div>
                            <strong>Enjoy a stress-free event</strong>
                            <p>From setup to service, we handle the details so you can relax.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="mini-cta">
                    <div class="eyebrow mb-2">Need a custom touch?</div>
                    <h3>We can build a menu around your event.</h3>
                    <p>Perfect for weddings, milestones, and gatherings that deserve a tailored plan.</p>
                    <a href="{{ route('inquiry') }}" class="btn btn-primary">Send an inquiry</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5" aria-labelledby="home-packages-title">
    <div class="container">
        <div class="section-heading text-center mb-4">
            <div class="eyebrow mb-2">Explore packages</div>
            <h2 class="section-title mb-3" id="home-packages-title">Find a package for your event.</h2>
        </div>
        <div class="row g-4">
            @forelse($packages as $package)
                <div class="col-12 col-md-6 col-xl-3">
                    <article class="home-package-card h-100">
                        <h3>{{ $package->name }}</h3>
                        <p>{{ $package->description }}</p>
                        <a href="{{ route('packages.show', $package->slug) }}" class="btn btn-outline-primary mt-auto">View package details</a>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <p class="text-center text-muted mb-0">Explore the available catering options on our packages page.</p>
                </div>
            @endforelse
        </div>
        <div class="text-center mt-4">
            <a href="{{ route('packages') }}" class="btn btn-primary">View all packages</a>
        </div>
    </div>
</section>

<section class="py-5 py-lg-5">
    <div class="container">
        <div class="cta-panel text-center">
            <div class="eyebrow mb-2">Ready to plan?</div>
            <h2 class="section-title mb-3">Let’s make your next event feel beautifully easy.</h2>
            <p class="mb-4">Tell us about your celebration and we’ll help you build the right package.</p>
            <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                <a href="{{ route('reservation') }}" class="btn btn-primary">Book an event</a>
                <a href="{{ route('inquiry') }}" class="btn btn-outline-primary">Send an inquiry</a>
            </div>
        </div>
    </div>
</section>

<style>
    .min-vh-75{min-height:68vh}
    .hero{background:linear-gradient(115deg,#f5eee3 0%,#fbf8f2 60%,#e8d9c6 100%);padding-top:calc(74px + clamp(2.25rem,4vw,4.25rem))}
    .hero-title{font-size:clamp(2.8rem,5vw,4.8rem);line-height:.98;letter-spacing:-.045em;max-width:700px}
    .hero-copy{color:#625e57;font-size:1.08rem;line-height:1.7;max-width:560px}
    .hero-art{min-height:390px;position:relative;overflow:hidden;background:radial-gradient(circle at 40% 25%,#e8bd78 0 10%,transparent 10.5%),radial-gradient(circle at 70% 75%,#ac5e3f 0 17%,transparent 17.5%),linear-gradient(145deg,#7c3d2d,#d38a5e);box-shadow:18px 18px 0 #ded2c0;border-radius:26px}
    .hero-art:before,.hero-art:after{content:'';position:absolute;border:1px solid rgba(255,255,255,.45);border-radius:50%}
    .hero-art:before{width:315px;height:315px;top:35px;left:48px}
    .hero-art:after{width:210px;height:210px;bottom:-65px;right:-35px}
    .hero-art--slideshow{background:#3a2d26}
    .hero-art__slide{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transition:opacity 1s ease-in-out}
    .hero-art__slide.is-active{opacity:1}
    .hero-art__overlay{position:absolute;inset:0;background:linear-gradient(180deg,rgba(32,24,20,.18),rgba(18,14,12,.46));z-index:1}
    .hero-art__label{position:absolute;z-index:2;bottom:28px;left:28px;color:#fff;max-width:78%}
    .hero-art__label span{display:block;font-size:.7rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase;margin-bottom:.45rem}
    .hero-art__label strong{font-family:'Playfair Display',serif;font-size:1.65rem;line-height:1.1}
    .home-services .service-tile{padding:clamp(1.5rem,2.2vw,2rem);border:1px solid var(--line);background:var(--paper);border-radius:14px;transition:border-color .2s ease,transform .2s ease}
    .home-services .service-tile:hover{border-color:color-mix(in srgb,var(--wine) 28%,var(--line));transform:translateY(-2px)}
    .home-services .service-tile h3{margin:0 0 .65rem;color:var(--wine);font-family:'Playfair Display',Georgia,serif;font-size:1.45rem}
    .home-services .service-tile p{color:var(--muted);line-height:1.7;margin-bottom:0}
    .why-card{display:flex;flex-direction:column;gap:.85rem;padding:1.5rem}
    .why-card h3{font-size:1.25rem;line-height:1.2;margin:0}
    .why-card p{margin:0}
    .why-card__icon{display:grid;place-items:center;width:48px;height:48px;flex:0 0 48px;border-radius:14px;background:rgba(109,48,36,.08);color:var(--wine)}
    .why-card__icon svg{width:25px;height:25px;stroke:currentColor;stroke-width:1.7;stroke-linecap:round;stroke-linejoin:round}
    .process-list{display:grid;gap:1rem}
    .process-item{display:flex;gap:1rem;padding:1rem 1.1rem;border:1px solid rgba(109,48,36,.08);border-radius:18px;background:#f8efe6}
    .process-item span{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border-radius:50%;background:var(--wine);color:#fff;font-weight:800}
    .process-item strong{display:block;margin-bottom:.3rem}
    .process-item p{margin:0;color:var(--muted);line-height:1.6}
    .mini-cta{padding:2rem;border-radius:24px;background:linear-gradient(145deg,#f3e7d9,#f8f0ea);border:1px solid rgba(109,48,36,.08)}
    .mini-cta h3{font-size:clamp(1.7rem,2vw,2.4rem);line-height:1.1;margin-bottom:.75rem}
    .mini-cta p{color:var(--muted);line-height:1.7;margin-bottom:1.25rem}
    .occasion-panel{padding:clamp(2rem,4vw,4rem);background:#ece3d5}
    .check-list{list-style:none;padding:0}
    .check-list li{padding:1rem 0;border-bottom:1px solid rgba(109,48,36,.16);font-weight:700}
    .check-list li:last-child{border-bottom:0}
    .check-list span{display:inline-grid;place-items:center;width:22px;height:22px;margin-right:.75rem;border-radius:50%;background:rgba(109,48,36,.08);color:var(--wine);font-size:.75rem}
    .section-title{font-size:clamp(2.1rem,3.4vw,3rem);line-height:1.08;letter-spacing:-.03em}
    .cta-panel{padding:2.5rem 1.5rem;border:1px solid rgba(109,48,36,.08);background:linear-gradient(140deg,#f9f3ed,#f4e7d8);border-radius:22px}
    .home-package-card{display:flex;flex-direction:column;gap:.9rem;padding:1.5rem;border:1px solid rgba(109,48,36,.08);background:#f9f2ea;border-radius:22px;box-shadow:0 10px 28px rgba(109,48,36,.04)}
    .home-package-card h3{font-size:1.5rem;margin:0}
    .home-package-card p{color:var(--muted);line-height:1.65;margin:0}
    @media(max-width:575px){.hero-art{min-height:280px}.hero-title{font-size:2.5rem}.cta-panel{padding:2rem 1rem}.process-item{padding:.85rem .9rem}.why-card,.home-package-card{padding:1.35rem}}
</style>

@if($galleryImages->count() > 1)
<script>
    (() => {
        const slideshow = document.querySelector('[data-gallery-slideshow]');
        if (!slideshow) return;

        const slides = Array.from(slideshow.querySelectorAll('.hero-art__slide'));
        if (slides.length === 0) return;

        slides[0].addEventListener('load', () => { slides[0].dataset.loaded = 'true'; });
        slides[0].addEventListener('error', () => {
            const slide = slides[0];
            if (slide.dataset.fallbackSrc && slide.dataset.fallbackAttempted !== 'true') {
                slide.dataset.fallbackAttempted = 'true';
                slide.removeAttribute('srcset');
                slide.src = slide.dataset.fallbackSrc;
            } else {
                slide.style.display = 'none';
            }
        });
        if (slides.length < 2) return;

        let currentIndex = 0;
        const showSlide = (index) => {
            slides.forEach((slide, slideIndex) => {
                slide.classList.toggle('is-active', slideIndex === index);
            });
        };

        const loadSlide = (slide) => {
            if (slide.dataset.loaded === 'true') return Promise.resolve(true);

            return new Promise((resolve) => {
                let completed = false;
                let usingFallback = false;
                const finish = (loaded) => {
                    if (completed) return;
                    completed = true;
                    slide.dataset.loaded = String(loaded);
                    if (!loaded) slide.style.display = 'none';
                    slide.removeEventListener('load', handleLoad);
                    slide.removeEventListener('error', handleError);
                    resolve(loaded);
                };
                const handleLoad = () => finish(true);
                const handleError = () => {
                    if (!usingFallback && slide.dataset.fallbackSrc) {
                        usingFallback = true;
                        slide.removeAttribute('srcset');
                        slide.src = slide.dataset.fallbackSrc;
                        return;
                    }
                    finish(false);
                };

                slide.addEventListener('load', handleLoad, { once: true });
                slide.addEventListener('error', handleError);

                if (slide.dataset.srcset) slide.srcset = slide.dataset.srcset;
                if (slide.dataset.sizes) slide.sizes = slide.dataset.sizes;
                if (slide.dataset.src) slide.src = slide.dataset.src;
                delete slide.dataset.src;
                delete slide.dataset.srcset;
                delete slide.dataset.sizes;

                if (slide.complete && slide.naturalWidth > 0) finish(true);
            });
        };

        const advanceSlideshow = async () => {
            const nextIndex = (currentIndex + 1) % slides.length;
            if (await loadSlide(slides[nextIndex])) {
                currentIndex = nextIndex;
                showSlide(currentIndex);
            }
            window.setTimeout(advanceSlideshow, 4500);
        };

        window.setTimeout(advanceSlideshow, 4500);
    })();
</script>
@endif
@endsection
