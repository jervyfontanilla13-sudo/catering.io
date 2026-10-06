@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header">
        <div>
            <h1 class="fw-bold mb-1">Reservations</h1>
            <p class="text-muted mb-0">Review customer information, then accept, cancel, or update each booking.</p>
        </div>
        <div class="page-actions">
            <a class="btn luxury-btn" href="{{ route('admin.reservations.create') }}">Add reservation</a>
            <a class="btn btn-outline-secondary" href="{{ route('admin.reservations') }}">Refresh bookings</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form id="reservation-filter-form" method="GET" action="{{ route('admin.reservations') }}" class="filter-bar" data-live-filter data-live-filter-target="#reservation-results">
        <div class="filter-field filter-field--wide">
            <label class="form-label">Customer</label>
            <input type="search" name="search" class="form-control" value="{{ old('search', $search ?? '') }}" placeholder="Name, email, phone, package, event, ID">
        </div>
        <div class="filter-field">
            <label class="form-label">From date</label>
            <input type="date" name="date_from" class="form-control" value="{{ old('date_from', $dateFrom ?? '') }}">
        </div>
        <div class="filter-field">
            <label class="form-label">To date</label>
            <input type="date" name="date_to" class="form-control" value="{{ old('date_to', $dateTo ?? '') }}">
        </div>
        <div class="filter-field">
            <label class="form-label">Reservation status</label>
            <select name="status" class="form-select">
                <option value="">All statuses</option>
                <option value="pending" @selected($status === 'pending')>Pending</option>
                <option value="confirmed" @selected($status === 'confirmed')>Accepted</option>
                <option value="completed" @selected($status === 'completed')>Completed</option>
                <option value="cancelled" @selected($status === 'cancelled')>Cancelled</option>
            </select>
        </div>
        <div class="filter-field">
            <label class="form-label">Payment status</label>
            <select name="payment_status" class="form-select">
                <option value="">All payments</option>
                <option value="Unpaid" @selected($paymentStatus === 'Unpaid')>No Payment</option>
                <option value="Partially Paid" @selected(in_array($paymentStatus, ['Partially Paid', 'Downpayment', 'Partial Payment'], true))>Partially Paid</option>
                <option value="Fully Paid" @selected($paymentStatus === 'Fully Paid')>Fully Paid</option>
                <option value="Partially Refunded" @selected($paymentStatus === 'Partially Refunded')>Partially Refunded</option>
                <option value="Fully Refunded" @selected($paymentStatus === 'Fully Refunded')>Fully Refunded</option>
            </select>
        </div>
        <div class="filter-field">
            @if($status || $paymentStatus || ($search ?? '') !== '' || ($dateFrom ?? '') !== '' || ($dateTo ?? '') !== '' || ($scope ?? '') !== '' || ($paymentDueSoon ?? false))
                <a href="{{ route('admin.reservations') }}" class="btn btn-outline-secondary w-100" data-live-filter-clear="#reservation-filter-form">Clear</a>
            @endif
        </div>
    </form>

    <div class="row g-3 mb-4 reservation-stats">
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--customers"><span>Customers</span><strong>{{ $customerCount }}</strong><small>Unique customer emails</small></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--pending"><span>Pending</span><strong>{{ $pendingCount }}</strong><small>Need a decision</small></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--accepted"><span>Accepted</span><strong>{{ $acceptedCount }}</strong><small>Confirmed bookings</small></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="reservation-stat reservation-stat--cancelled"><span>Cancelled</span><strong>{{ $cancelledCount }}</strong><small>Closed bookings</small></div>
        </div>
    </div>

    <div id="reservation-results" data-filter-count="{{ $matchingReservationCount }}" aria-live="polite">
    <div class="reservation-match-count text-muted small mb-2">{{ $matchingReservationCount }} matching reservation{{ $matchingReservationCount === 1 ? '' : 's' }}</div>
    <div class="reservation-table-container d-none d-md-block">
        <table class="table table-hover align-middle mb-0 reservations-table">
            <colgroup>
                <col style="width:11%"><col style="width:20%"><col style="width:14%"><col style="width:10%">
                <col style="width:7%"><col style="width:11%"><col style="width:13%"><col style="width:14%">
            </colgroup>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Event</th>
                    <th>Date</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th>Payment</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($reservations as $reservation)
                    @php($statusLabel = $reservation->status === 'confirmed' ? 'Accepted' : ucfirst($reservation->status))
                    @php($reservationFinancials = $reservation->financials())
                    <tr>
                        <td>
                            <a class="reservation-code" href="{{ route('admin.reservations.show', $reservation) }}">{{ $reservation->reservation_code ?? '—' }}</a>
                        </td>
                        <td>
                            <strong class="d-block">{{ $reservation->full_name }}</strong>
                            <a class="customer-contact customer-email" href="mailto:{{ $reservation->email }}" title="{{ $reservation->email }}">{{ $reservation->email }}</a>
                        </td>
                        <td>
                            <span class="d-block">{{ $reservation->event_type }}</span>
                            <small class="text-muted">{{ $reservation->venue }}</small>
                        </td>
                        <td>
                            <span class="d-block">{{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</span>
                            <small class="text-muted">Added: {{ $reservation->created_at->format('M j, Y') }}</small>
                        </td>
                        <td><strong>{{ $reservation->guest_count }}</strong></td>
                        <td><span class="status-badge status-badge--{{ $reservation->status }}">{{ $statusLabel }}</span></td>
                        <td><span class="status-badge status-badge--{{ \App\Models\Reservation::paymentStatusBadge($reservationFinancials['payment_status']) }}">{{ \App\Models\Reservation::paymentStatusLabel($reservationFinancials['payment_status']) }}</span></td>
                        <td>
                            <div class="reservation-row-actions">
                                @if($reservation->status === 'pending')
                                    <form method="POST" action="{{ route('admin.reservations.accept', $reservation) }}" data-confirm-message="Accept this reservation? The change will be saved immediately.">@csrf<button class="btn btn-sm btn-success" type="submit">Accept</button></form>
                                    <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" data-confirm-message="Cancel this reservation? The change will be saved immediately.">@csrf<button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
                                @endif
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.reservations.show', $reservation) }}">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No reservations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="reservation-mobile-list d-md-none">
        @forelse($reservations as $reservation)
            @php($statusLabel = $reservation->status === 'confirmed' ? 'Accepted' : ucfirst($reservation->status))
            <article class="reservation-mobile-card">
                <div class="d-flex justify-content-between gap-3">
                    <div>
                        <h5 class="mb-1">{{ $reservation->full_name }}</h5>
                        <span class="reservation-id-label">{{ $reservation->reservation_code ?? '—' }}</span>
                    </div>
                    <span class="status-badge status-badge--{{ $reservation->status }}">{{ $statusLabel }}</span>
                </div>
                <div class="mobile-event-info">
                    <div><span>Event</span><strong>{{ $reservation->event_type }}</strong></div>
                    <div>
                        <span>Date</span>
                        <strong>{{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}</strong>
                        <small class="d-block text-muted">Added: {{ $reservation->created_at->format('M j, Y') }}</small>
                    </div>
                    <div><span>Guests</span><strong>{{ $reservation->guest_count }}</strong></div>
                </div>
                <div class="reservation-actions">
                    @if($reservation->status === 'pending')
                        <form method="POST" action="{{ route('admin.reservations.accept', $reservation) }}" data-confirm-message="Accept this reservation? The change will be saved immediately.">@csrf<button class="btn btn-sm btn-success" type="submit">Accept</button></form>
                        <form method="POST" action="{{ route('admin.reservations.cancel', $reservation) }}" data-confirm-message="Cancel this reservation? The change will be saved immediately.">@csrf<button class="btn btn-sm btn-danger" type="submit">Cancel</button></form>
                    @endif
                    <a class="btn btn-sm luxury-btn" href="{{ route('admin.reservations.show', $reservation) }}">View reservation</a>
                </div>
            </article>
        @empty
            <div class="text-center text-muted py-4">No reservations found.</div>
        @endforelse
    </div>

    @include('admin.partials.pagination', ['paginator' => $reservations, 'resultLabel' => 'results', 'ariaLabel' => 'Reservation pagination'])
    </div>
</div>

<style>
    .reservation-stat { height: 100%; padding: 1rem 1.15rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); box-shadow: var(--shadow); }
    .reservation-stat span, .reservation-stat small { display: block; }
    .reservation-stat span, .mobile-event-info span { font-size: .68rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .reservation-stat strong { display: block; margin: .2rem 0; font-size: 1.7rem; line-height: 1.1; }
    .reservation-stat small, .mobile-event-info span { color: var(--muted); }
    .reservation-stat--customers { border-left: 4px solid #5279a8; }
    .reservation-stat--pending { border-left: 4px solid #d49b28; }
    .reservation-stat--accepted { border-left: 4px solid #21895b; }
    .reservation-stat--cancelled { border-left: 4px solid #c74e4e; }
    .reservation-match-count { margin-bottom: .5rem; }
    .customer-contact { display: block; color: var(--teal); font-size: .8rem; text-decoration: none; overflow-wrap: anywhere; }
    .reservation-mobile-card { margin-bottom: .75rem; padding: 1rem; border: 1px solid var(--line); border-radius: var(--radius); background: var(--surface); }
    .reservation-mobile-card h5 { font-size: 1rem; }
    .reservation-id-label { color: var(--muted); font-size: .76rem; font-weight: 700; }
    .mobile-event-info { display: grid; grid-template-columns: repeat(3, 1fr); gap: .5rem; padding: .8rem 0; }
    .mobile-event-info strong { display: block; margin-top: .15rem; font-size: .82rem; }
    .reservation-actions { display: flex; flex-wrap: wrap; justify-content: flex-end; gap: .5rem; padding-top: .75rem; border-top: 1px solid var(--line); }
    .reservation-actions form { display: flex; gap: .5rem; margin: 0; }

    /* Desktop table: readable type, compact controls, scrolls inside its card only when space runs out. */
    .reservations-table { min-width: 900px; table-layout: fixed; }
    .reservations-table thead th { white-space: normal; }
    .reservations-table th, .reservations-table td { padding-right: .45rem; padding-left: .45rem; }
    .reservations-table td { font-size: .78rem; vertical-align: middle; overflow-wrap: break-word; }
    .reservations-table td:first-child, .reservations-table th:first-child { padding-left: .85rem; }
    .reservation-code { font-size: .74rem; font-weight: 700; letter-spacing: .01em; white-space: nowrap; color: var(--teal-dark); text-decoration: none; }
    .reservation-code:hover { text-decoration: underline; }
    .reservations-table .btn-sm { padding-right: .5rem; padding-left: .5rem; }
    .reservations-table .customer-contact { font-size: .76rem; }
    .reservations-table .customer-email { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .reservation-row-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
    .reservation-row-actions form { margin: 0; }

    @media (max-width: 767.98px) {
        .reservation-actions { display: block; }
        .reservation-actions > * + * { margin-top: .6rem; }
        .reservation-actions form { display: flex; width: 100%; }
    }
</style>
@endsection
