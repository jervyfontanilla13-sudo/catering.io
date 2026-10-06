@extends('layouts.admin')

@section('content')
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
<div class="content-card p-4">
    <div class="page-header">
        <div>
            <a class="back-link" href="{{ route('admin.inquiries') }}">← Back to inquiries</a>
            <h1 class="fw-bold mb-1">{{ $inquiry->subject }}</h1>
            <p class="text-muted mb-2">{{ $inquiry->category }} · received {{ $inquiry->created_at->format('M j, Y') }} · {{ $inquiry->created_at->format('g:i A') }}</p>
            <div class="d-flex flex-wrap gap-2">
                <span class="status-badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Delete this inquiry? This cannot be undone.');">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm">Delete</button>
        </form>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card p-3 mb-4">
                <h5 class="fw-bold mb-3">Customer information</h5>
                <p class="mb-1"><strong>{{ $inquiry->full_name }}</strong></p>
                <p class="mb-1"><a href="mailto:{{ $inquiry->email }}" class="text-decoration-none">{{ $inquiry->email }}</a></p>
                <p class="mb-0"><a href="tel:{{ $inquiry->contact_number }}" class="text-decoration-none">{{ $inquiry->contact_number }}</a></p>
            </div>
            <div class="card p-3">
                <h5 class="fw-bold mb-3">Inquiry information</h5>
                <dl class="inquiry-info-list mb-3">
                    <div><dt>Subject</dt><dd>{{ $inquiry->subject }}</dd></div>
                    <div><dt>Category</dt><dd>{{ $inquiry->category }}</dd></div>
                    <div><dt>Received</dt><dd>{{ $inquiry->created_at->format('M j, Y') }} &middot; {{ $inquiry->created_at->format('g:i A') }}</dd></div>
                    <div><dt>Status</dt><dd><span class="status-badge {{ $statusBadgeClass }}">{{ $statusLabel }}</span></dd></div>
                </dl>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card p-3 mb-4">
                <h5 class="fw-bold mb-3">Conversation</h5>
                <div class="conversation-entry">
                    <div class="conversation-entry-head"><span class="conversation-badge conversation-badge--customer">Customer</span><span class="text-muted small">{{ $inquiry->created_at->format('M j, Y') }} &middot; {{ $inquiry->created_at->format('g:i A') }}</span></div>
                    <p class="conversation-body">{{ $inquiry->message }}</p>
                </div>
                @if($inquiry->admin_reply && $inquiry->replied_at)
                    <hr>
                    <div class="conversation-entry">
                        <div class="conversation-entry-head"><span class="conversation-badge conversation-badge--admin">Admin response</span><span class="text-muted small">{{ $inquiry->replied_at->format('M j, Y') }} &middot; {{ $inquiry->replied_at->format('g:i A') }}</span></div>
                        <p class="conversation-body">{{ $inquiry->admin_reply }}</p>
                    </div>
                @endif
            </div>

            <div class="card p-3">
                <h5 class="fw-bold mb-2">Reply by email</h5>
                <p class="text-muted small mb-3">Sending records the reply and changes this inquiry to Responded.</p>
                <form method="POST" action="{{ route('admin.inquiries.reply', $inquiry) }}" id="inquiry-reply-form">
                    @csrf
                    {{-- A previously *sent* reply lives in the conversation thread above, not here. But if the
                    last send attempt failed, admin_reply holds that unsent draft with no replied_at — restore it
                    so the admin doesn't lose what they typed. --}}
                    <textarea class="form-control @error('reply') is-invalid @enderror" name="reply" rows="8" required style="resize:vertical;min-height:200px">{{ old('reply', $inquiry->replied_at ? '' : $inquiry->admin_reply) }}</textarea>
                    @error('reply')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <button class="btn luxury-btn mt-3 w-100 w-sm-auto" type="submit" id="inquiry-reply-submit">Send reply</button>
                </form>
                <script>
                    (function () {
                        var form = document.getElementById('inquiry-reply-form');
                        var button = document.getElementById('inquiry-reply-submit');
                        if (!form || !button) return;
                        var defaultLabel = button.textContent;
                        form.addEventListener('submit', function (event) {
                            if (!form.checkValidity() || button.disabled) return;
                            button.disabled = true;
                            button.textContent = 'Sending...';
                        });
                        // If the browser restores this page from cache (e.g. the back button) after a failed
                        // submission, re-enable the button instead of leaving it stuck on "Sending...".
                        window.addEventListener('pageshow', function () {
                            button.disabled = false;
                            button.textContent = defaultLabel;
                        });
                    })();
                </script>
            </div>
        </div>
    </div>
</div>

<style>
    .inquiry-info-list { display: grid; gap: .55rem; margin: 0; }
    .inquiry-info-list dt { font-size: .68rem; font-weight: 800; letter-spacing: .05em; text-transform: uppercase; color: var(--muted); }
    .inquiry-info-list dd { margin: .1rem 0 0; }
    .conversation-entry { padding: .25rem 0; }
    .conversation-entry-head { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-bottom: .5rem; }
    .conversation-badge { display: inline-flex; height: 22px; align-items: center; padding: 0 .6rem; border-radius: 999px; font-size: .66rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
    .conversation-badge--customer { background: var(--mint); color: var(--teal-dark); }
    .conversation-badge--admin { background: #eef2f4; color: #4c6073; }
    body.dark-mode .conversation-badge--admin { background: #20323d; color: #c9d6dc; }
    .conversation-body { margin: 0; white-space: pre-line; word-break: break-word; overflow-wrap: break-word; color: var(--ink); }
</style>
@endsection
