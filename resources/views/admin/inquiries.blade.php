@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div><h1 class="fw-bold mb-1">Inquiries</h1><p class="text-muted mb-0">Track messages from prospective clients.</p></div></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif

    <section class="attention-card card mb-4">
        <div class="panel-header">
            <div>
                <h2 class="h5 fw-bold mb-1">Needs attention</h2>
                <p class="text-muted small mb-0">
                    @if($needsAttention->count() > 0)
                        {{ $needsAttention->count() }} inquir{{ $needsAttention->count() === 1 ? 'y needs' : 'ies need' }} your response
                    @else
                        No inquiries currently require a response.
                    @endif
                </p>
            </div>
        </div>
        @if($needsAttention->isEmpty())
            <p class="mb-0 attention-clear">&#10003; You're all caught up.</p>
        @else
            <div class="inquiry-card-list">
                @foreach($needsAttention as $inquiry)
                    @include('admin.partials.inquiry-card', ['inquiry' => $inquiry])
                @endforeach
            </div>
        @endif
    </section>

    <hr class="my-4">

    <h2 class="h5 fw-bold mb-3">All inquiries</h2>

    <form id="inquiry-filter-form" method="GET" action="{{ route('admin.inquiries') }}" class="filter-bar" data-live-filter data-live-filter-target="#inquiry-results">
        <div class="filter-field filter-field--wide">
            <label class="form-label" for="inquiry-search">Search</label>
            <input id="inquiry-search" type="search" name="search" class="form-control" value="{{ old('search', $search ?? '') }}" placeholder="Name, email, subject, or inquiry ID">
        </div>
        <input type="hidden" name="view" value="{{ $view }}">
        @if(($search ?? '') !== '' || $view !== 'all')
            <div class="filter-field">
                <a href="{{ route('admin.inquiries') }}" class="btn btn-outline-secondary w-100" data-live-filter-clear="#inquiry-filter-form">Clear</a>
            </div>
        @endif
    </form>

    <div class="inquiry-tabs" role="tablist" aria-label="Filter by status">
        @foreach(['all' => 'All', 'new' => 'New', 'in_progress' => 'In Progress', 'responded' => 'Responded', 'closed' => 'Closed'] as $value => $label)
            <a href="{{ route('admin.inquiries', array_filter(['view' => $value, 'search' => $search])) }}"
               class="inquiry-tab {{ $view === $value ? 'is-active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>

    <div id="inquiry-results" aria-live="polite">
        @if($inquiries->isEmpty())
            <div class="text-center text-muted py-5">
                @if($view === 'all' && ($search ?? '') === '')
                    <p class="mb-0 fw-bold">No inquiries yet.</p>
                    <p class="mb-0">New customer inquiries will appear here.</p>
                @else
                    <p class="mb-0">No inquiries match these filters.</p>
                @endif
            </div>
        @else
            <div class="inquiry-card-list">
                @foreach($inquiries as $inquiry)
                    @include('admin.partials.inquiry-card', ['inquiry' => $inquiry])
                @endforeach
            </div>
        @endif
    </div>
</div>

<style>
    .attention-clear { padding: .75rem 0; color: var(--teal-dark); font-weight: 700; }
    .inquiry-tabs { display: flex; flex-wrap: wrap; gap: .4rem; margin: 0 0 1.1rem; }
    .inquiry-tab { display: inline-flex; align-items: center; height: 32px; padding: 0 .9rem; border: 1px solid var(--line); border-radius: 999px; background: var(--surface); color: var(--muted); font-size: .8rem; font-weight: 700; text-decoration: none; }
    .inquiry-tab:hover { border-color: var(--teal); color: var(--teal-dark); }
    .inquiry-tab.is-active { border-color: var(--teal-dark); background: var(--teal-dark); color: #fff; }

    .inquiry-card-list { display: grid; gap: .75rem; }
    .inquiry-card { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem 1.5rem; padding: 1rem 1.15rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .inquiry-card--unread { border-color: rgba(212, 155, 40, .4); background: #fffaf0; }
    body.dark-mode .inquiry-card--unread { background: rgba(146, 99, 0, .12); }
    .inquiry-card-identity { display: flex; align-items: flex-start; gap: .6rem; flex: 1 1 200px; min-width: 0; }
    .inquiry-unread-dot { flex: 0 0 auto; width: 9px; height: 9px; margin-top: .4rem; border-radius: 50%; background: #d49b28; }
    .inquiry-card-identity-text { min-width: 0; }
    .inquiry-card-name { margin: 0; font-size: .95rem; font-weight: 800; color: var(--ink); text-transform: uppercase; letter-spacing: .02em; }
    .inquiry-card--unread .inquiry-card-name { font-weight: 900; }
    .inquiry-card-subject { margin: .15rem 0 0; color: var(--muted); font-size: .85rem; overflow-wrap: anywhere; }
    .inquiry-card-meta { display: flex; flex-wrap: wrap; gap: 1rem 1.75rem; flex: 1 1 260px; }
    .inquiry-card-meta-item span { display: block; margin-bottom: .2rem; color: var(--muted); font-size: .66rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .inquiry-card-meta-item strong { font-size: .82rem; font-weight: 600; color: var(--ink); }
    .inquiry-card-badges { display: flex; flex-wrap: wrap; align-items: center; gap: .4rem; flex: 0 0 auto; }
    .inquiry-card-action { flex: 0 0 auto; margin-left: auto; }

    @media (max-width: 575.98px) {
        .inquiry-card { flex-direction: column; align-items: stretch; }
        /* flex-basis values above size *width* in row mode; reset to auto so they don't force
           tall empty space once flex-direction switches to column (basis would size *height*). */
        .inquiry-card-identity, .inquiry-card-meta { flex: 0 1 auto; }
        .inquiry-card-action { flex: 0 1 auto; margin-left: 0; }
        .inquiry-card-action .btn { width: 100%; }
    }
</style>
@endsection
