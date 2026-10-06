@extends('layouts.admin')

@section('content')
<div class="content-card p-4 help-page">
    <div class="page-header">
        <div>
            <p class="text-uppercase small fw-bold text-muted mb-1">3YOS Support</p>
            <h1 class="fw-bold mb-1">How can we help you?</h1>
            <p class="text-muted mb-0">Search quick answers and the complete guest and administrator manual in one place.</p>
        </div>
    </div>

    <div class="help-search">
        <label class="visually-hidden" for="helpSearch">Search Support</label>
        <input type="search" id="helpSearch" class="form-control" placeholder="Search quick help and the manual...">
    </div>

    <h2 class="h5 fw-bold mt-4 mb-2">Quick Help</h2>
    <nav class="help-chips" aria-label="Quick Help categories">
        @foreach($adminCategories as $category)
            <a href="#category-{{ $category['key'] }}">{{ $category['title'] }}</a>
        @endforeach
        @foreach($guestCategories as $category)
            <a href="#category-{{ $category['key'] }}">{{ $category['title'] }}</a>
        @endforeach
    </nav>

    <p id="helpNoResults" class="help-no-results" hidden>No support content found. Try a different search term.</p>

    <h2 class="h4 fw-bold mt-4 mb-3">Administrator Quick Help</h2>
    @foreach($adminCategories as $category)
        @include('admin.partials.help-category', ['category' => $category, 'manualRoute' => 'admin.support'])
    @endforeach

    <hr class="my-4">

    <h2 class="h4 fw-bold mb-1">Guest Quick Help</h2>
    <p class="text-muted small mb-3">Customer-facing guidance for answering guest questions.</p>
    @foreach($guestCategories as $category)
        @include('admin.partials.help-category', ['category' => $category, 'manualRoute' => 'admin.support'])
    @endforeach

    <hr class="my-4">
    <h2 class="h4 fw-bold mb-1">User Manual</h2>
    <p class="text-muted small mb-3">Detailed guest and admin procedures. Admin-only instructions are available here only to signed-in administrators.</p>
    @include('shared.support-manual', ['chapters' => $chapters])

    <section class="support-contact mt-4">
        <h2 class="h5 fw-bold mb-1">Need more help?</h2>
        <p class="text-muted mb-0">Contact 3YOS Catering through the <a href="{{ route('contact') }}">contact page</a> or review the <a href="{{ route('admin.inquiries') }}">inquiry inbox</a>.</p>
    </section>
</div>

<style>
.help-search{max-width:480px;margin:0 0 1rem}
.help-chips{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.5rem}
.help-chips a{display:inline-flex;align-items:center;height:32px;padding:0 .9rem;border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--muted);font-size:.8rem;font-weight:700;text-decoration:none}
.help-chips a:hover{border-color:var(--teal);color:var(--teal-dark)}
.help-no-results{text-align:center;padding:1.25rem;color:var(--muted)}
.help-category{margin-bottom:1.75rem;scroll-margin-top:5rem}
.help-article-list{display:grid;gap:.6rem}
.help-article{border:1px solid var(--line);border-radius:var(--radius-sm);background:var(--surface);padding:.85rem 1rem}
.help-article summary{cursor:pointer;font-weight:700;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:.75rem;color:var(--ink)}
.help-article summary::-webkit-details-marker{display:none}
.help-article summary::after{content:'+';font-size:1.1rem;color:var(--teal-dark);flex:0 0 auto}
.help-article[open] summary::after{content:'\2212'}
.help-article summary:focus-visible{outline:2px solid var(--teal-dark);outline-offset:3px;border-radius:4px}
.help-article-body{margin-top:.6rem;color:var(--muted);font-size:.88rem;line-height:1.55}
.help-article-body p{margin:0 0 .5rem}
.help-article-body ul,.help-article-body ol{margin:0 0 .5rem;padding-left:1.15rem}
.help-article-body li{margin-bottom:.22rem}
.help-manual-link{display:inline-block;margin-top:.2rem;color:var(--teal-dark);font-weight:700;font-size:.84rem}
.help-manual-link:hover{text-decoration:underline}
.support-manual-toc{display:flex;flex-wrap:wrap;gap:.45rem;margin:1rem 0}
.support-manual-toc a{padding:.35rem .7rem;border:1px solid var(--line);border-radius:999px;color:var(--ink);font-size:.78rem;font-weight:600;text-decoration:none}
.support-manual-toc a:hover{color:var(--teal-dark);border-color:var(--teal)}
.support-manual{display:grid;gap:.55rem}
.support-manual-chapter{scroll-margin-top:1rem;border:1px solid var(--line);border-radius:var(--radius-sm);background:var(--surface);padding:.8rem 1rem}
.support-manual-chapter>summary{display:flex;justify-content:space-between;align-items:center;gap:.75rem;cursor:pointer;font-weight:700;list-style:none}
.support-manual-chapter>summary::-webkit-details-marker{display:none}
.support-manual-chapter>summary:focus-visible{outline:2px solid var(--teal-dark);outline-offset:3px;border-radius:4px}
.support-manual-chapter-number{display:block;color:var(--muted);font-size:.65rem;letter-spacing:.08em;text-transform:uppercase}
.support-manual-toggle:after{content:'+';color:var(--teal-dark);font-size:1.15rem}
.support-manual-chapter[open] .support-manual-toggle:after{content:'\2212'}
.support-manual-chapter-body{padding-top:.8rem;color:var(--muted);font-size:.88rem;line-height:1.55}
.support-manual-chapter-body h3{margin-top:.9rem;color:var(--ink);font-size:.98rem;font-weight:700}
.support-manual-chapter-body li{margin-bottom:.2rem}
.support-manual-prev-next{display:flex;justify-content:space-between;gap:1rem;font-size:.82rem;font-weight:700}
.support-manual-prev-next a,.support-manual-chapter-body a,.support-contact a{color:var(--teal-dark)}
.support-contact{border-top:1px solid var(--line);padding-top:1rem}
</style>

<script>
(() => {
    const input = document.getElementById('helpSearch');
    const noResults = document.getElementById('helpNoResults');
    const sections = Array.from(document.querySelectorAll('.help-category'));
    const chapters = Array.from(document.querySelectorAll('.support-manual-chapter'));
    if (!input) return;

    input.addEventListener('input', () => {
        const term = input.value.trim().toLowerCase();
        let anyVisible = false;

        sections.forEach((section) => {
            let sectionHasMatch = false;
            section.querySelectorAll('.help-article').forEach((article) => {
                const matches = term === '' || article.dataset.search.includes(term);
                article.hidden = !matches;
                article.open = matches && term !== '';
                if (matches) sectionHasMatch = true;
            });
            section.hidden = !sectionHasMatch;
            if (sectionHasMatch) anyVisible = true;
        });

        chapters.forEach((chapter) => {
            const matches = term === '' || chapter.dataset.search.includes(term);
            chapter.hidden = !matches;
            chapter.open = matches && term !== '';
            if (matches) anyVisible = true;
        });

        noResults.hidden = term === '' || anyVisible;
    });
})();
</script>
@endsection
