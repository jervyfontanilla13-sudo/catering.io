@php
    $totalPages = $paginator->lastPage();
    $currentPage = $paginator->currentPage();
    $windowSize = 10;
    $windowStart = $currentPage <= $windowSize
        ? 1
        : min($currentPage - 4, max(1, $totalPages - $windowSize + 1));
    $windowEnd = min($totalPages, $windowStart + $windowSize - 1);
    $visiblePages = collect(range($windowStart, $windowEnd));

    if ($windowStart > 1) {
        $visiblePages->prepend(1);
    }

    if ($totalPages > $windowSize && $windowEnd < $totalPages - 1) {
        $visiblePages = $visiblePages->concat([$totalPages - 1, $totalPages])->unique()->sort()->values();
    }

    $resultLabel = $resultLabel ?? 'results';
    $ariaLabel = $ariaLabel ?? 'Pagination';
@endphp

<div class="admin-pagination">
    @if($paginator->hasPages())
        <nav aria-label="{{ $ariaLabel }}">
            <ul class="pagination mb-0">
                <li class="page-item {{ $paginator->onFirstPage() ? 'disabled' : '' }}">
                    @if($paginator->onFirstPage())
                        <span class="page-link" aria-disabled="true" aria-label="Previous page">&#8249;</span>
                    @else
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">&#8249;</a>
                    @endif
                </li>

                @php($previousPage = null)
                @foreach($visiblePages as $page)
                    @if($previousPage !== null && $page - $previousPage > 1)
                        <li class="page-item disabled" aria-hidden="true"><span class="page-link">…</span></li>
                    @endif

                    @php($isMobilePage = $page <= 2 || $page >= $totalPages - 1 || abs($page - $currentPage) <= 1)
                    <li class="page-item admin-pagination-page {{ $isMobilePage ? 'admin-pagination-page--mobile' : '' }} {{ $currentPage === $page ? 'active' : '' }}">
                        @if($currentPage === $page)
                            <span class="page-link" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="page-link" href="{{ $paginator->url($page) }}">{{ $page }}</a>
                        @endif
                    </li>

                    @php($previousPage = $page)
                @endforeach

                <li class="page-item {{ $paginator->hasMorePages() ? '' : 'disabled' }}">
                    @if($paginator->hasMorePages())
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">&#8250;</a>
                    @else
                        <span class="page-link" aria-disabled="true" aria-label="Next page">&#8250;</span>
                    @endif
                </li>
            </ul>
        </nav>
    @endif

    <p class="admin-pagination-range text-muted small mb-0">
        Showing {{ $paginator->firstItem() ?? 0 }} to {{ $paginator->lastItem() ?? 0 }} of {{ $paginator->total() }} {{ $resultLabel }}
    </p>
</div>
