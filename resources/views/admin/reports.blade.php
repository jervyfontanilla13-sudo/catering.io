@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <div class="page-kicker mb-1">Operations &amp; insights</div>
        <h1 class="fw-bold mb-1">Reports</h1>
        <p class="text-muted mb-0">Live booking activity and financial summaries by calendar period.</p>
    </div></div>
    <p id="report-download-error" class="alert alert-danger py-2" role="alert" hidden>Unable to generate the report. Please try again.</p>

    <div class="row g-4 report-grid">
        @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $key => $label)
            @php
                $summary = ${$key};
                $periodStart = $summary['period_start'];
                $periodEnd = $summary['period_end'];
                $periodDescription = match ($key) {
                    'daily' => 'Today · '.$periodStart->format('M j, Y'),
                    'weekly' => 'This Week · '.$periodStart->format('M j').' – '.$periodEnd->format('M j, Y'),
                    'monthly' => 'This Month · '.$periodStart->format('F Y'),
                    'yearly' => 'This Year · '.$periodStart->format('Y'),
                };
            @endphp
            <div class="col-12 col-md-6">
                <article class="report-card h-100">
                    <header class="report-card-header">
                        <div>
                            <h2>{{ $label }} report</h2>
                            <p>{{ $periodDescription }}</p>
                        </div>
                        <span class="report-period-tag">{{ strtoupper($label) }}</span>
                    </header>

                    <dl class="report-metrics">
                        <div class="report-metric"><dt>Reservations</dt><dd>{{ number_format($summary['reservation_count']) }}</dd></div>
                        <div class="report-metric"><dt>Accepted</dt><dd>{{ number_format($summary['confirmed_reservations']) }}</dd></div>
                        <div class="report-metric"><dt>Completed</dt><dd>{{ number_format($summary['completed_events']) }}</dd></div>
                        <div class="report-metric"><dt>Cancelled</dt><dd>{{ number_format($summary['cancelled_reservations']) }}</dd></div>
                        <div class="report-metric"><dt>Inquiries</dt><dd>{{ number_format($summary['inquiry_count']) }}</dd></div>
                    </dl>

                    <details class="report-financials">
                        <summary>Payments, refunds &amp; balances</summary>
                        <section class="report-financial-scope" aria-labelledby="report-bookings-scope-{{ $key }}">
                            <h3 id="report-bookings-scope-{{ $key }}">Bookings created in period</h3>
                            <dl class="report-financial-metrics">
                                <div><dt>Contract value</dt><dd>₱{{ number_format($summary['contract_value'], 2) }}</dd></div>
                                <div><dt>Gross paid</dt><dd>₱{{ number_format($summary['gross_paid'], 2) }}</dd></div>
                                <div><dt>Net paid</dt><dd>₱{{ number_format($summary['net_paid'], 2) }}</dd></div>
                                <div><dt>Outstanding</dt><dd>₱{{ number_format($summary['outstanding_balance'], 2) }}</dd></div>
                            </dl>
                        </section>
                        <section class="report-financial-scope" aria-labelledby="report-transactions-scope-{{ $key }}">
                            <h3 id="report-transactions-scope-{{ $key }}">Transactions in period</h3>
                            <dl class="report-financial-metrics">
                                <div><dt>Gross payments</dt><dd>₱{{ number_format($summary['gross_payments_in_period'], 2) }}</dd></div>
                                <div><dt>Refunds</dt><dd class="report-refunded">{{ $summary['refunds_in_period'] > 0 ? '−' : '' }}₱{{ number_format($summary['refunds_in_period'], 2) }}</dd></div>
                                <div><dt>Net collected</dt><dd>₱{{ number_format($summary['net_collected_in_period'], 2) }}</dd></div>
                            </dl>
                        </section>
                    </details>

                    <footer class="report-downloads">
                        <a class="btn btn-report-primary" href="{{ route('admin.reports.export.excel', ['period' => $key]) }}" data-report-download aria-label="Download {{ strtolower($label) }} report as Excel">
                            <span data-download-label>Download Excel</span>
                        </a>
                        <a class="btn btn-report-secondary" href="{{ route('admin.reports.export', ['period' => $key]) }}" data-report-download aria-label="Download {{ strtolower($label) }} report as CSV">
                            <span data-download-label>Download CSV</span>
                        </a>
                    </footer>
                </article>
            </div>
        @endforeach
    </div>
</div>

