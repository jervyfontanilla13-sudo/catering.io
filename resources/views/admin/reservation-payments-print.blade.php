@php
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $balanceCents = $financials['remaining_balance_cents'];
    $grossPaid = $financials['gross_paid_cents'] / 100;
    $totalRefunded = $financials['total_refunded_cents'] / 100;
    $netPaid = $financials['net_paid_cents'] / 100;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment record · {{ $reservation->reservation_code ?? '#' . $reservation->id }}</title>
    <link rel="icon" type="image/png" href="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink: #202c36; --muted: #71808b; --line: #dfe7eb; --teal-dark: #087168; --navy: #172633; }
        * { box-sizing: border-box; }
        body { margin: 0; padding: 2rem 1rem; background: #eaf0f3; color: var(--ink); font-family: "DM Sans", sans-serif; font-size: .9rem; }
        .sheet { max-width: 820px; margin: 0 auto; padding: 2rem; border: 1px solid var(--line); border-radius: 12px; background: #fff; }
        .toolbar { display: flex; justify-content: flex-end; gap: .5rem; max-width: 820px; margin: 0 auto 1rem; }
        .toolbar button, .toolbar a { display: inline-flex; min-height: 38px; align-items: center; padding: .45rem .95rem; border: 1px solid var(--navy); border-radius: 8px; background: var(--navy); color: #fff; font: 700 .82rem "DM Sans", sans-serif; text-decoration: none; cursor: pointer; }
        .toolbar a { border-color: #d5dfe4; background: #fff; color: #53636e; }
        header { display: flex; justify-content: space-between; gap: 1rem; padding-bottom: 1.2rem; border-bottom: 2px solid var(--navy); }
        header img { width: 54px; height: 54px; object-fit: contain; }
        h1 { margin: 0; font-family: Manrope, sans-serif; font-size: 1.35rem; }
        .kicker { color: var(--teal-dark); font-size: .68rem; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
        .meta { margin-top: 1.2rem; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .5rem 2rem; }
        .meta div, .totals div { display: flex; justify-content: space-between; gap: 1rem; padding: .35rem 0; border-bottom: 1px dashed var(--line); }
        .meta span, .totals span { color: var(--muted); }
        h2 { margin: 1.6rem 0 .6rem; font-family: Manrope, sans-serif; font-size: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: .55rem .5rem; border-bottom: 1px solid var(--line); text-align: left; vertical-align: top; }
        th { color: var(--muted); font-size: .68rem; letter-spacing: .07em; text-transform: uppercase; }
        .num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .refund-row, .refund-amount { color: #a94242; }
        .totals { width: min(340px, 100%); margin: 1rem 0 0 auto; }
        .totals div strong { font-variant-numeric: tabular-nums; }
        .totals .grand { border-bottom: 2px solid var(--navy); font-size: 1rem; }
        footer { margin-top: 2rem; color: var(--muted); font-size: .75rem; }
        @media (max-width: 600px) { .meta { grid-template-columns: 1fr; } .sheet { padding: 1.2rem; } }
        @media print { body { padding: 0; background: #fff; } .toolbar { display: none; } .sheet { border: 0; padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('admin.reservations.payments', $reservation) }}">Back to payments</a>
        <button type="button" onclick="window.print()">Print</button>
    </div>
    <main class="sheet">
        <header>
            <div>
                <div class="kicker">Payment record</div>
                <h1>3YOS Catering Services &amp; Party Needs</h1>
                <div>Reservation {{ $reservation->reservation_code ?? '#' . $reservation->id }}</div>
            </div>
            <img src="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}" alt="">
        </header>

        <section class="meta">
            <div><span>Customer</span><strong>{{ $reservation->full_name }}</strong></div>
            <div><span>Event</span><strong>{{ $reservation->event_type }}</strong></div>
            <div><span>Event date</span><strong>{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</strong></div>
            <div><span>Package</span><strong>{{ $reservation->package?->name ?? 'Custom package' }}</strong></div>
            <div><span>Payment status</span><strong>{{ \App\Models\Reservation::paymentStatusLabel($reservation->payment_status) }}</strong></div>
            <div><span>Payment due date</span><strong>{{ $reservation->payment_due_date?->format('F j, Y') ?? 'Not set' }}</strong></div>
        </section>

        <h2>Payment history</h2>
        <table>
            <thead><tr><th>Date</th><th>Payment type</th><th>Method</th><th>Notes</th><th class="num">Amount</th></tr></thead>
            <tbody>
                @forelse($transactions as $transaction)
                        <tr class="{{ $transaction->kind === 'refund' ? 'refund-row' : '' }}"><td>{{ $transaction->date->format('m/d/Y') }}</td><td>{{ $transaction->type }}</td><td>{{ $transaction->method }}</td><td>{{ $transaction->notes }}</td><td class="num">{{ $transaction->kind === 'refund' ? '−' : '' }}{{ $peso($transaction->amount) }}</td></tr>
                @empty
                        <tr><td colspan="5">No payments or refunds recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        <section class="totals">
            <div><span>Contract price</span><strong>{{ $reservation->total_cost !== null ? $peso($reservation->total_cost) : 'Not set' }}</strong></div>
            <div><span>Gross paid</span><strong>{{ $peso($grossPaid) }}</strong></div>
            <div><span>Total refunded</span><strong class="refund-amount">−{{ $peso($totalRefunded) }}</strong></div>
            <div><span>Net paid</span><strong>{{ $peso($netPaid) }}</strong></div>
            <div class="grand"><span>Remaining balance</span><strong>{{ $balanceCents !== null ? $peso($balanceCents / 100) : '—' }}</strong></div>
        </section>

        <footer>Generated {{ now()->format('F j, Y g:i A') }} by {{ session('admin_name', 'Administrator') }}. For record-keeping only; not an official receipt.</footer>
    </main>
</body>
</html>
