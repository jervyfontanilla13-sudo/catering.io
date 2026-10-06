@extends('layouts.admin')

@section('content')
@php
$peso = fn ($amount) => '&#8369;' . number_format($amount, 2);
$chartValues = [$totals['paid'], $totals['refunded'], $totals['net'], $totals['outstanding']];
@endphp
<div class="content-card p-4">
    <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
        <div>
            <h1 class="fw-bold mb-1">Analytics</h1>
            <p class="text-muted mb-0">Understand reservations, payments, refunds, and revenue at a glance.</p>
        </div>

        <form method="GET" action="{{ route('admin.analytics') }}" class="ms-md-auto">
            <div class="d-flex flex-column flex-sm-row align-items-sm-end gap-2">
                <div>
                    <label for="analytics-range" class="form-label small text-uppercase text-muted mb-1">Date Range</label>
                    <select id="analytics-range" name="range" class="form-select form-select-sm">
                        <option value="all_time" {{ $selectedRange === 'all_time' ? 'selected' : '' }}>All Time</option>
                        <option value="today" {{ $selectedRange === 'today' ? 'selected' : '' }}>Today</option>
                        <option value="this_week" {{ $selectedRange === 'this_week' ? 'selected' : '' }}>This Week</option>
                        <option value="this_month" {{ $selectedRange === 'this_month' ? 'selected' : '' }}>This Month</option>
                        <option value="last_month" {{ $selectedRange === 'last_month' ? 'selected' : '' }}>Last Month</option>
                        <option value="this_year" {{ $selectedRange === 'this_year' ? 'selected' : '' }}>This Year</option>
                        <option value="custom" {{ $selectedRange === 'custom' ? 'selected' : '' }}>Custom Range</option>
                    </select>
                </div>

                <div id="analytics-custom-range" @if($selectedRange !== 'custom') style="display:none;" @endif>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <div>
                            <label for="analytics-from" class="form-label small text-uppercase text-muted mb-1">From</label>
                            <input id="analytics-from" name="from" type="date" class="form-control form-control-sm" value="{{ $dateFrom ?? '' }}">
                        </div>
                        <div>
                            <label for="analytics-to" class="form-label small text-uppercase text-muted mb-1">To</label>
                            <input id="analytics-to" name="to" type="date" class="form-control form-control-sm" value="{{ $dateTo ?? '' }}">
                        </div>
                    </div>
                </div>

                <button id="analytics-apply" type="submit" class="btn btn-sm btn-primary" @disabled($selectedRange === 'custom' && ! $isCustomRangeValid)>Apply</button>
            </div>
        </form>
    </div>

    @if($errors->any())
        <div class="alert alert-warning mt-3 mb-4" role="alert">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="row g-3 mb-4">
        @foreach([
            ['Total Reservations', $totals['reservations'], 'All bookings'],
            ['Accepted', $statusCounts['confirmed'], 'Confirmed bookings'],
            ['Pending', $statusCounts['pending'], 'Waiting for review'],
            ['Cancelled', $statusCounts['cancelled'], 'Cancelled bookings'],
        ] as [$label, $value, $hint])
            <div class="col-lg-3 col-sm-6">
                <div class="stat-card p-4 h-100">
                    <div class="badge-soft mb-2">{{ $label }}</div>
                    <h3 class="fw-bold">{{ $value }}</h3>
                    <p class="mb-0 text-muted">{{ $hint }}</p>
                </div>
            </div>
        @endforeach
        @foreach([
            ['Total Revenue', $totals['paid'], 'Payments received'],
            ['Unpaid Balance', $totals['outstanding'], 'Still to be collected'],
            ['Total Refunds', $totals['refunded'], $totals['refunded_reservations'] . ' reservation(s) refunded'],
            ['Net Revenue', $totals['net'], 'Payments minus refunds'],
        ] as [$label, $value, $hint])
            <div class="col-lg-3 col-sm-6">
                <div class="stat-card p-4 h-100">
                    <div class="badge-soft mb-2">{{ $label }}</div>
                    <h3 class="fw-bold" style="font-size:1.5rem">{!! $peso($value) !!}</h3>
                    <p class="mb-0 text-muted">{{ $hint }}</p>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-7">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Revenue Overview</h4>
                <canvas id="revenueChart" style="max-height:260px;"></canvas>
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Reservation Status</h4>
                @php $maxStatus = max(1, $statusCounts->max()); @endphp
                @foreach(['pending' => 'Pending', 'confirmed' => 'Accepted', 'completed' => 'Completed', 'cancelled' => 'Cancelled'] as $key => $label)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between"><span>{{ $label }}</span><strong>{{ $statusCounts[$key] }}</strong></div>
                        <div class="progress" style="height:10px"><div class="progress-bar" style="width:{{ $statusCounts[$key] / $maxStatus * 100 }}%;background:#b66545"></div></div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <h4 class="fw-semibold mb-3">Popular Packages</h4>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Package</th><th class="text-end">Reservations</th><th class="text-end">Revenue</th></tr></thead>
                <tbody>
                    @forelse($topPackages as $package)
                        <tr><td>{{ $package->name }}</td><td class="text-end">{{ $package->total }}</td><td class="text-end">{!! $peso($package->revenue) !!}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No package bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card p-4 mb-4">
        <h4 class="fw-semibold mb-3">Monthly Overview</h4>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Month</th><th class="text-end">Reservations</th><th class="text-end">Paid</th><th class="text-end">Refunds</th><th class="text-end">Net Revenue</th></tr></thead>
                <tbody>
                    @foreach($monthly as $row)
                        <tr>
                            <td>{{ $row->label }}</td>
                            <td class="text-end">{{ $row->reservations }}</td>
                            <td class="text-end">{!! $peso($row->paid) !!}</td>
                            <td class="text-end">{!! $peso($row->refunded) !!}</td>
                            <td class="text-end"><strong>{!! $peso($row->net) !!}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Latest Reservations</h4>
                @forelse($recentReservations as $reservation)
                    <div class="mb-2"><strong>{{ $reservation->full_name }}</strong><br>
                        <small class="text-muted">{{ ucfirst($reservation->status) }} &middot; {{ $reservation->created_at->format('M d, Y') }}</small></div>
                @empty
                    <p class="text-muted mb-0">No reservations yet.</p>
                @endforelse
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Latest Payments</h4>
                @forelse($recentPayments as $payment)
                    <div class="mb-2"><strong>{{ $payment->reservation?->full_name ?? 'Deleted reservation' }}</strong><br>
                        <small class="text-muted">Payment received &middot; {!! $peso($payment->amount) !!} &middot; {{ $payment->payment_date->format('M d, Y') }}</small></div>
                @empty
                    <p class="text-muted mb-0">No payments yet.</p>
                @endforelse
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card p-4 h-100">
                <h4 class="fw-semibold mb-3">Latest Refunds</h4>
                @forelse($recentRefunds as $refund)
                    <div class="mb-2"><strong>{{ $refund->reservation?->full_name ?? 'Deleted reservation' }}</strong><br>
                        <small class="text-muted">Refund issued &middot; {!! $peso($refund->amount) !!} &middot; {{ $refund->refund_date->format('M d, Y') }}</small></div>
                @empty
                    <p class="text-muted mb-0">No refunds yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