<style>
.report-card{display:flex;flex-direction:column;min-height:410px;padding:1.35rem;border:1px solid var(--line);border-radius:10px;background:var(--surface);box-shadow:0 8px 22px rgba(24,54,62,.045)}
.report-card-header{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding-bottom:1.15rem;border-bottom:1px solid var(--line)}
.report-card-header h2{margin:0;color:var(--ink);font-size:1.15rem;font-weight:800;text-transform:uppercase}
.report-card-header p{margin:.35rem 0 0;color:var(--muted);font-size:.82rem}
.report-period-tag{flex:none;padding:.28rem .48rem;border-radius:4px;background:#e8f4f1;color:var(--teal-dark);font-size:.62rem;font-weight:800}
.report-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem 1.25rem;margin:1.2rem 0}
.report-metric{margin:0}
.report-metric dt{color:var(--muted);font-size:.65rem;font-weight:800;text-transform:uppercase}
.report-metric dd{margin:.18rem 0 0;color:var(--ink);font-size:1.28rem;font-weight:750;line-height:1.2}
.report-financials{margin-top:1rem;border:1px solid var(--line);border-radius:7px;background:var(--surface)}
.report-financials summary{padding:.7rem .8rem;color:var(--teal-dark);font-size:.78rem;font-weight:800;cursor:pointer}
.report-financial-scope{margin:0 .8rem;padding:.65rem 0;border-top:1px solid var(--line)}
.report-financial-scope h3{margin:0 0 .55rem;color:var(--teal-dark);font-size:.64rem;font-weight:800;letter-spacing:.045em;text-transform:uppercase}
.report-financial-scope + .report-financial-scope{margin-top:.1rem}
.report-financial-metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.65rem 1rem;margin:0}
.report-financial-metrics div{min-width:0}
.report-financial-metrics dt{color:var(--muted);font-size:.62rem;font-weight:800;text-transform:uppercase}
.report-financial-metrics dd{margin:.12rem 0 0;color:var(--ink);font-size:.9rem;font-weight:750;font-variant-numeric:tabular-nums;overflow-wrap:anywhere}
.report-financial-metrics .report-refunded{color:var(--danger)}
.report-downloads{display:grid;grid-template-columns:1fr 1fr;gap:.65rem;margin-top:1rem}
.btn-report-primary,.btn-report-secondary{display:inline-flex;min-height:42px;align-items:center;justify-content:center;border-radius:6px;font-size:.83rem;font-weight:700}
.btn-report-primary{border:1px solid var(--teal-dark);background:var(--teal-dark);color:#fff}
.btn-report-primary:hover{background:#155b58;color:#fff}
.btn-report-secondary{border:1px solid var(--line);background:transparent;color:var(--ink)}
.btn-report-secondary:hover{border-color:var(--teal);color:var(--teal-dark)}
.report-downloads .btn:focus-visible{outline:3px solid rgba(34,130,121,.35);outline-offset:2px}
.report-downloads .btn[aria-disabled="true"]{cursor:wait;opacity:.7}
body.dark-mode .report-card{background:var(--surface);box-shadow:none}
body.dark-mode .report-period-tag{background:#203f48;color:#9ae0d3}
body.dark-mode .report-financial-metrics .report-refunded{color:#ffb0b0}
body.dark-mode .btn-report-secondary{color:var(--ink)}
@media(max-width:575.98px){
    .report-card{min-height:0;padding:1rem}
    .report-metrics{gap:.85rem}
    .report-downloads{grid-template-columns:1fr}
}
</style>

<script>
(() => {
    const errorMessage = document.getElementById('report-download-error');

    document.querySelectorAll('[data-report-download]').forEach((link) => {
        link.addEventListener('click', async (event) => {
            event.preventDefault();
            if (link.dataset.busy === 'true') return;

            link.dataset.busy = 'true';
            link.setAttribute('aria-disabled', 'true');
            const label = link.querySelector('[data-download-label]');
            const originalLabel = label.textContent;
            label.textContent = 'Generating report...';
            errorMessage.hidden = true;

            try {
                const response = await fetch(link.href, {
                    headers: { 'Accept': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv' },
                });
                if (!response.ok) throw new Error('Report generation failed.');

                const blob = await response.blob();
                if (blob.size === 0) throw new Error('The generated report was empty.');

                const disposition = response.headers.get('Content-Disposition') || '';
                const filenameMatch = disposition.match(/filename\*=UTF-8''([^;]+)|filename="?([^";]+)"?/i);
                const filename = decodeURIComponent(filenameMatch?.[1] || filenameMatch?.[2] || link.href.split('/').pop());
                const downloadUrl = URL.createObjectURL(blob);
                const download = document.createElement('a');
                download.href = downloadUrl;
                download.download = filename;
                document.body.append(download);
                download.click();
                download.remove();
                setTimeout(() => URL.revokeObjectURL(downloadUrl), 1000);
            } catch (error) {
                errorMessage.hidden = false;
            } finally {
                label.textContent = originalLabel;
                link.removeAttribute('aria-disabled');
                delete link.dataset.busy;
            }
        });
    });
})();
</script>
@endsection
