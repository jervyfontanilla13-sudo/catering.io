@extends('layouts.admin')

@section('content')
<div class="content-card p-4 manual-page">
    <div class="page-header">
        <div>
            <h1 class="fw-bold mb-1">Support User Manual</h1>
            <p class="text-muted mb-0">Browse the complete administrator reference in <a href="{{ route('admin.support') }}" target="_blank" rel="noopener">Support</a>.</p>
        </div>
    </div>

    <div class="manual-mobile-toc d-lg-none">
        <label class="form-label fw-bold" for="manualChapterJump">Jump to chapter</label>
        <select id="manualChapterJump" class="form-select">
            @foreach($chapters as $chapter)
                <option value="chapter-{{ $chapter['key'] }}">{{ $chapter['number'] }}. {{ $chapter['title'] }}</option>
            @endforeach
        </select>
    </div>

    <div class="manual-layout">
        <nav class="manual-toc d-none d-lg-block" aria-label="Manual table of contents">
            <div class="manual-toc-title">Contents</div>
            <ol>
                @foreach($chapters as $chapter)
                    <li><a href="#chapter-{{ $chapter['key'] }}">{{ $chapter['title'] }}</a></li>
                @endforeach
            </ol>
        </nav>

        <div class="manual-content">
            @foreach($chapters as $index => $chapter)
                <article class="manual-chapter" id="chapter-{{ $chapter['key'] }}">
                    <div class="manual-chapter-number">Chapter {{ $chapter['number'] }}</div>
                    <h2 class="h3 fw-bold">{{ $chapter['title'] }}</h2>
                    @foreach($chapter['intro'] ?? [] as $paragraph)
                        <p>{{ $paragraph }}</p>
                    @endforeach
                    @foreach($chapter['sections'] ?? [] as $section)
                        <h3 class="manual-section-heading">{{ $section['heading'] }}</h3>
                        @foreach($section['body'] ?? [] as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        @if(!empty($section['list']))
                            <ul>
                                @foreach($section['list'] as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        @endif
                    @endforeach

                    @if(!empty($chapter['related']))
                        <p class="manual-related"><a href="{{ route('admin.support') }}#category-{{ $chapter['related']['category'] }}" target="_blank" rel="noopener">Related Quick Help &rarr;</a></p>
                    @endif

                    <nav class="manual-prev-next" aria-label="Chapter navigation">
                        @if($index > 0)
                            <a href="#chapter-{{ $chapters[$index - 1]['key'] }}">&larr; {{ $chapters[$index - 1]['title'] }}</a>
                        @else
                            <span></span>
                        @endif
                        @if($index < count($chapters) - 1)
                            <a href="#chapter-{{ $chapters[$index + 1]['key'] }}">{{ $chapters[$index + 1]['title'] }} &rarr;</a>
                        @endif
                    </nav>
                </article>
            @endforeach
        </div>
    </div>
</div>

<style>
.manual-mobile-toc{margin-bottom:1.25rem}
.manual-layout{display:grid;grid-template-columns:230px 1fr;gap:2.25rem;align-items:start}
.manual-toc{position:sticky;top:1rem;padding:1rem;border:1px solid var(--line);border-radius:var(--radius);background:var(--surface)}
.manual-toc-title{font-size:.68rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);margin-bottom:.6rem}
.manual-toc ol{list-style:none;margin:0;padding:0;display:grid;gap:.35rem}
.manual-toc a{color:var(--ink);font-size:.84rem;font-weight:600}
.manual-toc a:hover{color:var(--teal-dark)}
.manual-chapter{padding-bottom:2.25rem;margin-bottom:2.25rem;border-bottom:1px solid var(--line);scroll-margin-top:5rem}
.manual-chapter:last-child{border-bottom:0}
.manual-chapter-number{color:var(--teal-dark);font-size:.68rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;margin-bottom:.3rem}
.manual-section-heading{font-size:1rem;font-weight:700;margin-top:1.1rem}
.manual-chapter p{color:var(--muted);line-height:1.6}
.manual-chapter ul{color:var(--muted);line-height:1.6}
.manual-related{margin-top:.9rem}
.manual-related a{color:var(--teal-dark);font-weight:700}
.manual-prev-next{display:flex;justify-content:space-between;gap:1rem;margin-top:1.25rem;font-size:.84rem;font-weight:700}
.manual-prev-next a{color:var(--ink)}
.manual-prev-next a:hover{color:var(--teal-dark)}
@media(max-width:991.98px){.manual-layout{grid-template-columns:1fr}}
</style>

<script>
(() => {
    const jump = document.getElementById('manualChapterJump');
    jump?.addEventListener('change', () => {
        document.getElementById(jump.value)?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
@endsection
