@extends('layouts.app')

@section('title', 'Book an event | 3YOS Catering')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="page-heading">
        <div class="eyebrow">Reservation request</div>
        <h1 class="fw-bold mt-2 mb-2">Plan your perfect event.</h1>
        <p class="text-muted mb-0">Share a few details about your event and our team will review your request.</p>
        <p class="small mt-2 mb-0"><a href="{{ route('support') }}#category-reservations">Need help? Learn how reservations work &rarr;</a></p>
    </div>

    @if($reservation)
        @php($statusLabel = match($reservation->status) {
            'pending' => 'Under review',
            'confirmed' => 'Accepted',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => ucfirst($reservation->status),
        })
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="form-card p-4 p-lg-5 confirmation-card">
                    <div class="confirmation-header">
                        <div class="confirmation-icon" aria-hidden="true">&#10003;</div>
                        <span class="eyebrow">Reservation request received</span>
                        <h1 class="fw-bold mt-2 mb-2">Thanks, {{ explode(' ', trim($reservation->full_name))[0] }}.</h1>
                        <p class="text-muted mb-0">This confirms we received your request — it does not mean your event is confirmed yet. Our team still needs to review availability and reach out to you.</p>
                    </div>

                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif

                    <div class="confirmation-id-block">
                        <span>Reservation ID</span>
                        <strong>{{ $reservation->reservation_code }}</strong>
                    </div>

                    @include('public.partials.reservation-timeline')

                    <div class="confirmation-details">
                        <div class="confirmation-detail"><span>Event</span><strong>{{ $reservation->event_type }}</strong></div>
                        <div class="confirmation-detail"><span>Date</span><strong>{{ \Carbon\Carbon::parse($reservation->event_date)->format('F j, Y') }}</strong></div>
                        <div class="confirmation-detail"><span>Time</span><strong>{{ \Carbon\Carbon::parse($reservation->event_time)->format('g:i A') }}</strong></div>
                        <div class="confirmation-detail"><span>Venue</span><strong>{{ $reservation->venue }}</strong></div>
                        <div class="confirmation-detail"><span>Guests</span><strong>{{ number_format($reservation->guest_count) }}</strong></div>
                        <div class="confirmation-detail"><span>Package</span><strong>{{ $reservation->package?->name ?? 'Custom package' }}</strong></div>
                        <div class="confirmation-detail"><span>Estimated amount</span><strong>&#8369;{{ number_format($reservation->estimated_budget, 2) }}</strong></div>
                        <div class="confirmation-detail"><span>Current status</span><strong>{{ $statusLabel }}</strong></div>
                    </div>

                    <div class="confirmation-next">
                        <h2 class="h6 fw-bold mb-2">What happens next</h2>
                        <ol>
                            <li>Your reservation request has been submitted.</li>
                            <li>Our team reviews availability for your date.</li>
                            <li>We contact you to go over the details.</li>
                            <li>Your reservation is accepted and finalized.</li>
                            <li>Any contract or payment arrangements are handled directly with our team.</li>
                            <li>Your event is completed.</li>
                        </ol>
                    </div>

                    <div class="confirmation-actions">
                        <a href="{{ route('reservation.status', ['code' => $reservation->reservation_code]) }}" class="btn btn-primary">Check status</a>
                        <a href="{{ route('home') }}" class="btn btn-outline-primary">Back to website</a>
                        <button type="button" class="btn btn-outline-secondary confirmation-print" onclick="window.print()">Print confirmation</button>
                    </div>
                    <p class="text-center text-muted small mt-3 mb-0"><a href="{{ route('reservation') }}">Make another reservation request</a></p>
                </div>
            </div>
        </div>
    @else
        <div class="row g-4 g-lg-5 reservation-shell">
            <div class="col-lg-4">
                <aside class="reservation-sidebar">
                    <div class="eyebrow mb-2">Before you submit</div>
                    <h2>We’ll make it easy.</h2>
                    <ul class="reservation-checklist">
                        <li>Tell us your guest count and preferred date.</li>
                        <li>Choose a package that fits your budget and vibe.</li>
                        <li>We’ll confirm availability and recommend the right setup.</li>
                    </ul>
                    <div class="reservation-tile">
                        <strong>Best for</strong>
                        <p>Weddings, debut parties, birthdays, corporate events, and intimate family celebrations.</p>
                    </div>
                </aside>
            </div>

            <div class="col-lg-8">
                <div class="form-card p-4 p-lg-5">
                    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error === 'This date is fully booked. Please choose another date.' ? 'This date is currently fully booked. Please select another available date.' : $error }}</li>@endforeach</ul></div>@endif
                    @if(session('success'))
                        <div id="reservation-toast" class="floating-toast show" role="alert" aria-live="assertive" aria-atomic="true">
                            <div class="floating-toast__icon">✓</div>
                            <div class="floating-toast__content">
                                <strong>Reservation sent</strong>
                                <div>{{ session('success') }}</div>
                            </div>
                            <button type="button" class="floating-toast__close" aria-label="Close notification">&times;</button>
                        </div>
                    @endif

                    <div class="wizard-stepper" role="tablist" aria-label="Reservation steps">
                        <div class="wizard-stepper-item" data-stepper-step="1"><span class="wizard-stepper-num">1</span><span class="wizard-stepper-label">Event</span></div>
                        <div class="wizard-stepper-item" data-stepper-step="2"><span class="wizard-stepper-num">2</span><span class="wizard-stepper-label">Package</span></div>
                        <div class="wizard-stepper-item" data-stepper-step="3"><span class="wizard-stepper-num">3</span><span class="wizard-stepper-label">Customer</span></div>
                        <div class="wizard-stepper-item" data-stepper-step="4"><span class="wizard-stepper-num">4</span><span class="wizard-stepper-label">Review</span></div>
                    </div>

                    <form method="POST" action="{{ route('reservation.store') }}" id="reservation-form">@csrf<input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off"><input type="hidden" name="submission_key" value="{{ old('submission_key', $submissionKey) }}"><input type="hidden" name="form_started" value="{{ now()->timestamp }}">

                        <fieldset class="wizard-step" data-step="1">
                            <legend class="wizard-step-title">Tell us about your event</legend>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Event type</label><select name="event_type" class="form-select" required><option value="">Select an event type</option>@foreach(['Wedding','Birthday','Debut','Anniversary','Corporate Event','Baptism','Graduation','Other'] as $type)<option value="{{ $type }}" @selected(old('event_type') === $type)>{{ $type }}</option>@endforeach</select></div>
                                <div class="col-md-6"><label class="form-label" for="event_date">Event date</label><input type="date" name="event_date" id="event_date" value="{{ old('event_date') }}" min="{{ now()->addDays(2)->toDateString() }}" class="form-control" aria-describedby="date-availability" required><div id="date-availability" class="date-availability form-text" role="status" aria-live="polite" aria-atomic="true">Choose a date at least 2 days in advance.</div></div>
                                <div class="col-md-6 clock-time-field">
                                    <label class="form-label" for="event_time">Event time</label>
                                    <input type="time" name="event_time" id="event_time" value="{{ old('event_time') }}" class="form-control" required>
                                    <div class="clock-time-picker" id="clock-time-picker" hidden>
                                        <button type="button" id="clock-time-toggle" class="form-control clock-time-toggle" aria-haspopup="dialog" aria-expanded="false" aria-required="true" aria-controls="clock-time-panel">
                                            <span id="clock-time-value">Select a time</span><span class="clock-time-icon" aria-hidden="true"></span>
                                        </button>
                                        <section class="clock-time-panel" id="clock-time-panel" role="dialog" aria-label="Select event time" hidden>
                                            <div class="clock-time-header">
                                                <div class="clock-time-readout">
                                                    <button type="button" id="clock-current-hour" aria-label="Choose hour">12</button>
                                                    <span>:</span>
                                                    <button type="button" id="clock-current-minute" aria-label="Choose minutes">00</button>
                                                </div>
                                                <div class="clock-time-period" role="group" aria-label="AM or PM">
                                                    <button type="button" data-clock-period="AM" aria-pressed="true">AM</button>
                                                    <button type="button" data-clock-period="PM" aria-pressed="false">PM</button>
                                                </div>
                                            </div>
                                            <div class="clock-time-face" id="clock-time-face" role="group" aria-label="Choose hour">
                                                <div class="clock-time-hand" id="clock-time-hand"></div>
                                                <div id="clock-time-numbers"></div>
                                            </div>
                                            <div class="clock-time-actions">
                                                <button type="button" id="clock-time-cancel" class="btn btn-outline-secondary btn-sm">Cancel</button>
                                                <button type="button" id="clock-time-apply" class="btn btn-primary btn-sm">Use this time</button>
                                            </div>
                                        </section>
                                    </div>
                                    <small id="clock-time-error" class="form-text text-danger" hidden>Select an event time to continue.</small>
                                    <small id="event-time-help" class="form-text">Use your device's time picker.</small>
                                </div>
                                <div class="col-md-6"><label class="form-label">Venue</label><input type="text" name="venue" value="{{ old('venue') }}" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label">Expected guests</label><input type="number" name="guest_count" id="guest_count" value="{{ old('guest_count', request()->query('guests')) }}" min="1" max="1000" class="form-control" required><small class="form-text">Enter the total number of attendees.</small></div>
                            </div>
                            <div class="wizard-actions"><span></span><button type="button" class="btn btn-primary wizard-next" data-next="2" disabled>Next: Package</button></div>
                        </fieldset>

                        <fieldset class="wizard-step" data-step="2" hidden>
                            <legend class="wizard-step-title">Choose your package</legend>
                            <div class="row g-3">
                                @php($preselectedPackageId = old('package_id', request()->query('package')))
                                @php($selectedPackage = $packages->firstWhere('id', $preselectedPackageId))
                                <div class="col-12">
                                    <div class="form-label" id="package-field-label">Catering package</div>
                                    <select name="package_id" id="package_id" class="form-select" aria-labelledby="package-field-label" required @if($selectedPackage) hidden @endif>
                                        <option value="">Select a package</option>
                                        @foreach($packages as $package)
                                            <option value="{{ $package->id }}" data-price="{{ $package->price }}" data-name="{{ $package->name }}" data-description="{{ $package->description }}" data-details-url="{{ route('packages.show', $package->slug) }}" @selected((string) $preselectedPackageId === (string) $package->id)>{{ $package->name }} — &#8369;{{ number_format($package->price, 2) }} / person</option>
                                        @endforeach
                                    </select>
                                    @if($preselectedPackageId && ! $selectedPackage)
                                        <small class="form-text text-danger" role="alert">The selected package is no longer available. Please choose another package.</small>
                                    @endif
                                    <small id="package-selection-error" class="form-text text-danger" hidden>Please select a catering package.</small>
                                    <div class="selected-package-card" id="selected-package-card" role="group" aria-labelledby="package-field-label selected-package-name" @if(! $selectedPackage) hidden @endif>
                                        <div>
                                            <strong id="selected-package-name">{{ $selectedPackage?->name }}</strong>
                                            <span id="selected-package-price">
                                                @if($selectedPackage)
                                                    &#8369;{{ number_format($selectedPackage->price, 2) }} / person
                                                @endif
                                            </span>
                                            <p id="selected-package-description" @if(! $selectedPackage?->description) hidden @endif>{{ $selectedPackage?->description }}</p>
                                        </div>
                                        <div class="selected-package-actions">
                                            <a id="view-package-details" class="package-selection-link" href="{{ $selectedPackage ? route('packages.show', $selectedPackage->slug) : '#' }}" @if(! $selectedPackage) hidden @endif>View package details</a>
                                            <button id="change-package" class="btn btn-outline-primary" type="button" aria-controls="package_id" aria-expanded="false">Choose a different package</button>
                                        </div>
                                    </div>
                                    @if(! $preselectedPackageId || $selectedPackage)
                                        <small class="form-text" id="package-selection-help" @if($selectedPackage) hidden @endif>Choose a package or <a class="package-selection-link" href="{{ route('packages') }}">review package details</a>.</small>
                                    @endif
                                </div>
                                <div class="col-md-6"><label class="form-label" for="estimated-budget-display">Estimated package total</label><output id="estimated-budget-display" class="form-control" aria-live="polite">Choose a package and guest count to see an estimate.</output><small class="form-text">Calculated automatically from the package rate and guest count; the final contract price is confirmed by our team.</small></div>
                                <div class="col-12"><label class="form-label">Additional services</label><textarea name="additional_services" class="form-control" rows="2">{{ old('additional_services') }}</textarea></div>
                                <div class="col-12"><label class="form-label">Special requests</label><textarea name="special_requests" class="form-control" rows="2">{{ old('special_requests') }}</textarea></div>
                            </div>
                            <div class="wizard-actions"><button type="button" class="btn btn-outline-secondary wizard-back" data-back="1">Back</button><button type="button" class="btn btn-primary wizard-next" data-next="3">Next: Your details</button></div>
                        </fieldset>

                        <fieldset class="wizard-step" data-step="3" hidden>
                            <legend class="wizard-step-title">Your details</legend>
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label">Full name</label><input type="text" name="full_name" value="{{ old('full_name') }}" class="form-control" placeholder="Juan dela Cruz" autocomplete="name" required></div>
                                <div class="col-md-6"><label class="form-label">Contact number</label><input type="tel" name="contact_number" value="{{ old('contact_number') }}" class="form-control" inputmode="tel" autocomplete="tel" minlength="11" maxlength="13" pattern="(?:\+63[0-9]{10}|09[0-9]{9})" placeholder="09XXXXXXXXX or +639XXXXXXXXX" title="Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces." required><small class="form-text">Enter 09 followed by 9 digits or +63 followed by 10 digits.</small></div>
                                <div class="col-md-6"><label class="form-label">Email address</label><input type="email" name="email" value="{{ old('email') }}" class="form-control" required></div>
                                <div class="col-md-6"><label class="form-label">Complete address</label><input type="text" name="address" value="{{ old('address') }}" class="form-control" required></div>
                                <div class="col-12"><label class="form-label">Additional notes</label><textarea name="additional_notes" class="form-control" rows="2">{{ old('additional_notes') }}</textarea></div>
                            </div>
                            <div class="wizard-actions"><button type="button" class="btn btn-outline-secondary wizard-back" data-back="2">Back</button><button type="button" class="btn btn-primary wizard-next" data-next="4">Next: Review</button></div>
                        </fieldset>

                        <fieldset class="wizard-step" data-step="4" hidden>
                            <legend class="wizard-step-title">Review your reservation</legend>
                            <p class="text-muted small mb-3">Check everything below before submitting. Use Edit to change any section.</p>
                            <div class="review-grid">
                                <div class="review-block">
                                    <div class="review-block-head"><h3>Event</h3><button type="button" class="review-edit" data-edit-step="1">Edit</button></div>
                                    <dl class="review-list">
                                        <div><dt>Event type</dt><dd id="review-event_type">—</dd></div>
                                        <div><dt>Date</dt><dd id="review-event_date">—</dd></div>
                                        <div><dt>Time</dt><dd id="review-event_time">—</dd></div>
                                        <div><dt>Venue</dt><dd id="review-venue">—</dd></div>
                                        <div><dt>Guests</dt><dd id="review-guest_count">—</dd></div>
                                    </dl>
                                </div>
                                <div class="review-block">
                                    <div class="review-block-head"><h3>Package</h3><button type="button" class="review-edit" data-edit-step="2">Edit</button></div>
                                    <dl class="review-list">
                                        <div><dt>Package</dt><dd id="review-package">—</dd></div>
                                        <div><dt>Estimated total</dt><dd id="review-estimate">—</dd></div>
                                        <div><dt>Additional services</dt><dd id="review-additional_services">—</dd></div>
                                        <div><dt>Special requests</dt><dd id="review-special_requests">—</dd></div>
                                    </dl>
                                </div>
                                <div class="review-block">
                                    <div class="review-block-head"><h3>Customer</h3><button type="button" class="review-edit" data-edit-step="3">Edit</button></div>
                                    <dl class="review-list">
                                        <div><dt>Full name</dt><dd id="review-full_name">—</dd></div>
                                        <div><dt>Contact number</dt><dd id="review-contact_number">—</dd></div>
                                        <div><dt>Email</dt><dd id="review-email">—</dd></div>
                                        <div><dt>Address</dt><dd id="review-address">—</dd></div>
                                        <div><dt>Additional notes</dt><dd id="review-additional_notes">—</dd></div>
                                    </dl>
                                </div>
                            </div>
                            <div class="mt-3">
                                <div data-recaptcha-container>
                                    <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                                    <p class="form-text mt-2 mb-0" data-recaptcha-status role="status" aria-live="polite" hidden></p>
                                </div>
                                @error('g-recaptcha-response')
                                    <div class="alert alert-danger mt-2">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="wizard-actions"><button type="button" class="btn btn-outline-secondary wizard-back" data-back="3">Back</button><button type="submit" id="submit-reservation" class="btn btn-primary">Submit reservation request</button></div>
                        </fieldset>
                    </form>
                </div>
            </div>
        </div>
    @endif
