<?php

namespace App\Services;

use App\Models\Inquiry;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use Carbon\Carbon;

class ReportService
{
    public function getSummary(string $period): array
    {
        $now = now(config('app.timezone'));
        [$start, $end] = match ($period) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'weekly' => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'yearly' => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            default => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
        };

        $reservations = Reservation::whereBetween('created_at', [$start, $end]);
        $reservationFinancials = (clone $reservations)->with('payments', 'refunds')->get();
        $financialService = app(ReservationFinancialService::class);
        $reservationTotals = $reservationFinancials->map(fn (Reservation $reservation) => $financialService->calculate($reservation));
        $paymentTransactions = ReservationPayment::whereDate('payment_date', '>=', $start->toDateString())
            ->whereDate('payment_date', '<=', $end->toDateString())
            ->get(['amount']);
        $refundTransactions = ReservationRefund::where('status', 'completed')
            ->whereDate('refund_date', '>=', $start->toDateString())
            ->whereDate('refund_date', '<=', $end->toDateString())
            ->get(['amount']);
        $grossPaymentsInPeriodCents = (int) $paymentTransactions->sum(fn (ReservationPayment $payment) => Reservation::toCents($payment->amount));
        $refundsInPeriodCents = (int) $refundTransactions->sum(fn (ReservationRefund $refund) => Reservation::toCents($refund->amount));
        $contractValueCents = (int) $reservationTotals->sum('contract_price_cents');
        $grossPaidCents = (int) $reservationTotals->sum('gross_paid_cents');
        $totalRefundedCents = (int) $reservationTotals->sum('total_refunded_cents');
        $netPaidCents = (int) $reservationTotals->sum('net_paid_cents');
        $outstandingBalanceCents = (int) $reservationTotals->sum(fn (array $financials) => $financials['remaining_balance_cents'] ?? 0);

        return [
            'period' => $period,
            'period_start' => $start,
            'period_end' => $end,
            'generated_at' => $now->copy(),
            'reservation_count' => $reservations->count(),
            'confirmed_reservations' => (clone $reservations)->where('status', 'confirmed')->count(),
            'completed_events' => (clone $reservations)->where('status', 'completed')->count(),
            'cancelled_reservations' => (clone $reservations)->where('status', 'cancelled')->count(),
            'inquiry_count' => Inquiry::whereBetween('created_at', [$start, $end])->count(),
            'contract_value' => $contractValueCents / 100,
            'gross_paid' => $grossPaidCents / 100,
            'total_refunded' => $totalRefundedCents / 100,
            'net_paid' => $netPaidCents / 100,
            'outstanding_balance' => $outstandingBalanceCents / 100,
            'gross_payments_in_period' => $grossPaymentsInPeriodCents / 100,
            'refunds_in_period' => $refundsInPeriodCents / 100,
            'net_collected_in_period' => ($grossPaymentsInPeriodCents - $refundsInPeriodCents) / 100,
        ];
    }
}
