@extends('layouts.admin')

@php
    $peso = fn ($amount) => '₱' . number_format((float) $amount, 2);
    $balanceCents = $financials['remaining_balance_cents'];
    $grossPaid = $financials['gross_paid_cents'] / 100;
    $totalRefunded = $financials['total_refunded_cents'] / 100;
    $netPaid = $financials['net_paid_cents'] / 100;
    $contractSet = $reservation->total_cost !== null;
    $fullyPaid = $financials['payment_status'] === 'Fully Paid';
    $lastPayment = $payments->last();
    $dueDate = $reservation->payment_due_date;
    $overdue = $dueDate && $dueDate->isPast() && ! $dueDate->isToday() && ($balanceCents ?? 0) > 0;
@endphp

@section('content')
<div class="content-card">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('admin.reservations') }}">← Back to reservations</a>
            <div class="page-kicker">Contract &amp; payment</div>
            <h1 class="fw-bold mb-1">Payments · {{ $reservation->reservation_code ?? '#' . $reservation->id }}</h1>
            <p class="text-muted mb-0">{{ $reservation->full_name }} · {{ $reservation->event_type }} on {{ \Carbon\Carbon::parse($reservation->event_date)->format('M j, Y') }}{{ $reservation->package ? ' · ' . $reservation->package->name : '' }}</p>
        </div>
        <div class="page-actions">
            <a class="btn btn-outline-secondary" href="{{ route('admin.reservations.payments.print', $reservation) }}" target="_blank" rel="noopener">View / print record</a>
        </div>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="summary-grid" aria-label="Payment summary">
        <div class="summary-item summary-item--accent"><span>Contract price</span><strong>{{ $contractSet ? $peso($reservation->total_cost) : 'Not set' }}</strong></div>
        <div class="summary-item"><span>Payment status</span><strong><span class="status-badge status-badge--{{ \App\Models\Reservation::paymentStatusBadge($reservation->payment_status) }}">{{ \App\Models\Reservation::paymentStatusLabel($reservation->payment_status) }}</span></strong></div>
        <div class="summary-item"><span>Gross paid</span><strong>{{ $peso($grossPaid) }}</strong></div>
        <div class="summary-item"><span>Total refunded</span><strong>{{ $peso($totalRefunded) }}</strong></div>
        <div class="summary-item"><span>Net paid</span><strong>{{ $peso($netPaid) }}</strong></div>
        <div class="summary-item {{ ($balanceCents ?? 0) > 0 ? 'summary-item--warn' : '' }}"><span>Remaining balance</span><strong>{{ $contractSet ? $peso($balanceCents / 100) : '—' }}</strong></div>
        <div class="summary-item"><span>Payment due date</span><strong>{{ $dueDate ? $dueDate->format('F j, Y') : 'Not set' }}</strong>@if($overdue)<span class="status-badge status-badge--cancelled mt-2">Overdue</span>@endif</div>
        <div class="summary-item"><span>Last payment method</span><strong>{{ $lastPayment?->payment_method ?? '—' }}</strong></div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-7">
            <section class="card h-100" aria-labelledby="record-payment-title">
                <div class="panel-header"><div><h5 class="fw-bold mb-1" id="record-payment-title">Record a payment</h5><p class="text-muted small">Log money received. Totals, balance, and status update automatically.</p></div></div>
                @if(! $contractSet)
                    <div class="alert alert-warning mb-0">Set the contract price first, then record payments against it.</div>
                @elseif($fullyPaid)
                    <div class="alert alert-success mb-0">This booking is fully paid. Edit a payment below to make changes.</div>
                @else
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.reservations.payments.store', $reservation) }}" data-submit-once data-confirm-message="Record this payment? The balance and status will be recalculated.">
                        @csrf
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_date">Payment date</label>
                                <input class="form-control @error('payment_date') is-invalid @enderror" type="date" id="payment_date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_type">Payment type</label>
                                <select class="form-select @error('payment_type') is-invalid @enderror" id="payment_type" name="payment_type" required>
                                    @foreach($types as $type)<option value="{{ $type }}" @selected(old('payment_type', $suggestedType) === $type)>{{ $type }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="amount">Amount (₱)</label>
                                <input class="form-control @error('amount') is-invalid @enderror" type="number" id="amount" name="amount" value="{{ old('amount') }}" min="0.01" max="{{ number_format($balanceCents / 100, 2, '.', '') }}" step="0.01" inputmode="decimal" placeholder="0.00" required>
                                <div class="form-text">Remaining balance: {{ $peso($balanceCents / 100) }}</div>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label" for="payment_method">Payment method</label>
                                <select class="form-select @error('payment_method') is-invalid @enderror" id="payment_method" name="payment_method" required>
                                    @foreach($methods as $method)<option value="{{ $method }}" @selected(old('payment_method', 'Cash') === $method)>{{ $method }}</option>@endforeach
                                </select>
                            </div>
                            <div class="col-12 receipt-upload-field">
                                <label class="form-label" for="receipt_image">Official Receipt Image <span class="text-muted fw-normal">(optional)</span></label>
                                <input class="form-control @error('receipt_image') is-invalid @enderror" type="file" id="receipt_image" name="receipt_image" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">Upload proof of payment. JPG, PNG, or WEBP; maximum 5MB.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="notes">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Reference number, who paid, etc.">{{ old('notes') }}</textarea>
                            </div>
                        </div>
                        <div class="d-flex justify-content-end mt-3"><button class="btn luxury-btn" type="submit">Save Payment</button></div>
                    </form>
                @endif
            </section>
        </div>
        <div class="col-lg-5">
            <section class="card h-100" aria-labelledby="contract-title">
                <div class="panel-header"><div><h5 class="fw-bold mb-1" id="contract-title">Contract &amp; due date</h5><p class="text-muted small">The due date is separate from the event date.</p></div></div>
                <form method="POST" action="{{ route('admin.reservations.payments.details', $reservation) }}" data-submit-once data-confirm-message="Save the contract price and payment due date?">
                    @csrf @method('PATCH')
                    <div class="mb-3">
                        <label class="form-label" for="total_cost">Contract price (₱)</label>
                        <input class="form-control @error('total_cost') is-invalid @enderror" type="number" id="total_cost" name="total_cost" value="{{ old('total_cost', $reservation->total_cost) }}" min="{{ number_format($netPaid, 2, '.', '') }}" step="0.01" inputmode="decimal" required>
                        @if($netPaid > 0)<div class="form-text">Cannot be lower than the {{ $peso($netPaid) }} net amount paid.</div>@endif
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="payment_due_date">Payment due date</label>
                        <input class="form-control" type="date" id="payment_due_date" name="payment_due_date" value="{{ old('payment_due_date', $dueDate?->toDateString()) }}">
                        <div class="form-text">Event date: {{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</div>
                    </div>
                    <div class="d-flex justify-content-end"><button class="btn luxury-btn" type="submit">Save</button></div>
                </form>
            </section>
        </div>
    </div>

    <section class="card mb-4 refund-panel" aria-labelledby="refund-payment-title">
        <div class="panel-header">
            <div>
                <h5 class="fw-bold mb-1" id="refund-payment-title">Refund payment</h5>
                <p class="text-muted small mb-0">Record money returned to the customer. The original payments remain in the history.</p>
            </div>
        </div>
        @if($netPaid <= 0)
            <div class="alert alert-info mb-0">A refund can be recorded after a payment has been received.</div>
        @else
            <form method="POST" id="refund-payment-form" action="{{ route('admin.reservations.refunds.store', $reservation) }}" data-submit-once data-confirm-message="Confirm this refund?" data-refund-confirm>
                @csrf
                <input type="hidden" name="request_key" value="{{ $refundRequestKey }}">
                <div class="row g-3">
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_amount">Amount (₱)</label>
                        <input class="form-control @error('refund_amount') is-invalid @enderror" type="number" id="refund_amount" name="refund_amount" value="{{ old('refund_amount') }}" min="0.01" max="{{ number_format($netPaid, 2, '.', '') }}" step="0.01" inputmode="decimal" placeholder="0.00" required>
                        <div class="form-text">Maximum refundable: {{ $peso($netPaid) }}</div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_method">Refund method</label>
                        <select class="form-select @error('refund_method') is-invalid @enderror" id="refund_method" name="refund_method" required>
                            @foreach($methods as $method)<option value="{{ $method }}" @selected(old('refund_method', 'Cash') === $method)>{{ $method }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_date">Refund date</label>
                        <input class="form-control @error('refund_date') is-invalid @enderror" type="date" id="refund_date" name="refund_date" value="{{ old('refund_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <label class="form-label" for="refund_reason">Reason / notes <span class="text-muted fw-normal">(optional)</span></label>
                        <input class="form-control @error('reason') is-invalid @enderror" type="text" id="refund_reason" name="reason" value="{{ old('reason') }}" maxlength="1000" placeholder="Reason for refund">
                    </div>
                </div>
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mt-3">
                    <span class="small text-muted">Current net paid: {{ $peso($netPaid) }}</span>
                    <button class="btn btn-outline-danger" type="submit">Process Refund</button>
                </div>
            </form>
        @endif
    </section>

    <section aria-labelledby="history-title">
        <h5 class="fw-bold mb-2" id="history-title">Payment history</h5>
        <div class="table-responsive">
            <table class="table align-middle payment-history">
                <thead>
            <tr><th>Date</th><th>Payment type</th><th class="text-end">Amount</th><th>Method</th><th>Receipt</th><th>Notes</th><th>Recorded by</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    @forelse($transactions as $transaction)
                        <tr>
                            <td class="text-nowrap">{{ $transaction->date->format('m/d/Y') }}</td>
                            <td>
                                @if($transaction->kind === 'refund')
                                    <span class="refund-type">Refund</span>
                                @else
                                    {{ $transaction->type }}
                                @endif
                            </td>
                            <td class="text-end money fw-bold {{ $transaction->kind === 'refund' ? 'refund-amount' : '' }}">{{ $transaction->kind === 'refund' ? '−' : '' }}{{ $peso($transaction->amount) }}</td>
                            <td>{{ $transaction->method }}</td>
                            <td>
                                @if($transaction->kind === 'payment' && $transaction->payment->receipt_image_path)
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-view-receipt
                                        data-receipt-url="{{ route('admin.reservations.payments.receipt', [$reservation, $transaction->payment]) }}"
                                        aria-label="View receipt for payment {{ $peso($transaction->amount) }}">View Receipt</button>
                                @elseif($transaction->kind === 'payment')
                                    <span class="text-muted">No receipt</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="text-muted payment-notes">{{ $transaction->notes ?: '—' }}</td>
                            <td class="text-muted">{{ $transaction->recorded_by_name ?: '—' }}<br><small>{{ $transaction->created_at?->format('M j, Y g:i A') }}</small></td>
                            <td class="text-end">
                                @if($transaction->kind === 'payment')
                                    <div class="table-actions">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-edit-payment
                                            data-action="{{ route('admin.reservations.payments.update', [$reservation, $transaction->payment]) }}"
                                            data-id="{{ $transaction->payment->id }}"
                                            data-date="{{ $transaction->payment->payment_date->toDateString() }}"
                                            data-type="{{ $transaction->payment->payment_type }}"
                                            data-amount="{{ number_format($transaction->payment->amount, 2, '.', '') }}"
                                            data-method="{{ $transaction->payment->payment_method }}"
                                            data-notes="{{ $transaction->payment->notes }}"
                                            data-receipt-url="{{ $transaction->payment->receipt_image_path ? route('admin.reservations.payments.receipt', [$reservation, $transaction->payment]) : '' }}">Edit</button>
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No payments recorded yet.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr><th colspan="2">Gross paid</th><th class="text-end money">{{ $peso($grossPaid) }}</th><th colspan="5"></th></tr>
                    <tr><th colspan="2">Total refunded</th><th class="text-end money refund-amount">−{{ $peso($totalRefunded) }}</th><th colspan="5"></th></tr>
                    <tr><th colspan="2">Net paid</th><th class="text-end money">{{ $peso($netPaid) }}</th><th colspan="5"></th></tr>
                    <tr><th colspan="2">Balance</th><th class="text-end money">{{ $contractSet ? $peso($balanceCents / 100) : '—' }}</th><th colspan="5"></th></tr>
                </tfoot>
            </table>
        </div>
    </section>
</div>

<dialog id="payment-receipt-dialog" aria-labelledby="payment-receipt-title" class="payment-receipt-dialog">
    <div class="receipt-dialog-header">
        <h2 id="payment-receipt-title" class="h5 mb-0">Official receipt</h2>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-close-receipt>Close</button>
    </div>
    <div class="receipt-dialog-body">
        <img id="payment-receipt-image" alt="Official receipt image" hidden>
        <p id="payment-receipt-error" class="alert alert-warning mb-0" hidden>The receipt image could not be displayed.</p>
    </div>
</dialog>

<dialog id="edit-payment-dialog" aria-labelledby="edit-payment-title">
    <h2 id="edit-payment-title" class="h5 mb-3">Edit payment</h2>
    @if($errors->editPayment->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0 ps-3">@foreach($errors->editPayment->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" enctype="multipart/form-data" id="edit-payment-form" action="" data-password-confirm data-password-message="Confirm your administrator password to edit this payment." data-submit-once data-confirm-message="Save changes to this payment? The balance will be recalculated.">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-sm-6"><label class="form-label" for="edit_payment_date">Payment date</label><input class="form-control" type="date" id="edit_payment_date" name="payment_date" max="{{ now()->toDateString() }}" required></div>
            <div class="col-sm-6"><label class="form-label" for="edit_payment_type">Payment type</label><select class="form-select" id="edit_payment_type" name="payment_type" required>@foreach($types as $type)<option value="{{ $type }}">{{ $type }}</option>@endforeach</select></div>
            <div class="col-sm-6"><label class="form-label" for="edit_amount">Amount (₱)</label><input class="form-control" type="number" id="edit_amount" name="amount" min="0.01" step="0.01" inputmode="decimal" required></div>
            <div class="col-sm-6"><label class="form-label" for="edit_payment_method">Payment method</label><select class="form-select" id="edit_payment_method" name="payment_method" required>@foreach($methods as $method)<option value="{{ $method }}">{{ $method }}</option>@endforeach</select></div>
            <div class="col-12 receipt-upload-field">
                <label class="form-label" for="edit_receipt_image">Official Receipt Image <span class="text-muted fw-normal">(optional)</span></label>
                <div class="current-receipt mb-2"><span id="edit-receipt-empty" class="text-muted small">No receipt uploaded.</span><button id="edit-view-receipt" type="button" class="btn btn-sm btn-outline-secondary" data-view-receipt hidden>View current receipt</button></div>
                <input class="form-control" type="file" id="edit_receipt_image" name="receipt_image" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">Choose a new JPG, PNG, or WEBP image (maximum 5MB) to replace the current receipt.</div>
            </div>
            <div class="col-12"><label class="form-label" for="edit_notes">Notes <span class="text-muted fw-normal">(optional)</span></label><textarea class="form-control" id="edit_notes" name="notes" rows="2" maxlength="1000"></textarea></div>
        </div>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" class="btn btn-outline-secondary" data-close-dialog>Cancel</button>
            <button type="submit" class="btn luxury-btn">Save changes</button>
        </div>
    </form>
</dialog>

<style>
    .payment-history { min-width: 900px; }
    .payment-history tfoot th { border-top: 1px solid var(--line); background: #f7f9fa; font-size: .8rem; }
    body.dark-mode .payment-history tfoot th { background: #223641; }
    .payment-notes { max-width: 260px; overflow-wrap: anywhere; }
    .summary-item .status-badge { font-family: "DM Sans", sans-serif; }
    .refund-panel { border-color: rgba(185, 71, 71, .28); }
    .refund-type, .refund-amount { color: var(--danger) !important; }
    body.dark-mode .refund-type, body.dark-mode .refund-amount { color: #ffb0b0 !important; }
    .receipt-upload-field { min-width: 0; }
    .current-receipt { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
    .payment-receipt-dialog { width: min(900px, calc(100vw - 2rem)); max-width: none; max-height: calc(100dvh - 2rem); padding: 0; overflow: hidden; border: 1px solid var(--line); background: var(--surface); color: var(--ink); }
    .payment-receipt-dialog::backdrop { background: rgba(16, 20, 24, .72); }
    .receipt-dialog-header { display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 1rem; border-bottom: 1px solid var(--line); }
    .receipt-dialog-body { display: grid; place-items: center; min-height: 140px; max-height: calc(100dvh - 6rem); overflow: auto; padding: .75rem; }
    .receipt-dialog-body img { display: block; width: auto; height: auto; max-width: 100%; max-height: calc(100dvh - 8rem); object-fit: contain; }
    .receipt-dialog-body img[hidden], #payment-receipt-error[hidden] { display: none; }
    #edit-payment-dialog { width: min(640px, calc(100vw - 2rem)); max-height: calc(100dvh - 2rem); overflow-y: auto; }
    @media (max-width: 575px) {
        .payment-receipt-dialog { width: calc(100vw - 1rem); max-height: calc(100dvh - 1rem); }
        .receipt-dialog-header { padding: .65rem .75rem; }
        .receipt-dialog-body { max-height: calc(100dvh - 5rem); padding: .5rem; }
        .receipt-dialog-body img { max-height: calc(100dvh - 7rem); }
    }
</style>
<script>
(() => {
    const form = document.getElementById('refund-payment-form');
    if (!form) return;

    const amount = form.querySelector('[name="refund_amount"]');
    const maximum = Number(amount.max);
    const peso = (value) => `₱${Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const updateConfirmation = () => {
        const refund = Number(amount.value);
        form.dataset.confirmMessage = [
            'Confirm Refund',
            `Refund amount: ${peso(refund)}`,
            `Current net paid: ${peso(maximum)}`,
            `Remaining after refund: ${peso(Math.max(0, maximum - refund))}`,
        ].join('\n');
    };

    amount.addEventListener('input', updateConfirmation);
    updateConfirmation();
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('payment-receipt-dialog');
    const image = document.getElementById('payment-receipt-image');
    const error = document.getElementById('payment-receipt-error');
    if (!dialog || !image || !error) return;

    const open = (url) => {
        if (!url) return;
        image.hidden = true;
        error.hidden = true;
        image.src = url;
        dialog.showModal();
    };

    document.querySelectorAll('[data-view-receipt]').forEach((button) => {
        button.addEventListener('click', () => open(button.dataset.receiptUrl));
    });
    image.addEventListener('load', () => {
        image.hidden = false;
        error.hidden = true;
    });
    image.addEventListener('error', () => {
        image.hidden = true;
        error.hidden = false;
    });
    dialog.querySelector('[data-close-receipt]').addEventListener('click', () => dialog.close());
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && dialog.open) {
            event.preventDefault();
            dialog.close();
        }
    });
    dialog.addEventListener('close', () => {
        image.removeAttribute('src');
        image.hidden = true;
        error.hidden = true;
    });
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close();
    });
})();
</script>
<script>
(() => {
    const dialog = document.getElementById('edit-payment-dialog');
    const form = document.getElementById('edit-payment-form');
    const currentReceiptButton = document.getElementById('edit-view-receipt');
    const currentReceiptEmpty = document.getElementById('edit-receipt-empty');
    const fields = {
        date: document.getElementById('edit_payment_date'),
        type: document.getElementById('edit_payment_type'),
        amount: document.getElementById('edit_amount'),
        method: document.getElementById('edit_payment_method'),
        notes: document.getElementById('edit_notes'),
    };
    const open = (values) => {
        form.action = values.action;
        fields.date.value = values.date;
        fields.type.value = values.type;
        fields.amount.value = values.amount;
        fields.method.value = values.method;
        fields.notes.value = values.notes || '';
        currentReceiptButton.dataset.receiptUrl = values.receiptUrl || '';
        currentReceiptButton.hidden = ! values.receiptUrl;
        currentReceiptEmpty.hidden = Boolean(values.receiptUrl);
        dialog.showModal();
        fields.amount.focus();
    };

    document.querySelectorAll('[data-edit-payment]').forEach((button) => {
        button.addEventListener('click', () => open(button.dataset));
    });
    dialog.querySelector('[data-close-dialog]').addEventListener('click', () => dialog.close());

    // A rejected edit comes back with its input; reopen the dialog so the admin can correct it.
    @if(session('editing_payment'))
        const failed = document.querySelector('[data-edit-payment][data-id="{{ session('editing_payment') }}"]');
        if (failed) open({
            ...failed.dataset,
            date: @json(old('payment_date')),
            type: @json(old('payment_type')),
            amount: @json(old('amount')),
            method: @json(old('payment_method')),
            notes: @json(old('notes')),
        });
    @endif
})();
</script>
@endsection
