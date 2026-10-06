@php
    $statusBadgeClass = match ($inquiry->status) {
        'new' => 'status-badge--pending',
        'in_progress' => 'status-badge--completed',
        'responded' => 'status-badge--confirmed',
        'closed' => 'status-badge--neutral',
        default => 'status-badge--neutral',
    };
    $statusLabel = ucwords(str_replace('_', ' ', $inquiry->status));
@endphp
<article class="inquiry-card {{ $inquiry->isUnread() ? 'inquiry-card--unread' : '' }}">
    <div class="inquiry-card-identity">
        @if($inquiry->isUnread())
            <span class="inquiry-unread-dot" aria-hidden="true" title="Unread"></span>
        @endif
        <div class="inquiry-card-identity-text">
            <h3 class="inquiry-card-name">{{ $inquiry->full_name }}</h3>
            <p class="inquiry-card-subject">{{ $inquiry->subject }}</p>
        </div>
    </div>
    <div class="inquiry-card-meta">
        <div class="inquiry-card-meta-item">
            <span>Received</span>
            <strong>{{ $inquiry->created_at->format('M j, Y') }} &middot; {{ $inquiry->created_at->format('g:i A') }}</strong>
        </div>
        <div class="inquiry-card-meta-item">
            <span>Last response</span>
            <strong>{{ $inquiry->replied_at ? $inquiry->replied_at->format('M j, Y').' · '.$inquiry->replied_at->format('g:i A') : 'No response yet' }}</strong>
        </div>
    </div>
    <div class="inquiry-card-badges">
        <span class="status-badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
    </div>
    <div class="inquiry-card-action">
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.inquiries.show', $inquiry) }}">View</a>
    </div>
</article>