<script>
const rangeSelect = document.getElementById('analytics-range');
const customRange = document.getElementById('analytics-custom-range');
const applyButton = document.getElementById('analytics-apply');
const fromInput = document.getElementById('analytics-from');
const toInput = document.getElementById('analytics-to');

function updateCustomRangeState() {
    const isCustom = rangeSelect.value === 'custom';
    customRange.style.display = isCustom ? 'block' : 'none';

    if (!isCustom) {
        applyButton.disabled = false;
        return;
    }

    const hasFrom = !!fromInput.value;
    const hasTo = !!toInput.value;
    const validRange = hasFrom && hasTo && new Date(fromInput.value + 'T00:00:00') <= new Date(toInput.value + 'T00:00:00');
    applyButton.disabled = !validRange;
}

rangeSelect.addEventListener('change', updateCustomRangeState);
[fromInput, toInput].forEach((field) => field.addEventListener('input', updateCustomRangeState));
updateCustomRangeState();
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartText=getComputedStyle(document.body).color;
new Chart(document.getElementById('revenueChart'),{
    type:'bar',
    data:{labels:['Payments','Refunds','Net Revenue','Unpaid'],datasets:[{data:@json($chartValues),backgroundColor:['#b66545','#66727a','#6d3024','#c7984b'],borderRadius:5}]},
    options:{responsive:true,maintainAspectRatio:true,plugins:{legend:{display:false}},scales:{x:{ticks:{color:chartText}},y:{beginAtZero:true,ticks:{color:chartText}}}}
});
</script>
@endsection
