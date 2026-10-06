@extends('layouts.app')

@section('title', 'Inquiry | 3YOS Catering')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="inquiry-shell">
        <div class="inquiry-header">
            <span class="eyebrow">Inquiry form</span>
            <h1 class="fw-bold">Let&rsquo;s talk about your event</h1>
            <p class="text-muted">Tell us what you need and we&rsquo;ll get back with the best options for your celebration.</p>
        </div>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="form-card inquiry-form-card p-4 p-lg-5">
            <form method="POST" action="{{ route('inquiry.store') }}">
                @csrf
                <input type="text" name="website" class="d-none" tabindex="-1" autocomplete="off">
                <input type="hidden" name="form_started" value="{{ now()->timestamp }}">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label" for="inquiry_full_name">Full Name</label>
                        <input type="text" id="inquiry_full_name" name="full_name" value="{{ old('full_name') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="inquiry_contact_number">Contact Number</label>
                        <input type="tel" id="inquiry_contact_number" name="contact_number" value="{{ old('contact_number') }}" class="form-control" inputmode="tel" autocomplete="tel" minlength="11" maxlength="13" pattern="(?:\+63[0-9]{10}|09[0-9]{9})" placeholder="09XXXXXXXXX or +639XXXXXXXXX" title="Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces." required>
                        <small class="form-text">Enter 09 followed by 9 digits or +63 followed by 10 digits.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="inquiry_email">Email Address</label>
                        <input type="email" id="inquiry_email" name="email" value="{{ old('email') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="inquiry_subject">Subject</label>
                        <input type="text" id="inquiry_subject" name="subject" value="{{ old('subject') }}" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="inquiry_category">Category</label>
                        <select id="inquiry_category" name="category" class="form-select" required>
                            <option value="">Select</option>
                            @foreach(['General Inquiry','Reservation','Packages','Pricing','Custom Event','Others'] as $category)
                                <option @selected(old('category') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="inquiry_message">Message</label>
                        <textarea id="inquiry_message" name="message" class="form-control inquiry-message" rows="4" required>{{ old('message') }}</textarea>
                    </div>
                    <div class="col-12">
                        <div data-recaptcha-container>
                            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                            <p class="form-text mt-2 mb-0" data-recaptcha-status role="status" aria-live="polite" hidden></p>
                        </div>
                        @error('g-recaptcha-response')
                            <div class="alert alert-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary inquiry-submit">Submit Inquiry</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .inquiry-shell{max-width:960px;margin:0 auto}
    .inquiry-header{padding:1rem 0 0}
    .inquiry-header .eyebrow{display:block;margin-bottom:1.1rem}
    .inquiry-header h1{font-size:clamp(2rem,4vw,3rem);line-height:1.1;letter-spacing:-.03em;margin:0 0 .6rem}
    .inquiry-header p{margin:0;max-width:640px;color:var(--muted)}
    .inquiry-form-card{margin-top:2rem;background:#fdf9f5;border:1px solid rgba(109,48,36,.08);border-radius:24px;box-shadow:0 22px 50px rgba(32,32,29,.06)}
    .inquiry-form-card .form-label{font-size:.78rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:.5rem}
    .inquiry-form-card .form-control,.inquiry-form-card .form-select{min-height:48px;border:1px solid #e7ddd0;border-radius:14px;padding:.7rem 1rem;background:#fff;color:var(--ink)}
    .inquiry-form-card .form-control:focus,.inquiry-form-card .form-select:focus{border-color:var(--wine);box-shadow:0 0 0 .2rem rgba(109,48,36,.12)}
    .inquiry-form-card .form-text{display:block;margin-top:.4rem;font-size:.78rem;color:var(--muted)}
    .inquiry-form-card .inquiry-message{min-height:130px;height:130px;resize:vertical}
    .inquiry-form-card .inquiry-submit{min-height:48px;padding:.75rem 2.25rem}
    body.dark-mode .inquiry-form-card{background:#201f1d!important;border-color:#403b36}
    body.dark-mode .inquiry-form-card .form-control,body.dark-mode .inquiry-form-card .form-select{background:#151515;border-color:#555047;color:#f5f1e9}
    @media (max-width:575px){.inquiry-header{padding-top:.5rem}.inquiry-form-card{padding:1.25rem!important;margin-top:1.5rem}}
</style>

<script src="{{ asset('js/recaptcha-lazy.js') }}?v={{ filemtime(public_path('js/recaptcha-lazy.js')) }}" defer></script>
@endsection
