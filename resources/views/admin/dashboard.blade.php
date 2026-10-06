@extends('layouts.admin')

@section('content')
<section class="calendar-card card" id="reservation-calendar" aria-labelledby="reservation-calendar-title">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
        <div>
            <h2 class="h5 fw-bold mb-1" id="reservation-calendar-title">Calendar</h2>
            <p class="text-muted small mb-0">Monthly schedule · Capacity</p>
            <p class="small mb-0"><a href="{{ route('admin.support') }}#category-admin-calendar" target="_blank" rel="noopener">How do I use the calendar?</a></p>
        </div>
        <div class="calendar-legend" aria-label="Reservation status legend">
            <span><i class="calendar-dot calendar-dot--pending"></i>Pending</span>
            <span><i class="calendar-dot calendar-dot--confirmed"></i>Accepted</span>
            <span><i class="calendar-dot calendar-dot--completed"></i>Completed</span>
            <span><i class="calendar-dot calendar-dot--cancelled"></i>Cancelled</span>
        </div>
    </div>
    <div class="calendar-toolbar">
        <button type="button" class="calendar-nav" id="calendarPrevious" aria-label="Previous month">&#8592;</button>
        <h6 id="calendarMonth" class="mb-0 fw-bold" aria-live="polite"></h6>
        <button type="button" class="calendar-nav" id="calendarNext" aria-label="Next month">&#8594;</button>
    </div>
    <div class="calendar-weekdays" aria-hidden="true"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
    <div class="calendar-grid" id="reservationCalendar" aria-label="Calendar days"></div>
</section>

@php($attentionTotal = array_sum($needsAttention))
<div class="operations-summary">
    <section class="card attention-card" aria-labelledby="needs-attention-title">
        <div class="panel-header">
            <div><h2 class="h5 fw-bold mb-1" id="needs-attention-title">Needs Attention</h2><p class="text-muted small mb-0">Open items that need a decision.</p></div>
            @if($attentionTotal > 0)<span class="badge-soft badge-soft--warn">{{ $attentionTotal }} OPEN ITEMS</span>@endif
        </div>
        @if($attentionTotal === 0)
            <p class="text-muted small mb-0">Nothing needs attention right now.</p>
        @else
            <div class="attention-list">
                @if($needsAttention['pending_reservations'] > 0)
                    <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'pending']) }}"><span class="attention-item-label">Pending reservations need a decision</span><span class="attention-item-count">{{ $needsAttention['pending_reservations'] }}</span></a>
                @endif
                @if($needsAttention['inquiries_needing_response'] > 0)
                    <a class="attention-item" href="{{ route('admin.inquiries') }}"><span class="attention-item-label">Inquiries need a response</span><span class="attention-item-count">{{ $needsAttention['inquiries_needing_response'] }}</span></a>
                @endif
                @if($needsAttention['unpaid_accepted'] > 0)
                    <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::NO_PAYMENT]) }}"><span class="attention-item-label">Accepted reservations with no payment on file</span><span class="attention-item-count">{{ $needsAttention['unpaid_accepted'] }}</span></a>
                @endif
                @if($needsAttention['missing_contracts'] > 0)
                    <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::MISSING_CONTRACT]) }}"><span class="attention-item-label">Accepted reservations missing a contract</span><span class="attention-item-count">{{ $needsAttention['missing_contracts'] }}</span></a>
                @endif
                @if($needsAttention['outstanding_balances'] > 0)
                    <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::OUTSTANDING_BALANCE]) }}"><span class="attention-item-label">Accepted reservations with an outstanding balance</span><span class="attention-item-count">{{ $needsAttention['outstanding_balances'] }}</span></a>
                @endif
                @if($needsAttention['events_awaiting_completion'] > 0)
                    <a class="attention-item" href="{{ route('admin.reservations', ['status' => 'confirmed', 'attention' => \App\Services\ReservationNeedsAttentionService::EVENTS_AWAITING_COMPLETION]) }}"><span class="attention-item-label">Events Awaiting Completion</span><span class="attention-item-count">{{ $needsAttention['events_awaiting_completion'] }}</span></a>
                @endif
            </div>
        @endif
    </section>

    <section class="card today-upcoming-card" aria-labelledby="today-upcoming-title">
        <div class="panel-header"><div><h2 class="h5 fw-bold mb-1" id="today-upcoming-title">Today / Upcoming</h2><p class="text-muted small mb-0">The next operational priorities.</p></div></div>
        <div class="today-upcoming-groups">
            <div class="today-upcoming-group">
                <h3>Today</h3>
                <a class="operations-row" href="{{ route('admin.reservations', ['scope' => 'scheduled', 'date_from' => now()->toDateString(), 'date_to' => now()->toDateString()]) }}">
                    <span>Events today</span><strong>{{ $todaySection['events_today'] }}</strong>
                </a>
                <a class="operations-row" href="{{ route('admin.reservations', ['payment_due' => 'soon']) }}">
                    <span>Payments due soon</span><strong>{{ $todaySection['payments_due'] }}</strong>
                </a>
            </div>
            <div class="today-upcoming-group">
                <h3>Upcoming</h3>
                <a class="operations-row" href="{{ route('admin.reservations', ['scope' => 'scheduled', 'date_from' => now()->addDay()->toDateString(), 'date_to' => now()->addDays(7)->toDateString()]) }}">
                    <span>Next 7 days</span><strong>{{ $todaySection['upcoming_events'] }}</strong>
                </a>
                <a class="operations-row" href="{{ route('admin.inquiries', ['view' => 'needs_attention']) }}">
                    <span>Inquiries needing response</span><strong>{{ $todaySection['inquiries_needing_response'] }}</strong>
                </a>
            </div>
        </div>
    </section>
