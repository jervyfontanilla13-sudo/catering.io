@extends('layouts.admin')

@section('content')
<div class="content-card p-4">
    <div class="page-header"><div>
        <a class="back-link" href="{{ route('admin.reservations') }}">Back to reservations</a>
        <h1 class="fw-bold mb-1">Add reservation</h1>
        <p class="text-muted mb-0">Create a pending booking. You can accept it from the reservations list.</p>
    </div></div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <form method="POST" action="{{ route('admin.reservations.store') }}" id="admin-reservation-form">
        @csrf
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="full-name">Customer name</label>
                <input id="full-name" name="full_name" class="form-control" value="{{ old('full_name') }}" placeholder="Juan dela Cruz" autocomplete="name" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="contact-number">Contact number</label>
                <input id="contact-number" name="contact_number" type="tel" class="form-control" value="{{ old('contact_number') }}" inputmode="tel" minlength="11" maxlength="13" pattern="(?:\+63[0-9]{10}|09[0-9]{9})" placeholder="09XXXXXXXXX or +639XXXXXXXXX" title="Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces." required>
                <small class="form-text">Enter 09 followed by 9 digits or +63 followed by 10 digits.</small>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="email">Email address</label>
                <input id="email" name="email" type="email" class="form-control" value="{{ old('email') }}" autocomplete="email" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="address">Complete address</label>
                <input id="address" name="address" class="form-control" value="{{ old('address') }}" autocomplete="street-address" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="event-type">Event type</label>
                <select id="event-type" name="event_type" class="form-select" required>
                    <option value="">Select event type</option>
                    @foreach(['Wedding', 'Birthday', 'Debut', 'Anniversary', 'Corporate Event', 'Baptism', 'Graduation', 'Other'] as $eventType)
                        <option value="{{ $eventType }}" @selected(old('event_type') === $eventType)>{{ $eventType }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="package-id">Catering package</label>
                <select id="package-id" name="package_id" class="form-select" required>
                    <option value="">Select a package</option>
                    @foreach($packages as $package)
                        <option value="{{ $package->id }}" data-price="{{ $package->price }}" @selected(old('package_id') == $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="event-date">Event date</label>
                <input id="event-date" name="event_date" type="date" class="form-control" value="{{ old('event_date') }}" required>
            </div>
            <div class="col-md-6">
                @include('components.clock-time-picker', ['inputId' => 'event-time', 'name' => 'event_time', 'label' => 'Event time', 'value' => old('event_time')])
            </div>
            <div class="col-md-6">
                <label class="form-label" for="venue">Venue</label>
                <input id="venue" name="venue" class="form-control" value="{{ old('venue') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="guest-count">Expected guests</label>
                <input id="guest-count" name="guest_count" type="number" min="1" max="1000" class="form-control" value="{{ old('guest_count') }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="estimate">Estimated package total</label>
                <output id="estimate" class="form-control" aria-live="polite">Select a package and guest count.</output>
            </div>
            <div class="col-12">
                <label class="form-label" for="additional-services">Additional services</label>
                <textarea id="additional-services" name="additional_services" class="form-control" rows="2">{{ old('additional_services') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="special-requests">Special requests</label>
                <textarea id="special-requests" name="special_requests" class="form-control" rows="3">{{ old('special_requests') }}</textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="additional-notes">Additional notes</label>
                <textarea id="additional-notes" name="additional_notes" class="form-control" rows="3">{{ old('additional_notes') }}</textarea>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2">
                <button type="submit" class="btn luxury-btn">Create reservation</button>
                <a class="btn btn-outline-secondary" href="{{ route('admin.reservations') }}">Cancel</a>
            </div>
        </div>
    </form>
</div>

<script>
(() => {
    const packageInput = document.getElementById('package-id');
    const guestInput = document.getElementById('guest-count');
    const estimate = document.getElementById('estimate');
    const formatCurrency = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });

    const updateEstimate = () => {
        const option = packageInput.selectedOptions[0];
        const guestCount = Number(guestInput.value);
        estimate.textContent = option?.value && guestCount
            ? formatCurrency.format(Number(option.dataset.price) * guestCount)
            : 'Select a package and guest count.';
    };

    packageInput.addEventListener('change', updateEstimate);
    guestInput.addEventListener('input', updateEstimate);
    updateEstimate();
})();
</script>
@endsection