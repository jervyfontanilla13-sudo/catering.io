@extends('layouts.admin')

@section('content')
<div class="content-card">
    <div class="page-header">
        <div>
            <div class="page-kicker">Security audit</div>
            <h1 class="fw-bold mb-1">Activity logs</h1>
            <p class="text-muted mb-0">Track which administrator accessed the panel and the actions they performed.</p>
        </div>
        <span id="activity-log-count" class="badge-soft">{{ $logs->total() }} records</span>
    </div>

    <div id="activity-log-loading" class="small text-muted mb-2" aria-live="polite" hidden>Loading activity logs...</div>
    
    <form id="activity-log-filter-form" class="filter-bar" method="GET" action="{{ route('admin.activity-logs') }}" data-live-filter data-live-filter-target="#activity-log-results" data-live-filter-count="#activity-log-count">
        <div class="filter-field filter-field--wide">
            <label class="form-label" for="actor">Administrator</label>
            <select id="actor" name="actor" class="form-select">
                <option value="">All administrators</option>
                @foreach($actors as $actor)
                    <option value="{{ $actor->actor_email }}" @selected(request('actor') === $actor->actor_email)>{{ $actor->actor_name }} — {{ $actor->actor_email }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-field filter-field--wide">
            <label class="form-label" for="search">Search actions</label>
            <input id="search" name="search" class="form-control" value="{{ request('search', request('action')) }}" placeholder="Search actions, e.g. signed in or updated">
        </div>
        <div class="filter-field">
            <label class="form-label" for="date_from">From date</label>
            <input id="date_from" name="date_from" type="date" class="form-control" value="{{ request('date_from') }}">
        </div>
        <div class="filter-field">
            <label class="form-label" for="date_to">Through date</label>
            <input id="date_to" name="date_to" type="date" class="form-control" value="{{ request('date_to') }}">
        </div>
        <div class="filter-field">
            @if(request('actor') || request('action') || request('date_from') || request('date_to'))
                <a href="{{ route('admin.activity-logs') }}" class="btn btn-outline-secondary w-100" data-live-filter-clear="#activity-log-filter-form">Clear</a>
            @endif
        </div>
    </form>
    
    <div id="activity-log-results" data-filter-count="{{ $logs->total() }}" aria-live="polite">
    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Administrator</th>
                    <th class="d-none d-md-table-cell">Action</th>
                    <th class="d-none d-lg-table-cell">When</th>
                    <th class="d-none d-xl-table-cell">Source</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>
                            <div class="actor-avatar">{{ str($log->actor_name ?: 'U')->substr(0, 1)->upper() }}</div>
                            <div class="d-inline-block align-middle ms-2">
                                <strong>{{ $log->actor_name ?: 'Unknown administrator' }}</strong>
                                <br><small class="text-muted">{{ $log->actor_email ?: 'Old log entry' }}</small>
                                <br><small class="d-md-none text-muted">{{ $log->action }}</small>
                            </div>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <strong>{{ $log->action }}</strong>
                            <br><small class="text-muted">{{ $log->description }}</small>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <strong>{{ $log->created_at?->format('M j, Y') ?? $log->activity_date }}</strong>
                            <br><small class="text-muted">{{ $log->created_at?->format('g:i A') ?? $log->activity_time }}</small>
                        </td>
                        <td class="d-none d-xl-table-cell">
                            <span class="method-label">{{ $log->method ?? 'LEGACY' }}</span>
                            <br><small class="text-muted">{{ $log->ip_address ?: 'Not recorded' }}</small>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-center text-muted py-5">No activity logs match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @include('admin.partials.pagination', ['paginator' => $logs, 'resultLabel' => 'results', 'ariaLabel' => 'Activity log pagination'])
    </div>
</div>

<style>
.actor-avatar{display:inline-grid;place-items:center;width:34px;height:34px;border-radius:50%;background:var(--mint);color:var(--teal-dark);font-size:.78rem;font-weight:800}
.method-label{display:inline-block;padding:.2rem .42rem;border:1px solid var(--line);border-radius:5px;color:var(--muted);font-size:.63rem;font-weight:800;letter-spacing:.06em}
</style>
@endsection