</div>

<section class="quick-actions-section" aria-labelledby="quick-actions-title">
    <div class="panel-header"><div><h2 class="h5 fw-bold mb-1" id="quick-actions-title">Quick Actions</h2></div></div>
    <nav class="quick-actions" aria-label="Quick actions">
        <a class="quick-action" href="{{ route('admin.reservations') }}">Review Reservations <span aria-hidden="true">→</span></a>
        <a class="quick-action" href="{{ route('admin.inquiries') }}">Client Inquiries <span aria-hidden="true">→</span></a>
        @if(session('admin_role') === 'full')
            <a class="quick-action" href="{{ route('admin.packages.index') }}">Manage Packages <span aria-hidden="true">→</span></a>
            <a class="quick-action" href="{{ route('admin.reports') }}">View Reports <span aria-hidden="true">→</span></a>
        @endif
    </nav>
</section>
<style>
.operations-summary{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(0,.9fr);align-items:start;gap:1rem;margin-top:1rem}
.attention-card,.today-upcoming-card{height:100%;padding:1rem}
.attention-card .panel-header{align-items:center}
.attention-list{display:grid;gap:.6rem}
.attention-item{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:.75rem 1rem;border:1px solid var(--line);border-radius:10px;background:var(--surface);color:var(--ink);text-decoration:none;transition:border-color .15s ease,background .15s ease}
.attention-item:hover{border-color:var(--teal);background:var(--mint);color:var(--ink)}
.attention-item-label{font-size:.85rem;font-weight:600}
.attention-item-count{display:inline-flex;min-width:26px;height:26px;align-items:center;justify-content:center;padding:0 .5rem;border-radius:999px;background:#fff5d8;color:#714d00;font-weight:800;font-size:.8rem}
body.dark-mode .attention-item-count{background:rgba(146,99,0,.28);color:#f7d57a}
.today-upcoming-groups{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
.today-upcoming-group h3{margin:0 0 .35rem;color:var(--teal-dark);font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
.operations-row{display:flex;justify-content:space-between;align-items:center;gap:.75rem;min-height:42px;padding:.55rem 0;border-bottom:1px solid var(--line);color:var(--ink);font-size:.8rem;text-decoration:none}
.operations-row:last-child{border-bottom:0}
.operations-row strong{flex:none;color:var(--teal-dark);font-size:.95rem;font-variant-numeric:tabular-nums}
.operations-row:hover span{text-decoration:underline;text-underline-offset:3px}
.operations-row:focus-visible,.quick-action:focus-visible{outline:3px solid rgba(34,130,121,.35);outline-offset:2px}
.quick-actions-section{margin-top:1rem}
.quick-actions-section .panel-header{margin-bottom:.5rem}
.quick-actions{display:flex;flex-wrap:wrap;gap:.55rem}
.quick-action{display:inline-flex;min-height:40px;align-items:center;justify-content:space-between;gap:.8rem;padding:.55rem .8rem;border:1px solid var(--line);border-radius:7px;background:var(--surface);color:var(--ink);font-size:.8rem;font-weight:700;text-decoration:none;transition:border-color .15s ease,background .15s ease}
.quick-action:hover{border-color:var(--teal);background:var(--mint);color:var(--ink)}
.quick-action span{color:var(--teal-dark);font-size:1rem}
.calendar-card{overflow:visible}
.calendar-legend{display:flex;align-items:center;flex-wrap:wrap;gap:.9rem;color:var(--muted);font-size:.72rem;font-weight:700}
.calendar-legend span{display:inline-flex;align-items:center;gap:.35rem}
.calendar-dot{width:9px;height:9px;border-radius:50%;background:var(--teal)}
.calendar-dot--pending{background:#d49b28}.calendar-dot--confirmed{background:#0d8b83}.calendar-dot--completed{background:#4d77b8}.calendar-dot--cancelled{background:#c54545}
.calendar-day:focus-within{outline:2px solid #71c9c0;outline-offset:1px}
.calendar-capacity{display:block;margin-top:.2rem;color:var(--teal-dark);font-size:.56rem;font-weight:800;line-height:1.1}
.calendar-capacity--full{color:var(--danger)}
.calendar-grid > .calendar-day:nth-child(7n+4) .calendar-hover-card,.calendar-grid > .calendar-day:nth-child(7n+5) .calendar-hover-card,.calendar-grid > .calendar-day:nth-child(7n+6) .calendar-hover-card,.calendar-grid > .calendar-day:nth-child(7n) .calendar-hover-card{right:0;left:auto}
.calendar-hover-card{max-width:min(240px,calc(100vw - 2rem))}
body.dark-mode .calendar-event--pending{background:rgba(146,99,0,.28);color:#f7d57a}
body.dark-mode .calendar-event--confirmed{background:rgba(13,139,131,.2);color:#a4e2d9}
body.dark-mode .calendar-event--completed{background:rgba(24,76,128,.3);color:#9ad0ff}
body.dark-mode .calendar-event--cancelled{background:rgba(127,34,34,.34);border-color:#ffb0b0;color:#ffb0b0;box-shadow:inset 0 0 0 1px rgba(255,176,176,.2)}
body.dark-mode .calendar-event--cancelled .calendar-event__status{color:#ffb0b0}
.calendar-toolbar{display:flex;align-items:center;justify-content:center;gap:1.25rem;margin-bottom:1rem}.calendar-nav{width:34px;height:34px;border:1px solid var(--line);border-radius:8px;background:var(--surface);color:var(--teal-dark);font-size:1.1rem;line-height:1}.calendar-nav:hover{background:var(--mint)}.calendar-weekdays,.calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}.calendar-weekdays{color:var(--muted);font-size:.68rem;font-weight:800;letter-spacing:.08em;text-align:center;text-transform:uppercase;margin-bottom:6px}.calendar-day{position:relative;min-height:92px;padding:.55rem;background:#fbfcfd;border:1px solid var(--line);border-radius:8px}.calendar-day--empty{background:transparent;border-color:transparent}.calendar-day--today{border-color:#71c9c0;box-shadow:inset 0 0 0 1px #71c9c0}.calendar-day-number{font-size:.78rem;font-weight:800}.calendar-event{display:flex;flex-direction:column;gap:.1rem;width:100%;margin-top:.45rem;padding:.28rem .35rem;border:0;border-left:3px solid;border-radius:4px;background:var(--mint);color:var(--ink);font-size:.68rem;text-align:left;line-height:1.2;white-space:normal;overflow:hidden;word-break:break-word}.calendar-event__title{display:block;font-weight:700;line-height:1.2;color:inherit}.calendar-event__status{display:block;font-size:.52rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:inherit;opacity:1}.calendar-event--pending{border-color:#d49b28}.calendar-event--confirmed{border-color:#0d8b83}.calendar-event--completed{border-color:#4d77b8}.calendar-event--cancelled{border-color:#a73838;background:#f9e0e1;color:#4d1316;box-shadow:inset 0 0 0 1px rgba(167,56,56,.2)}.calendar-event--cancelled .calendar-event__status{color:#6d1818}.calendar-hover-card{position:absolute;z-index:20;top:calc(100% + 6px);left:0;width:240px;padding:.7rem;background:var(--surface);border:1px solid var(--line);border-radius:8px;box-shadow:0 12px 28px rgba(21,37,55,.18);opacity:0;pointer-events:none;transform:translateY(-4px);transition:opacity .15s ease,transform .15s ease}.calendar-day:hover .calendar-hover-card,.calendar-day:focus-within .calendar-hover-card{opacity:1;transform:translateY(0)}.calendar-hover-item{padding:.35rem 0;border-top:1px solid var(--line);font-size:.7rem}.calendar-hover-item:first-child{padding-top:0;border-top:0}.calendar-hover-item strong{display:block}.calendar-hover-item small{display:block;color:var(--muted);margin-top:.12rem}.calendar-legend{display:flex;flex-wrap:wrap;gap:.8rem;color:var(--muted);font-size:.72rem;font-weight:700}.calendar-legend span{display:inline-flex;align-items:center;gap:.35rem}.calendar-dot{width:8px;height:8px;border-radius:50%;display:inline-block}.calendar-dot--pending{background:#d49b28}.calendar-dot--confirmed{background:#0d8b83}.calendar-dot--completed{background:#4d77b8}.calendar-dot--cancelled{background:#c54545}
.calendar-event{cursor:pointer;text-decoration:none;transition:filter .12s ease,box-shadow .12s ease}
.calendar-event:hover,.calendar-event:focus-visible{filter:brightness(.96)}
.calendar-event:focus-visible{outline:2px solid var(--teal-dark);outline-offset:1px}
body.dark-mode .calendar-day{background:#12202e}

@media(max-width:992px){
    .operations-summary{grid-template-columns:1fr}
}

@media(max-width:768px){
    .calendar-day{min-height:72px;padding:.35rem}.calendar-event{font-size:.6rem;padding:.2rem}.calendar-legend{gap:.5rem}
}

@media(max-width:575px){
    .today-upcoming-groups{grid-template-columns:1fr;gap:.7rem}
    .quick-actions{display:grid;grid-template-columns:1fr}
    .calendar-weekdays{font-size:.58rem}.calendar-weekdays,.calendar-grid{gap:3px}.calendar-day{min-height:58px;padding:.25rem}.calendar-day-number{font-size:.68rem}.calendar-event{height:auto;margin-top:.3rem;padding:.24rem .28rem;border-left-width:3px;font-size:.6rem}.calendar-event--cancelled{background:#fff1f1;color:#6b1f1f}.calendar-event--cancelled .calendar-event__status{color:#8d2020}.calendar-event--completed{background:#ecf2ff}.calendar-event--confirmed{background:#e7f7f4}
}
</style>
<script>
(() => {
    const reservations = @json($calendarEvents);
    const capacityByDate = @json($calendarCapacityByDate);
    const maximumCapacity = @json(\App\Models\Reservation::MAX_ACTIVE_RESERVATIONS_PER_DATE);
    // Reuses the existing reservation detail route — '__ID__' is swapped for each event's real
    // reservation id so each calendar event opens that exact reservation.
    const reservationUrlTemplate = @json(route('admin.reservations.show', ['reservation' => '__ID__']));
    const reservationUrl = (id) => reservationUrlTemplate.replace('__ID__', id);
    const calendar = document.getElementById('reservationCalendar');
    const monthLabel = document.getElementById('calendarMonth');
    if (!calendar || !monthLabel) return;

    const today = new Date();
    let displayedMonth = new Date(today.getFullYear(), today.getMonth(), 1);
    const formatter = new Intl.DateTimeFormat('en-US', { month: 'long', year: 'numeric' });
    const statusLabels = { pending: 'Pending', confirmed: 'Accepted', completed: 'Completed', cancelled: 'Cancelled' };
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
    const eventsByDate = reservations.reduce((events, reservation) => {
        (events[reservation.date] ??= []).push(reservation);
        return events;
    }, {});

    const renderCalendar = () => {
        const year = displayedMonth.getFullYear();
        const month = displayedMonth.getMonth();
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const todayKey = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
        monthLabel.textContent = formatter.format(displayedMonth);
        calendar.replaceChildren();

        for (let index = 0; index < firstDay; index += 1) {
            const emptyDay = document.createElement('div');
            emptyDay.className = 'calendar-day calendar-day--empty';
            emptyDay.setAttribute('aria-hidden', 'true');
            calendar.append(emptyDay);
        }

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const dayEvents = eventsByDate[date] ?? [];
            const dayElement = document.createElement('div');
            dayElement.className = `calendar-day${date === todayKey ? ' calendar-day--today' : ''}`;
            const dayNumber = document.createElement('div');
            dayNumber.className = 'calendar-day-number';
            dayNumber.textContent = String(day);
            dayElement.append(dayNumber);

            if (dayEvents.length) {
                const occupied = Number(capacityByDate[date] ?? 0);
                const remaining = Math.max(0, maximumCapacity - occupied);
                const capacityLabel = document.createElement('small');
                capacityLabel.className = `calendar-capacity${remaining === 0 ? ' calendar-capacity--full' : ''}`;
                capacityLabel.textContent = remaining === 0
                    ? `${occupied}/${maximumCapacity} · FULL`
                    : `${occupied}/${maximumCapacity} · ${remaining} slot${remaining === 1 ? '' : 's'} open`;
                dayElement.setAttribute('aria-label', `${date}: ${occupied} of ${maximumCapacity} active reservations, ${remaining} slots available.`);
                dayElement.append(capacityLabel);
            }

            dayEvents.forEach((event) => {
                const eventLink = document.createElement('a');
                eventLink.href = reservationUrl(event.id);
                eventLink.className = `calendar-event calendar-event--${event.status}`;
                eventLink.innerHTML = `
                    <span class="calendar-event__title">${escapeHtml(event.eventType)}</span>
                    ${event.status === 'cancelled' ? '<span class="calendar-event__status">Cancelled</span>' : ''}
                `;
                eventLink.setAttribute('aria-label', `${event.eventType}, ${statusLabels[event.status]}, ${event.time}, ${event.name}, ${event.venue}. Open reservation detail.`);
                dayElement.append(eventLink);
            });

            if (dayEvents.length) {
                const hoverCard = document.createElement('div');
                hoverCard.className = 'calendar-hover-card';
                hoverCard.innerHTML = dayEvents.map((event) => `<div class="calendar-hover-item"><strong>${escapeHtml(event.eventType)} · ${statusLabels[event.status]}</strong><small>${escapeHtml(event.time)} · ${escapeHtml(event.name)}</small><small>${escapeHtml(event.venue)}</small></div>`).join('');
                dayElement.append(hoverCard);
            }

            calendar.append(dayElement);
        }

    };

    document.getElementById('calendarPrevious')?.addEventListener('click', () => {
        displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() - 1, 1);
        renderCalendar();
    });
    document.getElementById('calendarNext')?.addEventListener('click', () => {
        displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() + 1, 1);
        renderCalendar();
    });
    renderCalendar();
})();
</script>
@endsection