</div>

<style>
    .page-heading{padding:1rem 0 2rem}
    .page-heading h1{font-size:clamp(2.4rem,4vw,4rem);line-height:1.05;letter-spacing:-.04em}
    .reservation-shell{margin-top:1rem}
    .reservation-sidebar{background:linear-gradient(160deg,#f5ebdf,#f0e2d0);border:1px solid rgba(109,48,36,.08);border-radius:24px;padding:2rem;position:sticky;top:90px}
    .reservation-sidebar h2{font-size:clamp(1.8rem,2vw,2.3rem);margin-bottom:1rem}
    .reservation-checklist{list-style:none;padding:0;margin:1rem 0 1.5rem;display:grid;gap:.9rem}
    .reservation-checklist li{position:relative;padding-left:1.8rem;color:var(--muted);line-height:1.6}
    .reservation-checklist li:before{content:'✓';position:absolute;left:0;top:0;color:var(--wine);font-weight:800}
    .reservation-tile{margin-top:1rem;padding:1.2rem;background:#f7efe7;border-radius:18px;border:1px solid rgba(109,48,36,.08)}
    .reservation-tile strong{display:block;margin-bottom:.25rem}
    .reservation-tile p{margin:0;color:var(--muted);line-height:1.6}
    .form-card{background:#fdf9f5;border:1px solid rgba(109,48,36,.08);border-radius:24px;box-shadow:0 22px 50px rgba(32,32,29,.06)}
    .form-label{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
    .form-control,.form-select{border:1px solid #e7ddd0;border-radius:14px;padding:.8rem 1rem;background:#fff;color:var(--ink)}
    .form-control:focus,.form-select:focus{border-color:var(--wine);box-shadow:0 0 0 .2rem rgba(109,48,36,.12)}
    .date-availability{display:block;margin-top:.45rem;font-size:.82rem}

    .wizard-stepper{display:flex;gap:.5rem;margin-bottom:2rem}
    .wizard-stepper-item{flex:1;display:flex;flex-direction:column;align-items:center;gap:.4rem;text-align:center;opacity:.55}
    .wizard-stepper-item.is-active,.wizard-stepper-item.is-complete{opacity:1}
    .wizard-stepper-num{display:grid;place-items:center;width:30px;height:30px;border-radius:50%;border:2px solid #e7ddd0;background:#fff;color:var(--muted);font-weight:800;font-size:.85rem}
    .wizard-stepper-item.is-active .wizard-stepper-num{border-color:var(--wine);color:var(--wine)}
    .wizard-stepper-item.is-complete .wizard-stepper-num{border-color:var(--wine);background:var(--wine);color:#fff}
    .wizard-stepper-label{font-size:.68rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
    .wizard-stepper-item.is-active .wizard-stepper-label{color:var(--wine)}
    .wizard-step-title{padding:0;margin:0 0 1.25rem;font-family:'Playfair Display',Georgia,serif;font-size:1.4rem;color:var(--ink);border:0}
    .wizard-actions{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:1.75rem;padding-top:1.5rem;border-top:1px solid rgba(109,48,36,.08)}
    .wizard-actions .btn-primary{margin-left:auto}
    .wizard-step[data-step="1"] .wizard-next:disabled,
    .wizard-step[data-step="1"] .wizard-next:disabled:hover,
    .wizard-step[data-step="1"] .wizard-next:disabled:focus{background-color:color-mix(in srgb,var(--wine) 55%,var(--muted));border-color:color-mix(in srgb,var(--wine) 55%,var(--muted));color:var(--paper);opacity:.72;box-shadow:none;transform:none;cursor:not-allowed}

    .review-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem}
    .review-block{padding:1.1rem 1.25rem;border:1px solid #e7ddd0;border-radius:16px;background:#fff;color:#20201d}
    .review-block-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:.75rem}
    .review-block-head h3{margin:0;font-family:'Playfair Display',Georgia,serif;font-size:1.05rem;color:#6d3024}
    .review-edit{border:0;background:transparent;color:#6d3024;font-size:.76rem;font-weight:700;text-decoration:underline;cursor:pointer;padding:0}
    .review-list{display:grid;gap:.55rem;margin:0}
    .review-list dt{font-size:.68rem;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:#6f6d66}
    .review-list dd{margin:.15rem 0 0;overflow-wrap:anywhere;color:#20201d}

    .confirmation-card{max-width:820px;margin:0 auto}
    .confirmation-header{text-align:center;padding-bottom:1.5rem;border-bottom:1px solid rgba(109,48,36,.1)}
    .confirmation-icon{display:grid;place-items:center;width:52px;height:52px;margin:0 auto .75rem;border-radius:50%;background:#e3f6ea;color:#0a5a35;font-size:1.4rem;font-weight:800}
    .confirmation-header p{max-width:560px;margin:0 auto}
    .confirmation-id-block{display:flex;flex-wrap:wrap;align-items:baseline;justify-content:center;gap:.5rem;margin:1.5rem 0;padding:1rem;border-radius:14px;background:#f7efe7;text-align:center}
    .confirmation-id-block span{font-size:.72rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
    .confirmation-id-block strong{font-family:'Playfair Display',Georgia,serif;font-size:1.4rem;color:var(--wine)}
    .confirmation-details{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:1px;background:#e7ddd0;border:1px solid #e7ddd0;border-radius:14px;overflow:hidden;margin-bottom:1.75rem}
    .confirmation-detail{padding:.9rem 1rem;background:#fff}
    .confirmation-detail span{display:block;margin-bottom:.3rem;font-size:.66rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:var(--muted)}
    .confirmation-detail strong{display:block;overflow-wrap:anywhere}
    .confirmation-next{padding:1.1rem 1.25rem;border-radius:14px;background:#f7efe7;margin-bottom:1.75rem}
    .confirmation-next ol{margin:0;padding-left:1.2rem;display:grid;gap:.4rem;color:var(--ink)}
    .confirmation-actions{display:flex;flex-wrap:wrap;gap:.75rem;justify-content:center}
    body.dark-mode .confirmation-icon{background:#18402a;color:#8fe3aa}
    body.dark-mode .confirmation-id-block,body.dark-mode .confirmation-next{background:#29231f}
    body.dark-mode .confirmation-detail{background:var(--paper)}

    .clock-time-field{position:relative;z-index:2}
    .clock-time-picker{position:relative}
    .clock-time-toggle{display:flex;align-items:center;justify-content:space-between;width:100%;text-align:left}
    .clock-time-icon{position:relative;width:18px;height:18px;flex:0 0 18px;border:1.5px solid currentColor;border-radius:50%}
    .clock-time-icon:before,.clock-time-icon:after{position:absolute;left:50%;top:50%;width:1.5px;background:currentColor;content:'';transform-origin:50% 0}
    .clock-time-icon:before{height:5px;transform:translate(-50%,-1px)}
    .clock-time-icon:after{height:4px;transform:translate(-50%,-1px) rotate(120deg)}
    .clock-time-panel{position:fixed;left:16px;top:16px;z-index:1080;width:min(320px,calc(100vw - 2rem));padding:1rem;border:1px solid #e7ddd0;border-radius:14px;background:#fff;box-shadow:0 18px 42px rgba(32,32,29,.2)}
    .clock-time-header{display:flex;align-items:center;justify-content:center;gap:.75rem;margin-bottom:.8rem}
    .clock-time-readout{display:flex;align-items:center;gap:.15rem;color:var(--wine);font-size:1.65rem;font-weight:700}
    .clock-time-readout button{min-width:2.5rem;padding:.2rem .3rem;border:0;border-radius:6px;background:transparent;color:inherit;font:inherit}
    .clock-time-readout button[aria-pressed="true"],.clock-time-readout button:hover{background:#f6eee5}
    .clock-time-period{display:grid;gap:.18rem}
    .clock-time-period button{padding:.15rem .4rem;border:1px solid #e7ddd0;border-radius:5px;background:#fff;color:var(--muted);font-size:.68rem;font-weight:700}
    .clock-time-period button[aria-pressed="true"]{border-color:var(--wine);background:var(--wine);color:#fff}
    .clock-time-face{--clock-number-radius:72px;position:relative;width:min(230px,100%);aspect-ratio:1;margin:0 auto .85rem;border-radius:50%;background:#f6eee5}
    .clock-time-hand{position:absolute;left:calc(50% - 1px);top:50%;z-index:0;width:2px;height:37%;border-radius:2px;background:var(--terracotta);transform:rotate(180deg);transform-origin:50% 0;pointer-events:none}
    .clock-time-hand:after{position:absolute;left:50%;bottom:-4px;width:9px;height:9px;border-radius:50%;background:var(--wine);content:'';transform:translateX(-50%)}
    .clock-time-number{position:absolute;left:50%;top:50%;z-index:1;display:grid;place-items:center;width:36px;height:36px;padding:0;border:0;border-radius:50%;background:transparent;color:var(--ink);font-size:.83rem;font-weight:700;transform:translate(-50%,-50%) rotate(var(--clock-angle)) translateY(calc(-1 * var(--clock-number-radius))) rotate(calc(var(--clock-angle) * -1))}
    .clock-time-number:hover,.clock-time-number:focus-visible,.clock-time-number[aria-pressed="true"]{outline:0;background:var(--wine);color:#fff}
    .clock-time-actions{display:flex;justify-content:flex-end;gap:.5rem}
    body.dark-mode .clock-time-toggle{border-color:#555047;background:#201f1d;color:#f5f1e9}
    body.dark-mode .clock-time-panel{border-color:#4f4942;background:#201f1d;color:#f5f1e9}
    body.dark-mode .clock-time-readout button[aria-pressed="true"],body.dark-mode .clock-time-readout button:hover{background:#332820}
    body.dark-mode .clock-time-period button{border-color:#4f4942;background:#201f1d;color:#c9c3b9}
    body.dark-mode .clock-time-period button[aria-pressed="true"]{border-color:var(--wine);background:var(--wine);color:#fff}
    body.dark-mode .clock-time-face{background:#29231f}
    body.dark-mode .clock-time-number{color:#f5f1e9}
    body.dark-mode .clock-time-number:hover,body.dark-mode .clock-time-number:focus-visible,body.dark-mode .clock-time-number[aria-pressed="true"]{background:#b66545;color:#fff}
    .selected-package-card{display:flex;align-items:center;justify-content:space-between;gap:1.25rem;margin-top:1rem;padding:1.1rem 1.2rem;border:1px solid rgba(109,48,36,.12);border-radius:16px;background:#f8efe6}
    .selected-package-card[hidden]{display:none}
    .selected-package-card strong,.selected-package-card span{display:block}
    .selected-package-card strong{font-family:'Playfair Display',Georgia,serif;font-size:1.25rem;color:var(--wine)}
    .selected-package-card span{margin-top:.2rem;font-weight:700}
    .selected-package-card p{margin:.45rem 0 0;color:var(--muted);line-height:1.5}
    .selected-package-actions{display:flex;flex-wrap:wrap;align-items:center;justify-content:flex-end;gap:.8rem}
    .selected-package-actions>a:first-child{font-size:.84rem;font-weight:700;text-decoration:underline}
    .selected-package-actions .btn{white-space:nowrap}
    body.dark-mode .selected-package-card{background:#29231f;border-color:#4f4942}
    body.dark-mode .selected-package-card strong{color:#f1c29b}
    @media(max-width:575px){.selected-package-card{align-items:stretch;flex-direction:column;gap:1rem;padding:1rem}.selected-package-actions{align-items:stretch;flex-direction:column;gap:.75rem}.selected-package-actions .btn{text-align:center}}
    .floating-toast{position:fixed;right:1.25rem;bottom:1.25rem;display:flex;align-items:center;gap:.9rem;width:min(360px,calc(100vw - 2rem));background:#1f1c1a;color:#fff;padding:1rem 1rem;border-radius:16px;box-shadow:0 20px 50px rgba(0,0,0,.18);opacity:0;transform:translateY(20px);transition:.28s ease;z-index:2000}
    .floating-toast.show{opacity:1;transform:translateY(0)}
    .floating-toast__icon{display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.12);font-weight:800;color:#d8f7d0}
    .floating-toast__content{flex:1;font-size:.92rem}
    .floating-toast__content strong{display:block;margin-bottom:.15rem}
    .floating-toast__close{border:0;background:transparent;color:#fff;opacity:.8;font-size:1.4rem;line-height:1}
    @media (max-width:991.98px){.reservation-sidebar{position:static}}
    @media (max-width:575px){
        .page-heading{padding-top:.5rem}
        .form-card{padding:1.25rem!important}
        .wizard-stepper-label{display:none}
        .wizard-actions{flex-direction:column-reverse;align-items:stretch}
        .wizard-actions .btn{width:100%;margin-left:0}
        .confirmation-actions{flex-direction:column}
        .confirmation-actions .btn{width:100%}
    }
    @media print{
        .navbar,.footer,.wizard-stepper,.confirmation-actions,#reservation-toast{display:none!important}
    }
</style>

<script>
const reservationDraftKey = '3yos-reservation-draft';
const reservationDraftForm = document.getElementById('reservation-form');
const reservationDraft = sessionStorage.getItem(reservationDraftKey);
if (reservationDraftForm && reservationDraft) {
    const draftValues = JSON.parse(reservationDraft);
    for (const [name, value] of Object.entries(draftValues)) {
        const field = reservationDraftForm.elements.namedItem(name);
        if (field && 'value' in field) field.value = value;
    }
    sessionStorage.removeItem(reservationDraftKey);
}

const reservationToast = document.getElementById('reservation-toast');
if (reservationToast) {
    const closeButton = reservationToast.querySelector('.floating-toast__close');
    const hideToast = () => reservationToast.classList.remove('show');
    setTimeout(hideToast, 6000);
    closeButton?.addEventListener('click', hideToast);
}

const dateInput=document.getElementById('event_date'), availability=document.getElementById('date-availability'), submitButton=document.getElementById('submit-reservation');
const nextPackageButton=document.querySelector('.wizard-step[data-step="1"] .wizard-next');
let dateAvailabilityState='unknown', dateAvailabilityCheckedFor='', availabilityRequestDate='', availabilityRequestId=0;

const setDateAvailability = (state, date, message, className) => {
    dateAvailabilityState = state;
    dateAvailabilityCheckedFor = state === 'available' ? date : '';
    availability.textContent = message;
    availability.hidden = message === '';
    availability.className = className;
    dateInput.setAttribute('aria-invalid', ['full', 'invalid', 'error'].includes(state) ? 'true' : 'false');
    if (nextPackageButton) {
        nextPackageButton.disabled = state !== 'available' || date !== dateInput.value;
    }
    submitButton.disabled = state !== 'available' || date !== dateInput.value;
};

if (dateInput && availability && submitButton) {
    const checkDateAvailability = async () => {
        const selectedDate = dateInput.value;

        if (dateAvailabilityState === 'loading' && availabilityRequestDate === selectedDate) return;
        if (['available', 'full'].includes(dateAvailabilityState) && dateAvailabilityCheckedFor === selectedDate) return;

        const requestId = ++availabilityRequestId;
        availabilityRequestDate = selectedDate;

        if (!selectedDate) {
            setDateAvailability('unknown', '', 'Choose a date at least 2 days in advance.', 'date-availability form-text');
            return;
        }

        if (selectedDate < dateInput.min) {
            setDateAvailability('invalid', selectedDate, 'Reservations must be booked at least 2 days in advance.', 'date-availability text-danger');
            return;
        }

        setDateAvailability('loading', selectedDate, 'Checking availability…', 'date-availability form-text');

        try {
            const response = await fetch(`{{ route('reservation.availability') }}?date=${encodeURIComponent(selectedDate)}`, {
                headers: { Accept: 'application/json' },
            });
            if (!response.ok) throw new Error('Availability request failed.');

            const data = await response.json();
            if (requestId !== availabilityRequestId || dateInput.value !== selectedDate) return;

            if (data.available === true) {
                setDateAvailability('available', selectedDate, '', 'date-availability form-text');
            } else if (data.available === false) {
                setDateAvailability('full', selectedDate, 'This date is currently fully booked. Please select another available date.', 'date-availability text-danger');
            } else {
                throw new Error('Availability response was invalid.');
            }
        } catch {
            if (requestId !== availabilityRequestId || dateInput.value !== selectedDate) return;
            setDateAvailability('error', selectedDate, 'We could not check this date. Please try again.', 'date-availability text-danger');
        }
    };

    dateInput.addEventListener('input', checkDateAvailability);
    dateInput.addEventListener('change', checkDateAvailability);
    checkDateAvailability();
}

const packageInput = document.getElementById('package_id');
const guestCountInput = document.getElementById('guest_count');
const budgetOutput = document.getElementById('estimated-budget-display');
const pesoFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', maximumFractionDigits: 0 });
const selectedPackageCard = document.getElementById('selected-package-card');
const selectedPackageName = document.getElementById('selected-package-name');
const selectedPackagePrice = document.getElementById('selected-package-price');
const selectedPackageDescription = document.getElementById('selected-package-description');
const packageDetailsLink = document.getElementById('view-package-details');
const packageSelectionError = document.getElementById('package-selection-error');
const packageSelectionHelp = document.getElementById('package-selection-help');
const packagePriceFormatter = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP', minimumFractionDigits: 2, maximumFractionDigits: 2 });
const changePackageButton = document.getElementById('change-package');
let isChangingPackage = false;

const updateSelectedPackage = () => {
    const option = packageInput?.selectedOptions[0];
    const selected = Boolean(option?.value);

    if (packageInput) packageInput.hidden = selected && !isChangingPackage;
    if (selectedPackageCard) selectedPackageCard.hidden = !selected;
    if (packageSelectionHelp) packageSelectionHelp.hidden = selected;
    if (packageSelectionError) packageSelectionError.hidden = selected;
    if (changePackageButton) changePackageButton.setAttribute('aria-expanded', String(isChangingPackage));
    if (!selected) return;

    selectedPackageName.textContent = option.dataset.name;
    selectedPackagePrice.textContent = `${packagePriceFormatter.format(Number(option.dataset.price))} / person`;
    selectedPackageDescription.textContent = option.dataset.description;
    selectedPackageDescription.hidden = !option.dataset.description;
    packageDetailsLink.href = option.dataset.detailsUrl;
    packageDetailsLink.hidden = false;
};

packageInput?.addEventListener('change', () => {
    isChangingPackage = false;
    updateSelectedPackage();
    changePackageButton?.focus();
});
changePackageButton?.addEventListener('click', () => {
    isChangingPackage = true;
    updateSelectedPackage();

    packageInput?.scrollIntoView({ block: 'nearest' });
    packageInput?.focus();
});
updateSelectedPackage();

const saveReservationDraft = (event) => {
    const link = event.currentTarget;
    if (link.id === 'view-package-details' && guestCountInput?.value) {
        const detailsUrl = new URL(link.href);
        detailsUrl.searchParams.set('guests', guestCountInput.value);
        link.href = detailsUrl.toString();
    }

    const excludedFields = new Set(['package_id', 'website', 'form_started', 'g-recaptcha-response']);
    const draftValues = {};

    for (const field of reservationDraftForm.elements) {
        if (!field.name || excludedFields.has(field.name) || field.type === 'hidden' || field.type === 'submit' || field.type === 'button' || field.type === 'file') continue;
        draftValues[field.name] = field.value;
    }

    sessionStorage.setItem(reservationDraftKey, JSON.stringify(draftValues));
};
document.querySelectorAll('.package-selection-link').forEach((link) => link.addEventListener('click', saveReservationDraft));

reservationDraftForm?.addEventListener('submit', (event) => {
    if (packageInput?.value) return;

    event.preventDefault();
    packageSelectionError.hidden = false;
    packageInput.focus();
    packageInput.reportValidity();
});

const timeInput = document.getElementById('event_time');
const clockTimePicker = document.getElementById('clock-time-picker');
const clockTimeToggle = document.getElementById('clock-time-toggle');
const clockTimePanel = document.getElementById('clock-time-panel');
const clockTimeNumbers = document.getElementById('clock-time-numbers');
const clockTimeValue = document.getElementById('clock-time-value');
const clockTimeFace = document.getElementById('clock-time-face');
const clockTimeHand = document.getElementById('clock-time-hand');
const selectedHourOutput = document.getElementById('clock-current-hour');
const selectedMinuteOutput = document.getElementById('clock-current-minute');
const eventTimeHelp = document.getElementById('event-time-help');
const nativeTimeQuery = window.matchMedia('(max-width: 767.98px)');
let selectedHour = 12;
let selectedMinute = 0;
let selectedPeriod = 'AM';
let clockMode = 'hours';

if (timeInput && clockTimePicker && clockTimeToggle && clockTimePanel) {
    const updateClockReadout = () => {
        selectedHourOutput.textContent = String(selectedHour);
        selectedMinuteOutput.textContent = String(selectedMinute).padStart(2, '0');
        document.querySelectorAll('[data-clock-period]').forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.clockPeriod === selectedPeriod));
        });
    };
    const renderClockFace = () => {
        const choosingHours = clockMode === 'hours';
        const values = choosingHours ? Array.from({ length: 12 }, (_, index) => index + 1) : Array.from({ length: 12 }, (_, index) => index * 5);
        const selectedValue = choosingHours ? selectedHour : selectedMinute;
        const angleStep = choosingHours ? (selectedHour % 12) * 30 : (selectedMinute / 5) * 30;
        clockTimeFace.setAttribute('aria-label', choosingHours ? 'Choose hour' : 'Choose minutes');
        clockTimeHand.style.transform = `rotate(${180 + angleStep}deg)`;
        clockTimeNumbers.replaceChildren();

        values.forEach((value) => {
            const button = document.createElement('button');
            const angle = choosingHours ? (value % 12) * 30 : (value / 5) * 30;
            button.type = 'button';
            button.className = 'clock-time-number';
            button.style.setProperty('--clock-angle', `${angle}deg`);
            button.textContent = choosingHours ? String(value) : String(value).padStart(2, '0');
            button.setAttribute('aria-label', choosingHours ? `${value} o'clock` : `${String(value).padStart(2, '0')} minutes`);
            button.setAttribute('aria-pressed', String(value === selectedValue));
            button.addEventListener('click', () => {
                if (choosingHours) {
                    selectedHour = value;
                    clockMode = 'minutes';
                } else {
                    selectedMinute = value;
                }
                updateClockReadout();
                renderClockFace();
            });
            clockTimeNumbers.append(button);
        });
    };
    const alignClockPanel = () => {
        const fieldBounds = clockTimePicker.getBoundingClientRect();
        const panelWidth = Math.min(320, window.innerWidth - 32);
        const panelHeight = clockTimePanel.getBoundingClientRect().height;
        const left = Math.max(16, Math.min(fieldBounds.left, window.innerWidth - panelWidth - 16));
        const spaceBelow = window.innerHeight - fieldBounds.bottom - 16;
        const maxTop = Math.max(16, window.innerHeight - panelHeight - 16);
        const preferredTop = spaceBelow >= panelHeight + 6 ? fieldBounds.bottom + 6 : fieldBounds.top - panelHeight - 6;
        clockTimePanel.style.left = `${left}px`;
        clockTimePanel.style.top = `${Math.max(16, Math.min(preferredTop, maxTop))}px`;
    };
    const closeClockPanel = (returnFocus = false) => {
        clockTimePanel.hidden = true;
        clockTimeToggle.setAttribute('aria-expanded', 'false');
        if (returnFocus) clockTimeToggle.focus();
    };
    const openClockPanel = () => {
        clockTimePanel.hidden = false;
        clockTimeToggle.setAttribute('aria-expanded', 'true');
        alignClockPanel();
        renderClockFace();
        document.querySelector(`.clock-time-number[aria-pressed="true"]`)?.focus();
    };

    const syncClockFromInput = () => {
        if (!timeInput.value) {
            clockTimeValue.textContent = 'Select a time';
            return;
        }

        const [hours, minutes] = timeInput.value.split(':').map(Number);
        selectedHour = hours % 12 || 12;
        selectedMinute = minutes;
        selectedPeriod = hours < 12 ? 'AM' : 'PM';
        clockTimeValue.textContent = `${selectedHour}:${String(selectedMinute).padStart(2, '0')} ${selectedPeriod}`;
        updateClockReadout();
    };
    const configureTimeInput = () => {
        const useNativeInput = nativeTimeQuery.matches;
        timeInput.hidden = !useNativeInput;
        timeInput.required = useNativeInput;
        timeInput.tabIndex = useNativeInput ? 0 : -1;

        if (useNativeInput) {
            timeInput.removeAttribute('aria-hidden');
            clockTimePicker.hidden = true;
            clockTimeToggle.setAttribute('aria-expanded', 'false');
            clockTimePanel.hidden = true;
            document.getElementById('clock-time-error').hidden = true;
            clockTimeToggle.removeAttribute('aria-invalid');
            eventTimeHelp.textContent = "Use your device's time picker.";
        } else {
            timeInput.setAttribute('aria-hidden', 'true');
            clockTimePicker.hidden = false;
            syncClockFromInput();
            eventTimeHelp.textContent = 'Choose a time using the clock.';
        }
    };
    syncClockFromInput();
    configureTimeInput();
    nativeTimeQuery.addEventListener('change', configureTimeInput);
    window.addEventListener('resize', configureTimeInput);
    timeInput.addEventListener('input', syncClockFromInput);
    updateClockReadout();
    renderClockFace();
    clockTimeToggle.addEventListener('click', () => {
        if (clockTimePanel.hidden) openClockPanel();
        else closeClockPanel();
    });
    selectedHourOutput.addEventListener('click', () => { clockMode = 'hours'; renderClockFace(); });
    selectedMinuteOutput.addEventListener('click', () => { clockMode = 'minutes'; renderClockFace(); });
    document.querySelectorAll('[data-clock-period]').forEach((button) => button.addEventListener('click', () => {
        selectedPeriod = button.dataset.clockPeriod;
        updateClockReadout();
    }));
    document.getElementById('clock-time-cancel').addEventListener('click', () => closeClockPanel(true));
    document.getElementById('clock-time-apply').addEventListener('click', () => {
        const hours24 = (selectedHour % 12) + (selectedPeriod === 'PM' ? 12 : 0);
        timeInput.value = `${String(hours24).padStart(2, '0')}:${String(selectedMinute).padStart(2, '0')}`;
        timeInput.dispatchEvent(new Event('change', { bubbles: true }));
        clockTimeValue.textContent = `${selectedHour}:${String(selectedMinute).padStart(2, '0')} ${selectedPeriod}`;
        clockTimeToggle.removeAttribute('aria-invalid');
        document.getElementById('clock-time-error').hidden = true;
        closeClockPanel(true);
    });
    document.addEventListener('pointerdown', (event) => {
        if (!clockTimePicker.contains(event.target)) closeClockPanel();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !clockTimePanel.hidden) closeClockPanel(true);
    });
    window.addEventListener('resize', () => { if (!clockTimePanel.hidden) alignClockPanel(); });
    window.addEventListener('scroll', () => { if (!clockTimePanel.hidden) alignClockPanel(); }, true);
    document.getElementById('reservation-form').addEventListener('submit', (event) => {
        if (nativeTimeQuery.matches) return;
        if (timeInput.value) return;

        event.preventDefault();
        clockTimeToggle.setAttribute('aria-invalid', 'true');
        document.getElementById('clock-time-error').hidden = false;
        clockTimeToggle.focus();
    });
}

const updateBudgetEstimate = () => {
    const selectedPackage = packageInput?.selectedOptions[0];
    const guestCount = Number(guestCountInput?.value);

    if (!selectedPackage?.value || !guestCount) {
        budgetOutput.textContent = 'Choose a package and guest count to see an estimate.';
        return;
    }

    budgetOutput.textContent = pesoFormatter.format(Number(selectedPackage.dataset.price) * guestCount);
};
packageInput?.addEventListener('change', updateBudgetEstimate);
guestCountInput?.addEventListener('input', updateBudgetEstimate);
updateBudgetEstimate();

// --- Multi-step wizard: pure presentation over the single unchanged form/submission/validation. ---
const reservationForm = document.getElementById('reservation-form');
if (reservationForm) {
    const wizardSteps = [...document.querySelectorAll('.wizard-step')];
    const stepperItems = [...document.querySelectorAll('.wizard-stepper-item')];

    const stepIsValid = (stepEl) => {
        if (stepEl.dataset.step === '2' && packageInput && !packageInput.value) {
            packageSelectionError.hidden = false;
            packageInput.focus();
            return false;
        }

        const requiredFields = [...stepEl.querySelectorAll('input[required], select[required], textarea[required]')];
        for (const field of requiredFields) {
            if (!field.reportValidity()) { field.focus(); return false; }
        }
        if (stepEl.dataset.step === '1' && timeInput && !timeInput.value) {
            clockTimeToggle?.setAttribute('aria-invalid', 'true');
            const errorEl = document.getElementById('clock-time-error');
            if (errorEl) errorEl.hidden = false;
            clockTimeToggle?.focus();
            return false;
        }
        if (stepEl.dataset.step === '1' && (
            dateAvailabilityState !== 'available' ||
            dateAvailabilityCheckedFor !== dateInput.value
        )) {
            dateInput.focus();
            return false;
        }
        return true;
    };

    const reviewFieldMap = {
        event_type: () => document.querySelector('[name="event_type"]')?.selectedOptions[0]?.text || '—',
        event_date: () => dateInput?.value ? new Date(`${dateInput.value}T00:00:00`).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '—',
        event_time: () => clockTimeValue?.textContent && clockTimeValue.textContent !== 'Select a time' ? clockTimeValue.textContent : '—',
        venue: () => document.querySelector('[name="venue"]')?.value || '—',
        guest_count: () => guestCountInput?.value || '—',
        package: () => packageInput?.selectedOptions[0]?.dataset.name || '—',
        estimate: () => budgetOutput?.textContent || '—',
        additional_services: () => document.querySelector('[name="additional_services"]')?.value || 'None',
        special_requests: () => document.querySelector('[name="special_requests"]')?.value || 'None',
        full_name: () => document.querySelector('[name="full_name"]')?.value || '—',
        contact_number: () => document.querySelector('[name="contact_number"]')?.value || '—',
        email: () => document.querySelector('[name="email"]')?.value || '—',
        address: () => document.querySelector('[name="address"]')?.value || '—',
        additional_notes: () => document.querySelector('[name="additional_notes"]')?.value || 'None',
    };
    const renderReview = () => {
        for (const [key, getValue] of Object.entries(reviewFieldMap)) {
            const el = document.getElementById(`review-${key}`);
            if (el) el.textContent = getValue();
        }
    };

    const showStep = (n) => {
        wizardSteps.forEach((el) => { el.hidden = Number(el.dataset.step) !== n; });
        stepperItems.forEach((el) => {
            const s = Number(el.dataset.stepperStep);
            el.classList.toggle('is-active', s === n);
            el.classList.toggle('is-complete', s < n);
        });
        if (n === 4) renderReview();
        reservationForm.closest('.form-card')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    document.querySelectorAll('.wizard-next').forEach((btn) => btn.addEventListener('click', () => {
        const stepEl = btn.closest('.wizard-step');
        if (!stepIsValid(stepEl)) return;
        showStep(Number(btn.dataset.next));
    }));
    document.querySelectorAll('.wizard-back').forEach((btn) => btn.addEventListener('click', () => showStep(Number(btn.dataset.back))));
    document.querySelectorAll('.review-edit').forEach((btn) => btn.addEventListener('click', () => showStep(Number(btn.dataset.editStep))));

    const fieldStepMap = { event_type: 1, event_date: 1, event_time: 1, venue: 1, guest_count: 1, package_id: 2, additional_services: 2, special_requests: 2, full_name: 3, contact_number: 3, email: 3, address: 3, additional_notes: 3, 'g-recaptcha-response': 4 };
    const errorFields = @json($errors->keys());
    let initialStep = 1;
    if (errorFields.length) {
        const candidateSteps = errorFields.map((field) => fieldStepMap[field]).filter(Boolean);
        if (candidateSteps.length) initialStep = Math.min(...candidateSteps);
    }
    showStep(initialStep);
}
</script>
<script src="{{ asset('js/recaptcha-lazy.js') }}?v={{ filemtime(public_path('js/recaptcha-lazy.js')) }}" defer></script>
@endsection
