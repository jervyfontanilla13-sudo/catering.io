<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const CAPACITY_OCCUPYING_STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED];

    public const MAX_ACTIVE_RESERVATIONS_PER_DATE = 4;

    /** @deprecated Use MAX_ACTIVE_RESERVATIONS_PER_DATE. */
    public const MAX_ACCEPTED_BOOKINGS_PER_DATE = self::MAX_ACTIVE_RESERVATIONS_PER_DATE;

    protected $fillable = [
        'client_id',
        'package_id',
        'full_name',
        'contact_number',
        'email',
        'address',
        'event_type',
        'event_date',
        'event_time',
        'venue',
        'guest_count',
        'estimated_budget',
        'total_cost',
        'additional_services',
        'special_requests',
        'additional_notes',
        'admin_notes',
        'service_contract',
        'service_contracts',
        'status',
        'payment_status',
        'payment_type',
        'amount_paid',
        'balance',
        'payment_due_date',
        'reservation_code',
    ];

    protected $casts = [
        'estimated_budget' => 'float',
        'total_cost' => 'float',
        'amount_paid' => 'float',
        'balance' => 'float',
        'payment_due_date' => 'date',
        'service_contracts' => 'array',
    ];

    public function contractFiles(): array
    {
        return array_values(array_filter(array_merge(
            $this->service_contract ? [$this->service_contract] : [],
            $this->service_contracts ?? [],
        )));
    }

    public function hasCurrentContract(): bool
    {
        return $this->contractFiles() !== [];
    }

    public function scopeOccupyingCapacity(Builder $query): Builder
    {
        return $query->whereIn('status', self::CAPACITY_OCCUPYING_STATUSES);
    }

    public function scopeOpenAccepted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONFIRMED);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_PENDING => 'Under Review',
            self::STATUS_CONFIRMED => 'Accepted',
            self::STATUS_COMPLETED => 'Completed',
            self::STATUS_CANCELLED => 'Cancelled',
            null, '' => 'Unknown',
            default => str($status)->replace('_', ' ')->title()->toString(),
        };
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function payments()
    {
        return $this->hasMany(ReservationPayment::class)->orderBy('payment_date')->orderBy('id');
    }

    public function refunds()
    {
        return $this->hasMany(ReservationRefund::class)->orderBy('refund_date')->orderBy('id');
    }

    public function financials(): array
    {
        return app(\App\Services\ReservationFinancialService::class)->calculate($this);
    }

    /** Display label for the stored payment_status ("Unpaid" is kept in storage for existing filters and reports). */
    public static function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            null, 'Unpaid' => 'No Payment',
            'Downpayment', 'Partial Payment' => 'Partially Paid',
            default => $status,
        };
    }

    /** Maps a payment status onto the shared status-badge palette. */
    public static function paymentStatusBadge(?string $status): string
    {
        return match ($status) {
            'Fully Paid' => 'confirmed',
            'Partially Paid', 'Partial Payment' => 'completed',
            'Partially Refunded' => 'pending',
            'Downpayment' => 'pending',
            'Fully Refunded' => 'neutral',
            default => 'neutral',
        };
    }

    /**
     * Bookings paid before the payment history existed only have a running total.
     * Record that total as one opening entry so the ledger and the total always agree.
     */
    public function ensurePaymentLedger(): void
    {
        if ((float) ($this->amount_paid ?? 0) <= 0 || $this->payments()->exists()) {
            return;
        }

        $this->payments()->create([
            'payment_date' => ($this->updated_at ?? now())->toDateString(),
            'payment_type' => $this->payment_status === 'Fully Paid' || $this->payment_type === 'Full Payment' ? 'Full Payment' : 'Downpayment',
            'amount' => $this->amount_paid,
            'payment_method' => 'Other',
            'notes' => 'Opening balance carried over from the previous payment tracker.',
            'recorded_by_name' => 'System',
        ]);
    }

    /** Recomputes the stored net totals and status from the payment and refund history. */
    public function recalculatePaymentTotals(): void
    {
        app(\App\Services\ReservationFinancialService::class)->recalculate($this);
    }

    public function remainingBalanceCents(): ?int
    {
        return $this->financials()['remaining_balance_cents'];
    }

    /**
     * The single definition of "payment due soon" — shared by the admin dashboard's count
     * and the reservation list filter it links to, so the two can never disagree.
     */
    public function isPaymentDueSoon(\Carbon\Carbon $byDate, ?array $financials = null): bool
    {
        return $this->status === 'confirmed'
            && $this->payment_due_date !== null
            && $this->payment_due_date->lte($byDate)
            && (($financials ?? $this->financials())['remaining_balance_cents'] ?? 0) > 0;
    }

    public static function toCents(float|int|string|null $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Customer-facing progress steps derived from the stored status (no separate status system).
     * Each step: key, label, description, and state (complete|current|upcoming|cancelled).
     */
    public function timelineSteps(): array
    {
        if ($this->status === self::STATUS_CANCELLED) {
            return [
                ['key' => 'submitted', 'label' => 'Submitted', 'description' => 'Your reservation request has been received.', 'state' => 'complete'],
                ['key' => 'under_review', 'label' => 'Under Review', 'description' => 'Our team is reviewing your reservation details.', 'state' => 'complete'],
                ['key' => 'cancelled', 'label' => self::statusLabel(self::STATUS_CANCELLED), 'description' => 'Your reservation has been cancelled.', 'state' => 'cancelled'],
            ];
        }

        // "Under Review" is the customer-facing label for the stored "pending" status.
        $order = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_COMPLETED];
        $foundIndex = array_search($this->status, $order, true);
        $currentIndex = $foundIndex === false ? 0 : $foundIndex;

        $steps = [
            ['key' => 'submitted', 'label' => 'Submitted', 'description' => 'Your reservation request has been received.'],
            ['key' => 'under_review', 'label' => self::statusLabel(self::STATUS_PENDING), 'description' => 'Our team is reviewing your reservation details.'],
            ['key' => 'accepted', 'label' => self::statusLabel(self::STATUS_CONFIRMED), 'description' => 'Your reservation has been accepted.'],
            ['key' => 'completed', 'label' => self::statusLabel(self::STATUS_COMPLETED), 'description' => 'Your event has been completed. Thank you for choosing 3YOS Catering.'],
        ];

        foreach ($steps as $i => &$step) {
            if ($i === 0) {
                $step['state'] = 'complete';
                continue;
            }

            $stepOrderIndex = $i - 1;
            $step['state'] = $stepOrderIndex < $currentIndex ? 'complete' : ($stepOrderIndex === $currentIndex ? 'current' : 'upcoming');
        }

        return $steps;
    }
}
