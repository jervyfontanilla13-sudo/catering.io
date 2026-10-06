@php
    $manualRoute = $manualRoute ?? 'admin.support';
@endphp
<section class="help-category" id="category-{{ $category['key'] }}">
    <h3 class="h5 fw-bold mb-1">{{ $category['title'] }}</h3>
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
                        <a class="help-manual-link" href="{{ route($manualRoute) }}#chapter-{{ $article['manual']['chapter'] }}">{{ $article['manual']['label'] }} &rarr;</a>
                    @endif
                </div>
            </details>
        @endforeach
    </div>
</section>
