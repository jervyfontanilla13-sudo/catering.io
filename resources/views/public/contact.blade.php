@extends('layouts.app')

@section('title', 'Contact | 3YOS Catering')

@section('content')
<div class="container py-4 py-lg-5">
    <div class="page-heading">
        <div class="eyebrow">Contact us</div>
        <h1 class="fw-bold mt-2 mb-2">Let's talk about your event.</h1>
        <p class="text-muted mb-0">Reach us directly using any of the details below, or send a detailed request through our inquiry form.</p>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="form-card p-4 p-lg-5 contact-card">
                <div class="contact-detail"><span class="contact-detail-icon" aria-hidden="true">AD</span><div><small>Address</small><strong>Marikina City, Metro Manila</strong></div></div>
                <div class="contact-detail"><span class="contact-detail-icon" aria-hidden="true">PH</span><div><small>Phone</small><a href="tel:+639982422719">0998 242 2719</a></div></div>
                <div class="contact-detail"><span class="contact-detail-icon" aria-hidden="true">EM</span><div><small>Email</small><a href="mailto:3yoscatering@gmail.com">3yoscatering@gmail.com</a></div></div>
                <div class="contact-detail"><span class="contact-detail-icon" aria-hidden="true">FB</span><div><small>Facebook</small><a href="https://www.facebook.com/profile.php?id=100063690915629" target="_blank" rel="noopener noreferrer">Message 3YOS Catering</a></div></div>

                <div class="contact-cta">
                    <p class="text-muted mb-2">Have event details ready? Send them through our inquiry form and we'll get back to you.</p>
                    <a href="{{ route('inquiry') }}" class="btn btn-primary">Send an inquiry</a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .page-heading{max-width:700px;padding:1rem 0 2rem}
    .page-heading h1{font-size:clamp(2.35rem,5vw,4rem);line-height:1.05;letter-spacing:-.03em}
    .page-heading p{max-width:610px;color:var(--muted);line-height:1.65}
    .contact-card{max-width:none}
    .contact-detail{display:flex;align-items:center;gap:1rem;padding:1rem 0;border-bottom:1px solid var(--line)}
    .contact-detail:last-of-type{border-bottom:0}
    .contact-detail-icon{display:grid;place-items:center;flex:0 0 42px;width:42px;height:42px;border:1px solid rgba(109,48,36,.18);border-radius:50%;color:var(--wine);font-size:.72rem;font-weight:800}
    .contact-detail small{display:block;margin-bottom:.15rem;color:var(--muted);font-size:.7rem;font-weight:800;letter-spacing:.06em;text-transform:uppercase}
    .contact-detail strong,.contact-detail a{font-size:1rem;color:var(--ink);text-decoration:none}
    .contact-detail a:hover{color:var(--wine)}
    .contact-cta{margin-top:1.5rem;padding-top:1.5rem;border-top:1px solid var(--line)}
    @media(max-width:575px){.page-heading{padding-top:.5rem}.form-card{padding:1.25rem!important}}
</style>
@endsection
