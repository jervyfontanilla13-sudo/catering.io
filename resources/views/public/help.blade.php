@extends('layouts.app')

@section('title', 'Support | 3YOS Catering')

@section('content')
<div class="container help-page">
    <div class="help-hero">
        <p class="eyebrow mb-2">3YOS Support</p>
        <h1 class="display-font mb-2">How can we help you?</h1>
        <p class="text-muted mb-4">Find quick answers or browse the complete guest User Manual.</p>
        <div class="help-search">
            <label class="visually-hidden" for="helpSearch">Search Support</label>
            <input type="search" id="helpSearch" class="form-control" placeholder="Search quick help and the manual...">
        </div>
        <h2 class="h5 display-font mb-2">Quick Help</h2>
        <nav class="help-chips" aria-label="Quick Help categories">
            @foreach($categories as $category)
                <a href="#category-{{ $category['key'] }}">{{ $category['title'] }}</a>
            @endforeach
        </nav>
    </div>

    <p id="helpNoResults" class="help-no-results" hidden>No support content found. Try a different search term, or <a href="{{ route('inquiry') }}">send us an inquiry</a> instead.</p>

    @foreach($categories as $category)
        <section class="help-category" id="category-{{ $category['key'] }}">
            <h2 class="h4 display-font mb-1">{{ $category['title'] }}</h2>
            <p class="text-muted small mb-3">{{ $category['description'] }}</p>
            <div class="help-article-list">
                @foreach($category['articles'] as $article)
                    @php
                        $searchText = strtolower(implode(' ', array_filter([
                            $category['title'],
                            $category['description'],
                            $article['question'],
                            implode(' ', $article['summary'] ?? []),
                            implode(' ', $article['steps'] ?? []),
                            implode(' ', $article['keywords'] ?? []),
                        ])));
                    @endphp
                    <details class="help-article" data-search="{{ $searchText }}">
                        <summary>{{ $article['question'] }}</summary>
                        <div class="help-article-body">
                            @if(count($article['summary'] ?? []) > 1 && empty($article['steps']))
                                <ul>
                                    @foreach($article['summary'] as $line)
                                        <li>{{ $line }}</li>
                                    @endforeach
                                </ul>
                            @else
                                @foreach($article['summary'] ?? [] as $line)
                                    <p>{{ $line }}</p>
                                @endforeach
                            @endif
                            @if(!empty($article['steps']))
                                <ol>
                                    @foreach($article['steps'] as $step)
                                        <li>{{ $step }}</li>
                                    @endforeach
                                </ol>
                            @endif
                            @if(!empty($article['manual']))
                                <a class="help-manual-link" href="{{ route('support') }}#chapter-{{ $article['manual']['chapter'] }}">{{ $article['manual']['label'] }} &rarr;</a>
                            @endif
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="support-manual-section">
        <h2 class="h3 display-font mb-1">User Manual</h2>
        <p class="text-muted mb-3">Step-by-step reference for reservations, packages, payments, contracts, and common questions.</p>
        @include('shared.support-manual', ['chapters' => $chapters])
    </section>

    <section class="support-contact">
        <h2 class="h4 display-font mb-1">Need more help?</h2>
        <p class="text-muted mb-0">Contact 3YOS Catering through the <a href="{{ route('contact') }}">contact page</a> or <a href="{{ route('inquiry') }}">send us an inquiry</a>.</p>
    </section>
</div>

<style>
.help-page{max-width:900px;margin:0 auto}
.help-hero{text-align:center;padding:1rem 0 1.5rem}
.help-search{max-width:480px;margin:0 auto 1.25rem}
.help-search .form-control{border-radius:999px;padding:.75rem 1.25rem;border-color:var(--line)}
.help-chips{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:center}
.help-chips a{display:inline-flex;align-items:center;height:34px;padding:0 1rem;border:1px solid var(--line);border-radius:999px;color:var(--ink);font-size:.82rem;font-weight:700;background:var(--paper)}
.help-chips a:hover,.help-chips a:focus-visible{border-color:var(--terracotta);color:var(--terracotta)}
.help-no-results{text-align:center;padding:1.5rem;color:var(--muted)}
.help-category{margin-bottom:2.25rem;scroll-margin-top:6rem}
.help-article-list{display:grid;gap:.6rem}
.help-article{border:1px solid var(--line);border-radius:12px;background:var(--paper);padding:.9rem 1.1rem}
.help-article summary{cursor:pointer;font-weight:700;list-style:none;display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.help-article summary::-webkit-details-marker{display:none}
.help-article summary::after{content:'+';font-size:1.2rem;color:var(--terracotta);flex:0 0 auto}
.help-article[open] summary::after{content:'\2212'}
.help-article summary:focus-visible{outline:2px solid var(--terracotta);outline-offset:3px;border-radius:4px}
.help-article-body{margin-top:.65rem;color:var(--muted);font-size:.92rem;line-height:1.55}
.help-article-body p{margin:0 0 .5rem}
.help-article-body ul,.help-article-body ol{margin:0 0 .5rem;padding-left:1.2rem}
.help-article-body li{margin-bottom:.25rem}
.help-manual-link{display:inline-block;margin-top:.25rem;color:var(--wine);font-weight:700;font-size:.86rem}
.help-manual-link:hover{text-decoration:underline}
.support-manual-section{margin-top:2.5rem}
.support-manual-toc{display:flex;flex-wrap:wrap;gap:.45rem;margin:1rem 0}
.support-manual-toc a{padding:.35rem .75rem;border:1px solid var(--line);border-radius:999px;background:var(--paper);color:var(--ink);font-size:.8rem;font-weight:600;text-decoration:none}
.support-manual-toc a:hover{border-color:var(--terracotta);color:var(--terracotta)}
.support-manual{display:grid;gap:.6rem}
.support-manual-chapter{scroll-margin-top:6rem;border:1px solid var(--line);border-radius:12px;background:var(--paper);padding:.9rem 1.1rem}
.support-manual-chapter>summary{display:flex;align-items:center;justify-content:space-between;gap:.75rem;cursor:pointer;font-weight:700;list-style:none}
.support-manual-chapter>summary::-webkit-details-marker{display:none}
.support-manual-chapter>summary:focus-visible{outline:2px solid var(--terracotta);outline-offset:3px;border-radius:4px}
.support-manual-chapter-number{display:block;color:var(--muted);font-size:.68rem;letter-spacing:.08em;text-transform:uppercase}
.support-manual-toggle:after{content:'+';color:var(--terracotta);font-size:1.2rem}
.support-manual-chapter[open] .support-manual-toggle:after{content:'\2212'}
.support-manual-chapter-body{padding-top:.7rem;color:var(--muted);font-size:.92rem;line-height:1.6}
.support-manual-chapter-body h3{margin-top:1rem;color:var(--ink);font-size:1rem;font-weight:700}
.support-manual-chapter-body li{margin-bottom:.25rem}
.support-manual-prev-next{display:flex;justify-content:space-between;gap:1rem;font-size:.85rem;font-weight:700}
.support-manual-prev-next a,.support-manual-chapter-body a,.support-contact a{color:var(--wine)}
.support-contact{margin-top:2.5rem;padding-top:1.25rem;border-top:1px solid var(--line)}
@media(max-width:575.98px){.help-chips{gap:.4rem}.help-chips a{height:30px;padding:0 .75rem;font-size:.76rem}}
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
