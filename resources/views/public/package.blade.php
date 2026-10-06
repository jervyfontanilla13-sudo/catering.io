@extends('layouts.app')

@section('content')
@php
    $guests = max(0, min(1000, (int) request()->query('guests')));
    $hasImage = $package->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($package->image_path);
    $details = [
        ['Menu', $package->menu, '<path d="M4 3v7a2 2 0 0 0 2 2v9M8 3v7M6 3v4M16 3c-2 2-2.5 5-2 8h2v10"/>'],
        ['Freebies', $package->freebies, '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9M12 8v13M12 8c-2 0-4-1-4-3a2 2 0 0 1 4 0c0-2 4-2 4 0 0 2-2 3-4 3z"/>'],
        ['Add-ons', $package->addons, '<path d="M12 5v14M5 12h14"/>'],
    ];
@endphp
<div class="container package-detail">
    <article class="card package-card">
        <div class="package-hero">
            @if($hasImage)
                <img src="{{ route('package.image', ['path' => $package->image_path]) }}" alt="{{ $package->name }} catering package">
            @else
                <div class="package-detail-image-fallback" role="img" aria-label="{{ $package->name }} package image unavailable">{{ $package->name }} package image unavailable</div>
            @endif
        </div>

        <div class="package-body">
            <header class="package-header">
                <div>
                    <span class="package-label">Catering Package</span>
                    <h1 class="fw-bold">{{ $package->name }}</h1>
                    <p class="package-lead">{{ $package->description }}</p>
                </div>
            </header>

            <section class="package-price-panel" aria-labelledby="package-price-heading">
                <div>
                    <h2 id="package-price-heading" class="package-section-title">Package Price</h2>
                    <p class="package-price"><span>&#8369;{{ number_format($package->price, 2) }}</span> per guest</p>
                    @if($guests)
                        <p class="package-estimate">Estimated for {{ number_format($guests) }} {{ Str::plural('guest', $guests) }}: <strong>&#8369;{{ number_format($package->estimatedTotalFor($guests), 2) }}</strong><br>
                            <small>&#8369;{{ number_format($package->price, 0) }} &times; {{ number_format($guests) }} {{ Str::plural('guest', $guests) }}</small></p>
                    @else
                        <p class="package-estimate">Estimated total will depend on your selected number of guests.</p>
                    @endif
                </div>
                <form method="GET" action="{{ route('reservation') }}" class="package-book-form">
                    <input type="hidden" name="package" value="{{ $package->id }}">
                    <label for="package-guests" class="form-label mb-1">Number of guests</label>
                    <input type="number" id="package-guests" name="guests" min="1" max="1000" value="{{ $guests }}" class="form-control" placeholder="e.g. 50" inputmode="numeric">
                    <button type="submit" class="btn btn-primary package-cta">Choose this package</button>
                </form>
            </section>

            <section aria-labelledby="package-details-heading">
                <h2 id="package-details-heading" class="package-section-title">Package Details</h2>
                <div class="package-details">
                    @foreach($details as [$label, $text, $icon])
                        <div class="package-detail {{ $loop->last ? 'package-detail-wide' : '' }}">
                            <div class="package-detail-head">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icon !!}</svg>
                                <h3>{{ $label }}</h3>
                            </div>
                            <p>{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="package-pricing" aria-labelledby="package-pricing-heading">
                <h2 id="package-pricing-heading" class="package-section-title">Pricing Information</h2>
                <p class="mb-1">The estimated total is the price per guest multiplied by your number of guests.</p>
                <p class="mb-0">Final contract pricing is confirmed by our team and may differ from the estimate.</p>
            </section>
        </div>
    </article>
</div>
<style>
.package-detail{max-width:980px}
.package-card{overflow:hidden;border:1px solid var(--line);border-radius:14px;box-shadow:0 6px 24px rgba(32,32,29,.06)}
.package-hero{height:clamp(200px,34vw,360px);overflow:hidden;background:#edf2f4}
.package-hero img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .5s ease}
.package-card:hover .package-hero img{transform:scale(1.02)}
.package-body{padding:clamp(1.25rem,3vw,2.25rem);display:grid;gap:1.75rem}
.package-header{display:flex;justify-content:space-between;align-items:flex-end;gap:1.5rem}
.package-label{display:inline-block;margin-bottom:.5rem;font-size:.72rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:var(--terracotta)}
.package-header h1{font-size:clamp(2rem,4vw,2.75rem);line-height:1.1;margin:0 0 .5rem;color:var(--wine)}
.package-lead{margin:0;max-width:52ch;color:var(--muted);font-size:1.05rem}
.package-section-title{font-family:'DM Sans',sans-serif;font-size:.78rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin:0 0 .85rem}
.package-details{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
.package-detail{padding:1.15rem 1.25rem;border:1px solid var(--line);border-radius:10px;background:var(--paper);transition:border-color .2s,box-shadow .2s}
.package-detail:hover{border-color:var(--gold);box-shadow:0 4px 14px rgba(32,32,29,.06)}
.package-detail-wide{grid-column:1/-1}
.package-detail-head{display:flex;align-items:center;gap:.6rem;margin-bottom:.4rem;color:var(--terracotta)}
.package-detail-head h3{margin:0;font-family:'DM Sans',sans-serif;font-size:1rem;font-weight:700;color:var(--ink)}
.package-detail p{margin:0;line-height:1.6}
.package-pricing{padding:1.15rem 1.25rem;border-left:3px solid var(--gold);border-radius:0 10px 10px 0;background:rgba(199,152,75,.1)}
.package-cta{align-items:center;justify-content:center;padding:.8rem 1.6rem;font-weight:700;white-space:nowrap;transition:transform .2s,box-shadow .2s,background .2s}
.package-cta:hover,.package-cta:focus-visible{box-shadow:0 6px 16px rgba(109,48,36,.25)}
.package-cta:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
.package-price-panel{display:flex;justify-content:space-between;align-items:flex-end;gap:1.5rem;padding:1.5rem;border:1px solid var(--line);border-radius:12px;background:var(--paper)}
.package-price{margin:0;color:var(--muted);font-size:1rem}
.package-price span{font-family:'Playfair Display',Georgia,serif;font-size:clamp(2.25rem,5vw,3rem);font-weight:700;line-height:1.1;color:var(--wine)}
.package-estimate{margin:.5rem 0 0}
.package-book-form{display:grid;gap:.5rem;min-width:min(100%,260px)}
@media (max-width:767.98px){.package-price-panel{display:grid}.package-cta{width:100%}}
body.dark-mode .package-price-panel{background:#201f1d;border-color:#3a3835}
body.dark-mode .package-price span{color:#f5f1e9}
.package-detail-image-fallback{display:grid;height:100%;place-items:center;color:var(--muted);font-weight:700}
@media (max-width:767.98px){.package-details{grid-template-columns:1fr}.package-header{display:block}}
@media (prefers-reduced-motion:reduce){.package-hero img,.package-cta,.package-detail{transition:none}.package-card:hover .package-hero img{transform:none}}
body.dark-mode .package-card,body.dark-mode .package-detail{background:#201f1d!important;border-color:#3a3835}
body.dark-mode .package-header h1,body.dark-mode .package-detail-head h3{color:#f5f1e9}
body.dark-mode .package-pricing{background:rgba(199,152,75,.14);color:#f5f1e9}
</style>
@endsection
