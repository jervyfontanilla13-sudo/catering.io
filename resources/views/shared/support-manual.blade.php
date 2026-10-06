<nav class="support-manual-toc" aria-label="User manual contents">
    @foreach($chapters as $chapter)
        <a href="#chapter-{{ $chapter['key'] }}">{{ $chapter['title'] }}</a>
    @endforeach
</nav>

<div class="support-manual">
    @foreach($chapters as $index => $chapter)
        @php
            $chapterSearchParts = [$chapter['title'], implode(' ', $chapter['intro'] ?? [])];
            foreach ($chapter['sections'] ?? [] as $chapterSection) {
                $chapterSearchParts[] = $chapterSection['heading'] ?? '';
                $chapterSearchParts[] = implode(' ', $chapterSection['body'] ?? []);
                $chapterSearchParts[] = implode(' ', $chapterSection['list'] ?? []);
            }
            $chapterSearchText = strtolower(implode(' ', $chapterSearchParts));
        @endphp
        <details class="support-manual-chapter" id="chapter-{{ $chapter['key'] }}" data-search="{{ $chapterSearchText }}">
            <summary>
                <span><span class="support-manual-chapter-number">Chapter {{ $chapter['number'] }}</span>{{ $chapter['title'] }}</span>
                <span class="support-manual-toggle" aria-hidden="true"></span>
            </summary>
            <div class="support-manual-chapter-body">
                @foreach($chapter['intro'] ?? [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @foreach($chapter['sections'] ?? [] as $section)
                    <h3>{{ $section['heading'] }}</h3>
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
                    <p><a href="#category-{{ $chapter['related']['category'] }}">Related Quick Help &rarr;</a></p>
                @endif
                <nav class="support-manual-prev-next" aria-label="Manual chapter navigation">
                    @if($index > 0)
                        <a href="#chapter-{{ $chapters[$index - 1]['key'] }}">&larr; {{ $chapters[$index - 1]['title'] }}</a>
                    @endif
                    @if($index < count($chapters) - 1)
                        <a href="#chapter-{{ $chapters[$index + 1]['key'] }}">{{ $chapters[$index + 1]['title'] }} &rarr;</a>
                    @endif
                </nav>
            </div>

        </details>
    @endforeach
</div>

<script>
(() => {
    const openChapterFromHash = () => {
        const chapter = document.getElementById(window.location.hash.slice(1));
        if (chapter instanceof HTMLDetailsElement && chapter.classList.contains('support-manual-chapter')) {
            chapter.open = true;
        }
    };

    openChapterFromHash();
    window.addEventListener('hashchange', openChapterFromHash);
})();
</script>
