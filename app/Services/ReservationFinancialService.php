<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;

class ReservationFinancialService
{
    /**
     * @return array{
     *     contract_price_cents: int|null,
     *     gross_paid_cents: int,
     *     total_refunded_cents: int,
     *     net_paid_cents: int,
     *     remaining_balance_cents: int|null,
     *     payment_status: string,
     *     payment_type: string,
     *     payments_count: int,
     *     has_payment_history: bool
     * }
     */
    public function calculate(Reservation $reservation, bool $includeLegacyTotal = true): array
    {
        $payments = $reservation->relationLoaded('payments')
            ? $reservation->getRelation('payments')
            : $reservation->payments()->get();
        $refunds = $reservation->relationLoaded('refunds')
            ? $reservation->getRelation('refunds')
            : $reservation->refunds()->get();

        $grossPaidCents = (int) $payments->sum(fn (ReservationPayment $payment) => Reservation::toCents($payment->amount));
        $hasPaymentHistory = $payments->contains(
            fn (ReservationPayment $payment) => Reservation::toCents($payment->amount) > 0
        );
        $totalRefundedCents = (int) $refunds
            ->where('status', 'completed')
            ->sum(fn (ReservationRefund $refund) => Reservation::toCents($refund->amount));

        if ($includeLegacyTotal && $payments->isEmpty() && $refunds->isEmpty()) {
            $grossPaidCents = Reservation::toCents($reservation->amount_paid ?? 0);
        }

        if ($totalRefundedCents > $grossPaidCents) {
            throw new \LogicException('Reservation refunds exceed recorded payments.');
        }

        $netPaidCents = $grossPaidCents - $totalRefundedCents;
        $contractPriceCents = $reservation->total_cost === null ? null : Reservation::toCents($reservation->total_cost);
        $remainingBalanceCents = $contractPriceCents === null
            ? null
            : max(0, $contractPriceCents - $netPaidCents);
        $latestPayment = $payments->sortBy([
            ['payment_date', 'desc'],
            ['id', 'desc'],
        ])->first();

        $isCancelled = $reservation->status === Reservation::STATUS_CANCELLED;
        $paymentStatus = match (true) {
            $isCancelled && $hasPaymentHistory && $grossPaidCents > 0 && $totalRefundedCents >= $grossPaidCents => 'Fully Refunded',
            $isCancelled && $hasPaymentHistory && $totalRefundedCents > 0 => 'Partially Refunded',
            $netPaidCents <= 0 => 'Unpaid',
            $contractPriceCents !== null && $netPaidCents >= $contractPriceCents => 'Fully Paid',
            $contractPriceCents === null && in_array($latestPayment?->payment_type, ['Full Payment', 'Final Payment'], true) => 'Fully Paid',
            default => 'Partially Paid',
        };

        return [
            'contract_price_cents' => $contractPriceCents,
            'gross_paid_cents' => $grossPaidCents,
            'total_refunded_cents' => $totalRefundedCents,
            'net_paid_cents' => $netPaidCents,
            'remaining_balance_cents' => $remainingBalanceCents,
            'payment_status' => $paymentStatus,
            'payment_type' => $latestPayment?->payment_type ?? 'Unpaid',
            'payments_count' => $payments->count(),
            'has_payment_history' => $hasPaymentHistory,
        ];
    }

    /**
     * @return array{
     *     contract_price: float|null,
     *     gross_paid: float,
     *     total_refunded: float,
     *     net_paid: float,
     *     remaining_balance: float|null,
     *     payment_status: string
     * }
     */
    public function amounts(Reservation $reservation, bool $includeLegacyTotal = true): array
    {
        $financials = $this->calculate($reservation, $includeLegacyTotal);

        return [
            'contract_price' => $financials['contract_price_cents'] === null ? null : $financials['contract_price_cents'] / 100,
            'gross_paid' => $financials['gross_paid_cents'] / 100,
            'total_refunded' => $financials['total_refunded_cents'] / 100,
            'net_paid' => $financials['net_paid_cents'] / 100,
            'remaining_balance' => $financials['remaining_balance_cents'] === null ? null : $financials['remaining_balance_cents'] / 100,
            'payment_status' => $financials['payment_status'],
        ];
    }

    public function recalculate(Reservation $reservation): array
    {
        $financials = $this->calculate($reservation, false);

        $reservation->forceFill([
            'amount_paid' => $financials['net_paid_cents'] / 100,
            'balance' => ($financials['remaining_balance_cents'] ?? 0) / 100,
            'payment_status' => $financials['payment_status'],
            'payment_type' => $financials['payment_type'],
        ])->save();

        return $financials;
    }
}
