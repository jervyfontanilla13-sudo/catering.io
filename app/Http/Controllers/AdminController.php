<?php

namespace App\Http\Controllers;

use App\Mail\InquiryReplyMail;
use App\Mail\ReservationAcceptedMail;
use App\Mail\ReservationCancelledMail;
use App\Mail\ReservationUpdatedMail;
use App\Models\ActivityLog;
use App\Models\Inquiry;
use App\Models\Package;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\ReservationRefund;
use App\Models\ReservationStatusNotification;
use App\Models\Service;
use App\Services\ReservationNeedsAttentionService;
use App\Services\ReservationFinancialService;
use App\Services\ReservationCapacityService;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\HeaderUtils;

class AdminController extends Controller
{
    public function index(ReservationNeedsAttentionService $needsAttentionService, ReservationCapacityService $capacity)
    {
        $reservationCount = Reservation::count();
        $inquiryCount = Inquiry::count();
        $serviceCount = Service::count();
        $packageCount = Package::count();
        $calendarReservations = Reservation::query()
            ->whereIn('status', ['pending', 'confirmed', 'completed', 'cancelled'])
            ->orderBy('event_date')
            ->get(['id', 'reservation_code', 'full_name', 'event_type', 'event_date', 'event_time', 'venue', 'status']);
        $capacityByDate = $capacity->countsForDates($calendarReservations->pluck('event_date')->all());
        $calendarEvents = $calendarReservations->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'code' => $reservation->reservation_code,
                'name' => $reservation->full_name,
                'eventType' => $reservation->event_type,
                'date' => $reservation->event_date,
                'time' => $reservation->event_time,
                'venue' => $reservation->venue,
                'status' => $reservation->status,
                'occupiedCapacity' => (int) $capacityByDate->get($reservation->event_date, 0),
            ])
            ->values();
        $calendarCapacityByDate = $capacityByDate->all();

        [$needsAttention, $todaySection, $businessOverview] = $this->buildDashboardInsights($needsAttentionService);

        return view('admin.dashboard', compact(
            'reservationCount', 'inquiryCount', 'serviceCount', 'packageCount', 'calendarEvents', 'calendarCapacityByDate',
            'needsAttention', 'todaySection', 'businessOverview',
        ));
    }

    /**
     * Split dashboard figures into three non-overlapping groups: cross-cutting action items
     * ("needs attention"), this week's schedule ("today"), and lifetime aggregate KPIs
     * ("business overview") — so aggregate totals are never mislabeled as today's activity.
     */
    private function buildDashboardInsights(ReservationNeedsAttentionService $needsAttentionService): array
    {
        $reservations = Reservation::with('payments', 'refunds')->get();
        $attentionAnalysis = $needsAttentionService->analyze($reservations);
        $financials = $attentionAnalysis['financials'];
        $confirmed = $reservations->where('status', Reservation::STATUS_CONFIRMED);
        // Canonical "needs a response" definition — shared with the inquiries inbox's own
        // Needs Attention section and its ?view=needs_attention filter, so the dashboard count
        // and the list it links to can never disagree.
        $inquiriesNeedingResponse = Inquiry::query()->needsAttention()->count();

        $needsAttention = [
            'pending_reservations' => $reservations->where('status', 'pending')->count(),
            'inquiries_needing_response' => $inquiriesNeedingResponse,
            'unpaid_accepted' => $attentionAnalysis['counts'][ReservationNeedsAttentionService::NO_PAYMENT],
            'missing_contracts' => $attentionAnalysis['counts'][ReservationNeedsAttentionService::MISSING_CONTRACT],
            'outstanding_balances' => $attentionAnalysis['counts'][ReservationNeedsAttentionService::OUTSTANDING_BALANCE],
            'events_awaiting_completion' => $attentionAnalysis['counts'][ReservationNeedsAttentionService::EVENTS_AWAITING_COMPLETION],
        ];

        $weekAhead = now()->addDays(7)->endOfDay();
        $scheduled = $reservations->whereIn('status', ['pending', 'confirmed']);
        $todaySection = [
            'events_today' => $scheduled->filter(fn (Reservation $r) => Carbon::parse($r->event_date)->isToday())->count(),
            'upcoming_events' => $scheduled->filter(fn (Reservation $r) => Carbon::parse($r->event_date)->isFuture() && ! Carbon::parse($r->event_date)->isToday() && Carbon::parse($r->event_date)->lte($weekAhead))->count(),
            // Reservation::isPaymentDueSoon() is the single source of truth for this figure —
            // also used by reservations() when the dashboard card links through with ?payment_due=soon.
            'payments_due' => $confirmed->filter(fn (Reservation $r) => $financials[$r->id] !== null
                && $r->isPaymentDueSoon($weekAhead, $financials[$r->id]))->count(),
            'inquiries_needing_response' => $inquiriesNeedingResponse,
        ];

        $businessOverview = [
            'total_reservations' => $reservations->count(),
            'total_inquiries' => Inquiry::count(),
            'revenue' => $reservations->sum(fn (Reservation $r) => $financials[$r->id]['net_paid_cents'] ?? 0) / 100,
            'outstanding_balance' => $reservations->sum(fn (Reservation $r) => $financials[$r->id]['remaining_balance_cents'] ?? 0) / 100,
            'completed_events' => $reservations->where('status', 'completed')->count(),
        ];

        return [$needsAttention, $todaySection, $businessOverview];
    }

    public function reservations(Request $request, ?ReservationNeedsAttentionService $needsAttentionService = null)
    {
        $needsAttentionService ??= app(ReservationNeedsAttentionService::class);
        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $search = $request->input('search');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        // Compound filters the dashboard's "Today" cards link through with — kept separate from
        // the plain `status` dropdown so that control's own single-value binding stays untouched.
        $scope = $request->input('scope');
        $paymentDueSoon = $request->input('payment_due') === 'soon';
        $attentionFilter = $request->input('attention');

        $query = Reservation::with('client', 'package', 'payments', 'refunds')->latest();

        if (in_array($attentionFilter, [
            ReservationNeedsAttentionService::NO_PAYMENT,
            ReservationNeedsAttentionService::MISSING_CONTRACT,
            ReservationNeedsAttentionService::OUTSTANDING_BALANCE,
            ReservationNeedsAttentionService::EVENTS_AWAITING_COMPLETION,
        ], true)) {
            $query->whereIn('id', $needsAttentionService->reservationIds()[$attentionFilter]);
        }

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        } elseif ($scope === 'scheduled') {
            // Mirrors buildDashboardInsights()'s $scheduled definition (pending + confirmed) exactly,
            // so the "Events today" / "Upcoming" card counts always match this filtered list.
            $query->whereIn('status', ['pending', 'confirmed']);
        }

        $this->applyPaymentStatusFilter($query, $paymentStatus);

        if ($search !== null && trim($search) !== '') {
            $this->applyReservationSearch($query, $search);
        }

        if ($dateFrom) {
            $query->whereDate('event_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('event_date', '<=', $dateTo);
        }

        if ($paymentDueSoon) {
            // Reservation::isPaymentDueSoon() is the same predicate buildDashboardInsights() counts
            // for the "Payments due soon" card, so the card and this list can never disagree.
            $weekAhead = now()->addDays(7)->endOfDay();
            $acceptedReservations = Reservation::query()
                ->openAccepted()
                ->with(['payments', 'refunds'])
                ->get();
            $financials = $needsAttentionService->analyze($acceptedReservations)['financials'];
            $dueSoonIds = $acceptedReservations
                ->filter(fn (Reservation $r) => $financials[$r->id] !== null
                    && $r->isPaymentDueSoon($weekAhead, $financials[$r->id]))
                ->pluck('id');
            $query->whereIn('id', $dueSoonIds);
        }

        $matchingReservationCount = (clone $query)->count();
        $perPage = 10;
        $lastPage = max(1, (int) ceil($matchingReservationCount / $perPage));

        if ($request->integer('page', 1) > $lastPage) {
            return redirect()->route('admin.reservations', array_merge(
                $request->query(),
                ['page' => $lastPage],
            ));
        }

        $reservations = $query->paginate($perPage)->withQueryString();
        $packages = Package::orderBy('price')->get(['id', 'name', 'price']);
        $customerCount = Reservation::query()->distinct('email')->count('email');
        $pendingCount = Reservation::query()->where('status', 'pending')->count();
        $acceptedCount = Reservation::query()->where('status', 'confirmed')->count();
        $cancelledCount = Reservation::query()->where('status', 'cancelled')->count();

        return view('admin.reservations', compact(
            'reservations',
            'matchingReservationCount',
            'packages',
            'customerCount',
            'pendingCount',
            'acceptedCount',
            'cancelledCount',
            'status',
            'paymentStatus',
            'search',
            'dateFrom',
            'dateTo',
            'scope',
            'paymentDueSoon',
        ))->with([
            'filterStatus' => $status,
            'filterPaymentStatus' => $paymentStatus,
            'search' => $search,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
    }

    public function showReservation(Reservation $reservation)
    {
        $reservation->load('client', 'package', 'payments', 'refunds');
        $reservation->setAttribute('payment_status', $reservation->financials()['payment_status']);
        $packages = Package::orderBy('price')->get(['id', 'name', 'price']);

        $activity = ActivityLog::query()
            ->where(function (Builder $match) use ($reservation): void {
                $match->where('description', 'like', '%#'.$reservation->id.'%')
                    ->orWhere('description', 'like', '%/'.$reservation->id.'/%')
                    ->orWhere('description', 'like', '%/'.$reservation->id)
                    ->when($reservation->reservation_code, fn (Builder $q) => $q->orWhere('description', 'like', '%'.$reservation->reservation_code.'%'));
            })
            ->latest('id')
            ->limit(20)
            ->get();

        return view('admin.reservation-show', compact('reservation', 'packages', 'activity'));
    }

    public function exportReservationsCsv(Request $request, ?ReservationFinancialService $financialService = null)
    {
        $financialService ??= app(ReservationFinancialService::class);
        $query = Reservation::with('payments', 'refunds')->latest();

        $status = $request->input('status');
        $paymentStatus = $request->input('payment_status');
        $search = trim((string) $request->input('search', ''));
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'], true)) {
            $query->where('status', $status);
        }

        $this->applyPaymentStatusFilter($query, $paymentStatus);

        if ($search !== '') {
            $this->applyReservationSearch($query, $search);
        }

        if ($dateFrom) {
            $query->whereDate('event_date', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('event_date', '<=', $dateTo);
        }

        $reservations = $query->get();

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Reservation Code', 'Customer Name', 'Email', 'Contact Number', 'Event Type', 'Event Date', 'Venue', 'Status', 'Payment Status', 'Contract Price', 'Gross Paid', 'Refunded', 'Net Paid', 'Balance']);

        foreach ($reservations as $reservation) {
            $amounts = $financialService->amounts($reservation);
            fputcsv($handle, [
                $reservation->reservation_code ?? '',
                $reservation->full_name ?? '',
                $reservation->email ?? '',
                $reservation->contact_number ?? '',
                $reservation->event_type ?? '',
                $reservation->event_date ? Carbon::parse($reservation->event_date)->format('Y-m-d') : '',
                $reservation->venue ?? '',
                $reservation->status ?? '',
                $amounts['payment_status'],
                (string) ($reservation->total_cost ?? 0),
                number_format($amounts['gross_paid'], 2, '.', ''),
                number_format($amounts['total_refunded'], 2, '.', ''),
                number_format($amounts['net_paid'], 2, '.', ''),
                number_format($amounts['remaining_balance'] ?? 0, 2, '.', ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'reservations-'.now()->format('YmdHis').'.csv';

        return response($csv ?: '', 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function inquiries(Request $request)
    {
        $view = $request->input('view', 'all');
        $search = trim((string) $request->input('search', ''));

        $query = Inquiry::query();

        if ($view === 'needs_attention') {
            // Same scope as the always-visible Needs Attention section below and the dashboard's
            // "Inquiries needing response" count, so that card and this list can never disagree.
            $query->needsAttention();
        } elseif (in_array($view, ['new', 'in_progress', 'responded', 'closed'], true)) {
            $query->where('status', $view);
        }

        if ($search !== '') {
            $pattern = '%'.str($search)->lower().'%';
            $query->where(function (Builder $matches) use ($pattern, $search): void {
                $matches->whereRaw('LOWER(COALESCE(full_name, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(subject, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(COALESCE(category, \'\')) LIKE ?', [$pattern])
                    ->orWhereRaw('CAST(id AS CHAR) = ?', [$search]);
            });
        }

        $inquiries = $query->latest()->get();

        // Always computed fresh from the real data, independent of the filters above — this is
        // the "what needs my attention right now" view, not something a search/tab should hide.
        $needsAttention = Inquiry::query()->needsAttention()->latest()->get();

        return view('admin.inquiries', compact('inquiries', 'needsAttention', 'view', 'search'));
    }

    public function showInquiry(Inquiry $inquiry)
    {
        // Viewing marks it read but must never silently change its status — status only moves
        // when the admin actually does something (attempts or sends a reply), see replyToInquiry().
        if ($inquiry->viewed_at === null) {
            $inquiry->update(['viewed_at' => now()]);
        }

        return view('admin.inquiry-show', compact('inquiry'));
    }

    public function replyToInquiry(Request $request, Inquiry $inquiry)
    {
        // Step 1: validate the reply is not empty.
        $data = $request->validate(['reply' => ['required', 'string', 'max:5000']]);

        $mailSent = false;
        $mailError = null;

        // Step 2: send the email first. The status only changes if this succeeds.
        try {
            Mail::to($inquiry->email, $inquiry->full_name)->send(new InquiryReplyMail(
                $inquiry->full_name,
                $data['reply'],
                'Re: '.$inquiry->subject,
            ));
            $mailSent = ! in_array(config('mail.default'), ['log', 'array'], true);
        } catch (\Throwable $exception) {
            $mailError = $exception;
            report($exception);
        }

        if (! $mailSent) {
            // The reply text is kept so the admin doesn't lose what they typed. A genuine attempt to
            // reply is real engagement, so — unlike just viewing — this is allowed to move New to
            // In Progress; it never touches an already Responded/Closed inquiry.
            $inquiry->update([
                'admin_reply' => $data['reply'],
                'status' => $inquiry->status === 'new' ? 'in_progress' : $inquiry->status,
            ]);

            if (in_array(config('mail.default'), ['log', 'array'], true) && $mailError === null) {
                return redirect()->route('admin.inquiries.show', $inquiry)->with(
                    'error',
                    'Failed to send reply. The inquiry status was not changed. Email delivery is disabled because MAIL_MAILER is set to '.config('mail.default').'.'
                );
            }

            return back()->with(
                'error',
                'Failed to send reply. The inquiry status was not changed. Details: '.($mailError?->getMessage() ?? 'Unknown mail error.')
            );
        }

        // Step 3: the email was sent successfully — now mark the inquiry as responded.
        try {
            $inquiry->update(['admin_reply' => $data['reply'], 'replied_at' => now(), 'status' => 'responded']);
        } catch (\Throwable $exception) {
            report($exception);

            return redirect()->route('admin.inquiries.show', $inquiry)->with(
                'error',
                'Reply was sent to '.$inquiry->email.', but the inquiry status could not be updated. Please refresh and update it manually.'
            );
        }

        // Step 4: confirm both the email and the status change.
        return redirect()->route('admin.inquiries.show', $inquiry)->with('success', 'Reply sent successfully. Inquiry marked as Responded.');
    }

    public function destroyInquiry(Inquiry $inquiry)
    {
        $inquiry->delete();

        return redirect()->route('admin.inquiries')->with('success', 'Inquiry deleted.');
    }

    public function analytics(Request $request, ReservationFinancialService $financialService)
    {
        $selectedRange = $request->input('range', 'all_time');
        $dateFrom = $request->input('from');
        $dateTo = $request->input('to');

        $validated = $request->validate([
            'range' => ['nullable', 'in:all_time,today,this_week,this_month,last_month,this_year,custom'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $selectedRange = $validated['range'] ?? $selectedRange;
        [$rangeStart, $rangeEnd, $rangeLabel] = $this->resolveAnalyticsRange($selectedRange, $dateFrom, $dateTo);

        $reservations = Reservation::with('payments', 'refunds', 'package:id,name')
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('event_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('event_date', '<=', $date->toDateString()))
            ->get();

        $financials = $reservations->mapWithKeys(fn (Reservation $reservation) => [
            $reservation->id => $financialService->calculate($reservation),
        ]);

        $cents = fn (string $key) => (int) $financials->sum($key);

        $paidRecords = ReservationPayment::query()
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('payment_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('payment_date', '<=', $date->toDateString()))
            ->get();
        $refundedRecords = ReservationRefund::query()
            ->where('status', 'completed')
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('refund_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('refund_date', '<=', $date->toDateString()))
            ->get();

        $paidAmountCents = $paidRecords->sum(fn (ReservationPayment $payment) => Reservation::toCents($payment->amount));
        $refundedAmountCents = $refundedRecords->sum(fn (ReservationRefund $refund) => Reservation::toCents($refund->amount));
        $rangeApplied = $rangeStart !== null || $rangeEnd !== null;

        $totals = [
            'reservations' => $reservations->count(),
            'paid' => $rangeApplied ? ($paidAmountCents / 100) : ($cents('gross_paid_cents') / 100),
            'refunded' => $rangeApplied ? ($refundedAmountCents / 100) : ($cents('total_refunded_cents') / 100),
            'net' => $rangeApplied ? (($paidAmountCents - $refundedAmountCents) / 100) : ($cents('net_paid_cents') / 100),
            'outstanding' => (int) $financials->sum(fn (array $f) => $f['remaining_balance_cents'] ?? 0) / 100,
            'refunded_reservations' => $rangeApplied ? $refundedRecords->pluck('reservation_id')->unique()->count() : $financials->filter(fn (array $f) => $f['total_refunded_cents'] > 0)->count(),
        ];

        $statusCounts = collect(['pending', 'confirmed', 'completed', 'cancelled'])
            ->mapWithKeys(fn ($status) => [$status => $reservations->where('status', $status)->count()]);

        $topPackages = $reservations->filter(fn (Reservation $r) => $r->package)
            ->groupBy('package_id')
            ->map(fn ($group) => (object) [
                'name' => $group->first()->package->name,
                'total' => $group->count(),
                'revenue' => $group->sum(fn (Reservation $r) => $financials[$r->id]['net_paid_cents']) / 100,
            ])
            ->sortByDesc('total')
            ->take(5)
            ->values();

        $months = $this->buildAnalyticsMonthBuckets($rangeStart, $rangeEnd, $selectedRange);
        $payments = ReservationPayment::with('reservation:id,full_name')
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('payment_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('payment_date', '<=', $date->toDateString()))
            ->get();
        $refunds = ReservationRefund::with('reservation:id,full_name')
            ->where('status', 'completed')
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('refund_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('refund_date', '<=', $date->toDateString()))
            ->get();

        $monthly = $months->map(function ($month) use ($reservations, $payments, $refunds) {
            $key = $month->format('Y-m');
            $paid = $payments->filter(fn ($p) => $p->payment_date->format('Y-m') === $key)->sum(fn ($p) => Reservation::toCents($p->amount));
            $refunded = $refunds->filter(fn ($r) => $r->refund_date->format('Y-m') === $key)->sum(fn ($r) => Reservation::toCents($r->amount));

            return (object) [
                'label' => $month->format('F Y'),
                'reservations' => $reservations->filter(fn ($r) => Carbon::parse($r->event_date)->format('Y-m') === $key)->count(),
                'paid' => $paid / 100,
                'refunded' => $refunded / 100,
                'net' => ($paid - $refunded) / 100,
            ];
        });

        $recentReservations = $reservations->sortByDesc('created_at')->take(5)->values();
        $recentPayments = ReservationPayment::with('reservation:id,full_name')
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('payment_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('payment_date', '<=', $date->toDateString()))
            ->latest('payment_date')->latest('id')->take(5)->get();
        $recentRefunds = ReservationRefund::with('reservation:id,full_name')
            ->where('status', 'completed')
            ->when($rangeStart, fn ($query, Carbon $date) => $query->whereDate('refund_date', '>=', $date->toDateString()))
            ->when($rangeEnd, fn ($query, Carbon $date) => $query->whereDate('refund_date', '<=', $date->toDateString()))
            ->latest('refund_date')->latest('id')->take(5)->get();

        $isCustomRangeValid = $selectedRange !== 'custom' || ($dateFrom !== null && $dateTo !== null && Carbon::parse($dateFrom, config('app.timezone'))->lte(Carbon::parse($dateTo, config('app.timezone'))));

        return view('admin.analytics', compact(
            'totals',
            'statusCounts',
            'topPackages',
            'monthly',
            'recentReservations',
            'recentPayments',
            'recentRefunds',
            'selectedRange',
            'rangeLabel',
            'dateFrom',
            'dateTo',
            'isCustomRangeValid',
        ));
    }

    private function resolveAnalyticsRange(string $range, ?string $from, ?string $to): array
    {
        $timezone = config('app.timezone', 'UTC');
        $today = now($timezone);

        return match ($range) {
            'today' => [$today->copy()->startOfDay(), $today->copy()->endOfDay(), 'Today'],
            'this_week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(), 'This Week'],
            'this_month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'This Month'],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth(), 'Last Month'],
            'this_year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear(), 'This Year'],
            'custom' => $this->resolveCustomAnalyticsRange($from, $to),
            default => [null, null, 'All Time'],
        };
    }

    private function resolveCustomAnalyticsRange(?string $from, ?string $to): array
    {
        if ($from === null || $to === null) {
            throw ValidationException::withMessages([
                'from' => 'Please choose a start date for the custom range.',
                'to' => 'Please choose an end date for the custom range.',
            ]);
        }

        $timezone = config('app.timezone', 'UTC');
        $fromDate = Carbon::parse($from, $timezone)->startOfDay();
        $toDate = Carbon::parse($to, $timezone)->endOfDay();

        if ($fromDate->gt($toDate)) {
            throw ValidationException::withMessages([
                'to' => 'The end date must be on or after the start date.',
            ]);
        }

        return [$fromDate, $toDate, 'Custom Range'];
    }

    private function buildAnalyticsMonthBuckets(?Carbon $rangeStart, ?Carbon $rangeEnd, string $range): \Illuminate\Support\Collection
    {
        if ($rangeStart === null || $rangeEnd === null) {
            $months = collect(range(5, 0))->map(fn ($ago) => now()->startOfMonth()->subMonths($ago));

            return $months;
        }

        $cursor = $rangeStart->copy()->startOfMonth();
        $endMonth = $rangeEnd->copy()->startOfMonth();
        $months = collect();

        while ($cursor->lte($endMonth)) {
            $months->push($cursor->copy());
            $cursor->addMonth();
        }

        return $months;
    }

    public function acceptReservation(Request $request, Reservation $reservation, ReservationCapacityService $capacity)
    {
        return $this->transitionReservationStatus($request, $reservation, $capacity, Reservation::STATUS_CONFIRMED);
    }

    public function cancelReservation(Request $request, Reservation $reservation, ReservationCapacityService $capacity)
    {
        return $this->transitionReservationStatus($request, $reservation, $capacity, Reservation::STATUS_CANCELLED);
    }

    public function completeReservation(Request $request, Reservation $reservation)
    {
        $request->validate(['status' => ['prohibited']]);

        DB::transaction(function () use ($request, $reservation): void {
            $lockedReservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            if ($lockedReservation->status !== Reservation::STATUS_CONFIRMED) {
                throw ValidationException::withMessages(['status' => 'Only accepted reservations can be completed.']);
            }

            $timezone = config('app.timezone');
            $eventDate = Carbon::parse($lockedReservation->event_date, $timezone)->toDateString();
            if ($eventDate !== now($timezone)->toDateString()) {
                throw ValidationException::withMessages([
                    'status' => 'This reservation can only be completed on the scheduled event date.',
                ]);
            }

            $lockedReservation->update(['status' => Reservation::STATUS_COMPLETED]);
            $this->logReservationActivity(
                $request,
                'Reservation status changed',
                'Changed reservation #'.$lockedReservation->id.' status from '.Reservation::statusLabel(Reservation::STATUS_CONFIRMED).' to '.Reservation::statusLabel(Reservation::STATUS_COMPLETED).'.',
            );
        });

        return back()->with('success', 'Reservation marked as completed.');
    }

    private function transitionReservationStatus(
        Request $request,
        Reservation $reservation,
        ReservationCapacityService $capacity,
        string $targetStatus,
    ) {
        $request->validate(['status' => ['prohibited']]);

        $transitioned = DB::transaction(function () use ($request, $reservation, $capacity, $targetStatus): bool {
            $lockedReservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $originalStatus = $lockedReservation->status;

            if ($originalStatus === $targetStatus) {
                return false;
            }

            if ($targetStatus === Reservation::STATUS_CONFIRMED && $originalStatus !== Reservation::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => 'Only pending reservations can be accepted.']);
            }
            if ($targetStatus === Reservation::STATUS_CANCELLED
                && ! in_array($originalStatus, [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending or accepted reservations can be cancelled.']);
            }

            $capacity->lockDates([$lockedReservation->event_date]);
            if ($targetStatus === Reservation::STATUS_CONFIRMED
                && $capacity->countForDate($lockedReservation->event_date, $lockedReservation->id) >= Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE) {
                throw ValidationException::withMessages([
                    'status' => 'Maximum active reservations for this date has been reached. Only '.Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE.' active reservations are allowed per date.',
                ]);
            }

            $lockedReservation->ensurePaymentLedger();
            $lockedReservation->recalculatePaymentTotals();
            $lockedReservation->update(['status' => $targetStatus]);
            $this->logReservationActivity(
                $request,
                'Reservation status changed',
                'Changed reservation #'.$lockedReservation->id.' status from '.Reservation::statusLabel($originalStatus).' to '.Reservation::statusLabel($targetStatus).'.',
            );

            return true;
        });

        if (! $transitioned) {
            return back()->with('success', 'Reservation saved successfully.');
        }

        return back()->with('success', $this->sendStatusNotification($reservation->fresh(), $targetStatus));
    }

    public function updateReservation(Request $request, Reservation $reservation, ReservationCapacityService $capacity)
    {
        $data = $request->validate([
            'status' => ['prohibited'],
            'payment_status' => ['sometimes', 'nullable', 'in:Unpaid,Downpayment,Partial Payment,Fully Paid'],
            'payment_type' => ['sometimes', 'nullable', 'in:Unpaid,Downpayment,Partial Payment,Final Payment,Full Payment'],
            'total_cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'amount_paid' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'event_type' => ['sometimes', 'required', 'string', 'max:100'],
            'event_date' => ['sometimes', 'required', 'date'],
            'event_time' => ['sometimes', 'required', 'date_format:H:i'],
            'package_id' => ['sometimes', 'required', 'exists:packages,id'],
            'venue' => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'guest_count' => ['sometimes', 'required', 'integer', 'min:1', 'max:1000'],
            'additional_services' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'special_requests' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'additional_notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'admin_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
        ]);

        $detailFields = [
            'event_type', 'event_date', 'event_time', 'package_id', 'venue',
            'guest_count', 'additional_services', 'special_requests', 'additional_notes',
        ];
        $hasDetailUpdate = $request->hasAny($detailFields);
        if ($hasDetailUpdate && $reservation->status !== 'confirmed') {
            return back()->withInput()->withErrors(['schedule' => 'Only accepted reservations can have event details edited.']);
        }
        $reason = trim((string) ($data['reason'] ?? ''));
        unset($data['reason']);

        $hasPaymentUpdate = $request->has('amount_paid') || $request->has('payment_type') || $request->has('payment_status');
        $hasTotalCostUpdate = $request->has('total_cost');
        if ($hasPaymentUpdate && ! array_key_exists('total_cost', $data) && $reservation->total_cost === null) {
            return back()->withInput()->withErrors(['total_cost' => 'Enter the contract price before saving payment details.']);
        }

        if ($hasTotalCostUpdate && $data['total_cost'] === null) {
            return back()->withInput()->withErrors(['total_cost' => 'Enter the contract price before saving payment details.']);
        }

        // Payment totals and status are derived from the payment history, never written directly.
        $requestedPaid = $request->has('amount_paid') ? round((float) ($data['amount_paid'] ?? 0), 2) : null;
        unset($data['payment_status'], $data['payment_type'], $data['amount_paid']);

        $reservationChanges = [];
        $detailsChanged = false;

        DB::transaction(function () use ($request, $reservation, $data, $requestedPaid, $reason, $detailFields, $hasDetailUpdate, $capacity, &$reservationChanges, &$detailsChanged): void {
            $reservation = Reservation::whereKey($reservation->id)->lockForUpdate()->firstOrFail();
            $originalStatus = $reservation->status;
            $originalNotes = $reservation->admin_notes;
            $originalTotalCost = $reservation->total_cost;
            $oldPackage = $reservation->package;
            $oldPackageName = $oldPackage?->name ?? 'Custom package';

            $targetEventDate = $data['event_date'] ?? $reservation->event_date;
            $capacityMayChange = $hasDetailUpdate;
            if ($capacityMayChange) {
                $capacity->lockDates([$reservation->event_date, $targetEventDate]);
            }

            $scheduleDateChanged = isset($data['event_date'])
                && Carbon::parse($data['event_date'])->toDateString() !== Carbon::parse($reservation->event_date)->toDateString();
            if (in_array($originalStatus, Reservation::CAPACITY_OCCUPYING_STATUSES, true)
                && ($scheduleDateChanged || $hasDetailUpdate)) {
                $occupiedCount = $capacity->countForDate($targetEventDate, $reservation->id);

                if ($occupiedCount >= Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE) {
                    throw ValidationException::withMessages([
                        'event_date' => 'Unable to save this schedule because the selected date already has '.Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE.' active reservations.',
                    ]);
                }
            }

            $reservation->ensurePaymentLedger();
            $reservation->recalculatePaymentTotals();
            $paidCents = Reservation::toCents($reservation->amount_paid);
            $targetPaidCents = $requestedPaid === null ? $paidCents : Reservation::toCents($requestedPaid);
            $contractKnown = array_key_exists('total_cost', $data) || $reservation->total_cost !== null;
            $contractCents = Reservation::toCents($data['total_cost'] ?? $reservation->total_cost);

            if ($targetPaidCents < $paidCents) {
                throw ValidationException::withMessages(['amount_paid' => 'To lower the amount paid, edit the relevant entries in the payment history.']);
            }
            if ($contractKnown && $targetPaidCents > $contractCents) {
                throw ValidationException::withMessages([
                    array_key_exists('total_cost', $data) && $requestedPaid === null ? 'total_cost' : 'amount_paid' => 'The contract price cannot be lower than the total amount paid (₱'.number_format($targetPaidCents / 100, 2).').',
                ]);
            }

            $before = [
                'event_type' => $reservation->event_type,
                'event_date' => $reservation->event_date,
                'event_time' => $reservation->event_time,
                'venue' => $reservation->venue,
                'guest_count' => $reservation->guest_count,
                'package_id' => $reservation->package_id,
                'additional_services' => $reservation->additional_services,
                'special_requests' => $reservation->special_requests,
                'additional_notes' => $reservation->additional_notes,
            ];
            $trackedUpdates = array_intersect_key($data, array_flip($detailFields));
            $normalizedChanges = [];
            foreach ($trackedUpdates as $field => $value) {
                $oldValue = $before[$field] ?? null;
                $normalizedOld = $this->normalizeReservationDetail($field, $oldValue);
                $normalizedNew = $this->normalizeReservationDetail($field, $value);
                if ($normalizedOld !== $normalizedNew) {
                    $normalizedChanges[$field] = ['before' => $oldValue, 'after' => $value];
                }
            }

            if (isset($normalizedChanges['package_id']) || isset($normalizedChanges['guest_count'])) {
                $updatedPackageId = (int) ($data['package_id'] ?? $reservation->package_id);
                $updatedGuestCount = (int) ($data['guest_count'] ?? $reservation->guest_count);
                $updatedPackage = Package::findOrFail($updatedPackageId);
                $updatedEstimatedBudget = $updatedPackage->estimatedTotalFor($updatedGuestCount);
                if (Reservation::toCents($updatedEstimatedBudget) !== Reservation::toCents($reservation->estimated_budget)) {
                    $normalizedChanges['estimated_budget'] = [
                        'before' => $reservation->estimated_budget,
                        'after' => $updatedEstimatedBudget,
                    ];
                    $data['estimated_budget'] = $updatedEstimatedBudget;
                }
            }

            $reservation->update($data);

            if (array_key_exists('admin_notes', $data)
                && $this->normalizeReservationDetail('admin_notes', $originalNotes) !== $this->normalizeReservationDetail('admin_notes', $data['admin_notes'])) {
                $noteAdded = trim((string) $originalNotes) === '' && trim((string) ($data['admin_notes'] ?? '')) !== '';
                $action = $noteAdded ? 'Internal note added' : 'Internal note updated';
                $description = $noteAdded
                    ? 'Added an internal note to reservation #'.$reservation->id.'.'
                    : 'Updated the internal note for reservation #'.$reservation->id.'.';
                $this->logReservationActivity($request, $action, $description);
            }

            if ($normalizedChanges !== []) {
                $newPackageName = Package::find($reservation->package_id)?->name ?? 'Custom package';
                $labels = [
                    'event_type' => 'Event type',
                    'event_date' => 'Date',
                    'event_time' => 'Time',
                    'venue' => 'Venue',
                    'guest_count' => 'Guests',
                    'package_id' => 'Package',
                    'additional_services' => 'Additional services',
                    'special_requests' => 'Special requests',
                    'additional_notes' => 'Additional notes',
                    'estimated_budget' => 'Estimated package total',
                ];
                foreach ($normalizedChanges as $field => $change) {
                    $oldPackageValue = $field === 'package_id' ? $oldPackageName : null;
                    $newPackageValue = $field === 'package_id' ? $newPackageName : null;
                    $reservationChanges[] = [
                        'label' => $labels[$field],
                        'before' => $this->formatReservationDetail($field, $change['before'], $oldPackageValue),
                        'after' => $this->formatReservationDetail($field, $change['after'], $newPackageValue),
                    ];
                }

                $descriptionLines = array_map(
                    fn (array $change) => $change['label'].': '.$change['before'].' → '.$change['after'],
                    $reservationChanges,
                );
                $reservationLabel = $reservation->reservation_code ?: '#'.$reservation->id;
                $scheduleFields = ['event_date', 'event_time'];
                $isScheduleOnlyChange = array_diff(array_keys($normalizedChanges), $scheduleFields) === [];
                $activityTitle = $isScheduleOnlyChange ? 'Reservation schedule changed' : 'Reservation details updated';
                $description = 'Reservation #'.$reservation->id.' ('.$reservationLabel.")\n".implode("\n", $descriptionLines);
                if ($reason !== '') {
                    $description .= "\nReason: ".$reason;
                }

                $this->logReservationActivity($request, $activityTitle, $description);
                $detailsChanged = true;
            }

            // Older forms post a running total; record the increase as a payment history entry.
            if ($targetPaidCents > $paidCents) {
                $isFirst = ! $reservation->payments()->exists();
                $reachesContract = $contractKnown && $targetPaidCents >= $contractCents;
                $reservation->payments()->create([
                    'payment_date' => now()->toDateString(),
                    'payment_type' => $reachesContract ? ($isFirst ? 'Full Payment' : 'Final Payment') : ($isFirst ? 'Downpayment' : 'Partial Payment'),
                    'amount' => ($targetPaidCents - $paidCents) / 100,
                    'payment_method' => 'Other',
                    'notes' => 'Recorded from the reservations list.',
                    'recorded_by_user_id' => $request->hasSession() ? $request->session()->get('admin_user_id') : null,
                    'recorded_by_name' => $request->hasSession() ? $request->session()->get('admin_name', 'Administrator') : 'Administrator',
                ]);
                $this->logReservationActivity(
                    $request,
                    'Payment recorded',
                    'Recorded a payment of ₱'.number_format(($targetPaidCents - $paidCents) / 100, 2).' for reservation #'.$reservation->id.'.',
                );
            }

            if (array_key_exists('total_cost', $data)
                && Reservation::toCents($originalTotalCost) !== Reservation::toCents($data['total_cost'])) {
                $this->logReservationActivity(
                    $request,
                    'Contract amount updated',
                    'Updated the contract amount for reservation #'.$reservation->id.' from '.($originalTotalCost === null ? 'not set' : '₱'.number_format((float) $originalTotalCost, 2)).' to ₱'.number_format((float) $data['total_cost'], 2).'.',
                );
            }

            $reservation->recalculatePaymentTotals();
        });

        if ($detailsChanged) {
            return back()->with('success', $this->sendReservationUpdatedNotification($reservation->fresh('package')));
        }

        return back()->with('success', 'Reservation saved successfully.');
    }

    private function logReservationActivity(Request $request, string $action, string $description): void
    {
        $timestamp = now();

        ActivityLog::create([
            'user_id' => $request->hasSession() ? $request->session()->get('admin_user_id') : null,
            'actor_name' => $request->hasSession() ? $request->session()->get('admin_name', 'Unknown administrator') : 'Unknown administrator',
            'actor_email' => $request->hasSession() ? $request->session()->get('admin_email') : null,
            'actor_role' => $request->hasSession() ? $request->session()->get('admin_role', 'limited') : 'limited',
            'action' => $action,
            'method' => $request->method(),
            'ip_address' => $request->ip(),
            'activity_date' => $timestamp->toDateString(),
            'activity_time' => $timestamp->toTimeString(),
            'description' => $description,
        ]);
    }

    private function normalizeReservationDetail(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'event_date' => Carbon::parse($value)->toDateString(),
            'event_time' => Carbon::parse($value)->format('H:i'),
            'guest_count', 'package_id' => (int) $value,
            default => trim((string) $value) === '' ? null : trim((string) $value),
        };
    }

    private function formatReservationDetail(string $field, mixed $value, ?string $packageName = null): string
    {
        if ($field === 'package_id') {
            return $packageName ?? 'Custom package';
        }
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'event_date' => Carbon::parse($value)->format('F j, Y'),
            'event_time' => Carbon::parse($value)->format('g:i A'),
            'guest_count' => number_format((int) $value),
            'estimated_budget' => '₱'.number_format((float) $value, 2),
            default => (string) $value,
        };
    }

    private function sendReservationUpdatedNotification(Reservation $reservation): string
    {
        $message = 'Reservation updated.';
        if (! $reservation->email) {
            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => '',
                'notification_type' => 'updated',
                'status' => 'failed',
                'error_message' => 'Reservation has no email address on file.',
            ]);

            return $message.' The client could not be notified because there is no email address on file.';
        }

        try {
            Mail::to($reservation->email, $reservation->full_name)->send(new ReservationUpdatedMail($reservation));
            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => $reservation->email,
                'notification_type' => 'updated',
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $message.' An update notification was sent to the client.';
        } catch (\Throwable $exception) {
            report($exception);
            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => $reservation->email,
                'notification_type' => 'updated',
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            return $message.' The update notification email could not be sent.';
        }
    }

    /**
     * Send the accepted/cancelled notification email, log the outcome, and return the flash message.
     */
    private function sendStatusNotification(Reservation $reservation, string $status): string
    {
        $notificationType = $status === 'confirmed' ? 'accepted' : 'cancelled';
        $savedMessage = $status === 'confirmed' ? 'Reservation accepted' : 'Reservation cancelled';

        if (! $reservation->email) {
            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => '',
                'notification_type' => $notificationType,
                'status' => 'failed',
                'error_message' => 'Reservation has no email address on file.',
            ]);

            return $savedMessage.', but the notification email could not be sent.';
        }

        try {
            Mail::to($reservation->email, $reservation->full_name)->send(
                $notificationType === 'accepted'
                    ? new ReservationAcceptedMail($reservation)
                    : new ReservationCancelledMail($reservation)
            );

            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => $reservation->email,
                'notification_type' => $notificationType,
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            return $savedMessage.' and notification email sent.';
        } catch (\Throwable $e) {
            report($e);

            ReservationStatusNotification::create([
                'reservation_id' => $reservation->id,
                'recipient_email' => $reservation->email,
                'notification_type' => $notificationType,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return $savedMessage.', but the notification email could not be sent.';
        }
    }

    public function uploadReservationContract(Request $request, Reservation $reservation)
    {
        $data = $request->validate([
            'service_contract' => ['required', 'array', 'min:1', 'max:10'],
            'service_contract.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $paths = $reservation->service_contracts ?? [];
        foreach ($data['service_contract'] as $file) {
            $paths[] = $file->store('service-contracts', 'local');
        }
        $reservation->update(['service_contracts' => $paths]);

        return back()->with('success', count($data['service_contract']).' contract image(s) uploaded.');
    }

    public function previewReservationContract(Reservation $reservation, int $contract)
    {
        $path = $this->reservationContractPath($reservation, $contract);
        $disk = Storage::disk('local');
        $mimeType = $disk->mimeType($path);
        abort_unless(in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp'], true), 415);

        $stream = $disk->readStream($path);
        abort_if($stream === false, 404);

        $filename = basename($path);

        return response()->stream(
            static function () use ($stream): void {
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            [
                'Content-Type' => $mimeType,
                'Content-Disposition' => HeaderUtils::makeDisposition('inline', $filename, Str::ascii($filename) ?: 'contract-image'),
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public function downloadReservationContract(Reservation $reservation, int $contract)
    {
        $path = $this->reservationContractPath($reservation, $contract);
        $disk = Storage::disk('local');

        return $disk->download($path, basename($path), [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function deleteReservationContract(Request $request, Reservation $reservation, int $contract)
    {
        $files = $reservation->contractFiles();
        abort_unless(isset($files[$contract]), 404);

        Storage::disk('local')->delete($files[$contract]);
        $files = array_values(array_diff($files, [$files[$contract]]));

        $reservation->update([
            'service_contract' => null,
            'service_contracts' => $files,
        ]);

        return back()->with('success', 'Contract image deleted.');
    }

    private function reservationContractPath(Reservation $reservation, int $contract): string
    {
        $files = $reservation->contractFiles();
        abort_unless(isset($files[$contract]), 404);

        $path = $files[$contract];
        abort_unless(str_starts_with($path, 'service-contracts/'), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return $path;
    }

    private function applyReservationSearch(Builder $query, string $search): void
    {
        $term = str($search)->lower()->toString();
        $pattern = '%'.$term.'%';

        $query->where(function (Builder $matches) use ($pattern, $term): void {
            $matches->whereRaw('LOWER(COALESCE(reservation_code, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(CAST(id AS CHAR)) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(full_name, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(contact_number, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(event_type, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(venue, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(address, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(status, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(payment_status, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(payment_type, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(service_contract, \'\')) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(COALESCE(CAST(service_contracts AS CHAR), \'\')) LIKE ?', [$pattern])
                ->orWhereHas('package', fn (Builder $package) => $package->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$pattern]))
                ->orWhereHas('client', fn (Builder $client) => $client->whereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', [$pattern]));

            if (str_contains('accepted', $term)) {
                $matches->orWhere('status', 'confirmed');
            }
        });
    }

    private function applyPaymentStatusFilter(Builder $query, ?string $paymentStatus): void
    {
        if ($paymentStatus === 'Partially Paid' || in_array($paymentStatus, ['Downpayment', 'Partial Payment'], true)) {
            $query->whereIn('payment_status', ['Partially Paid', 'Downpayment', 'Partial Payment']);

            return;
        }

        if (in_array($paymentStatus, ['Unpaid', 'Fully Paid', 'Partially Refunded', 'Fully Refunded'], true)) {
            $query->where('payment_status', $paymentStatus);
        }
    }
}
