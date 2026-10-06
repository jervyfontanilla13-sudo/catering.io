@extends('layouts.app')

@section('title', 'Gallery | 3YOS Catering')

@section('content')
<section class="gallery-page py-5 py-lg-6">
    <div class="container">
        <header class="gallery-heading text-center mx-auto mb-4">
            <p class="eyebrow mb-2">Celebrations we have served</p>
            <h1 class="display-font mb-3">Gallery</h1>
            <p class="text-muted mb-0">A selection of thoughtful tables, generous spreads, and memorable occasions by 3YOS Catering.</p>
        </header>

        <div class="row g-4">
            @forelse($galleryItems as $item)
                <div class="col-sm-6 col-lg-4">
                    <article class="gallery-card h-100">
                        <div class="gallery-image-wrap">
                            @if(\Illuminate\Support\Facades\Storage::disk('public')->exists($item->image_path))
                                <img src="{{ route('gallery.image', ['path' => $item->image_path]) }}" alt="{{ $item->title }}" class="gallery-image" loading="lazy">
                            @else
                                <div class="gallery-image-fallback" role="img" aria-label="{{ $item->title }} photo unavailable"><span>Photo unavailable</span></div>
                            @endif
                            @if($item->is_featured)
                                <span class="gallery-featured">Featured</span>
                            @endif
                        </div>
                    </article>
                </div>
            @empty
                <div class="col-12">
                    <div class="gallery-empty text-center">
                        <p class="eyebrow mb-2">Coming soon</p>
                        <h2 class="h3 mb-2">Our event gallery is being updated.</h2>
                        <p class="text-muted mb-0">Please check back soon for fresh celebrations and catering setups.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</section>

<style>
    .gallery-page { min-height: 58vh; background: linear-gradient(180deg, var(--paper) 0%, var(--cream) 100%); }
    .gallery-heading { max-width: 650px; }
    .gallery-heading h1 { font-size: clamp(2.5rem, 5vw, 4rem); letter-spacing: -.04em; }
    .gallery-card { overflow: hidden; border: 1px solid var(--line); border-radius: 18px; background: var(--paper); box-shadow: 0 12px 28px rgba(53, 39, 26, .08); transition: transform .2s ease, box-shadow .2s ease; }
    .gallery-card:hover { transform: translateY(-5px); box-shadow: 0 18px 34px rgba(53, 39, 26, .13); }
    .gallery-image-wrap { position: relative; aspect-ratio: 4 / 3; overflow: hidden; background: #e8dfd0; }
    .gallery-image { width: 100%; height: 100%; object-fit: cover; transition: transform .35s ease; }
    .gallery-card:hover .gallery-image { transform: scale(1.04); }
    .gallery-image-fallback { display: grid; width: 100%; height: 100%; place-items: center; color: var(--muted); font-size: .85rem; font-weight: 700; }
    .gallery-featured { position: absolute; top: 1rem; left: 1rem; padding: .35rem .65rem; border-radius: 999px; background: var(--gold); color: #2c2014; font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .gallery-empty { padding: 4rem 1.5rem; border: 1px dashed #cfc3b3; border-radius: 18px; background: var(--paper); }
    body.dark-mode .gallery-page { background: linear-gradient(180deg, #201f1d 0%, #151515 100%); }
    body.dark-mode .gallery-card, body.dark-mode .gallery-empty { border-color: #4f4942; background: #201f1d; }
    body.dark-mode .gallery-image-wrap { background: #302d29; }
</style>
@endsection
