<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', '3YOS Catering | Exceptional celebrations')</title>
    <link rel="icon" type="image/png" href="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/scroll-header.css') }}?v={{ filemtime(public_path('css/scroll-header.css')) }}">
    <style>
        :root{--ink:#20201d;--muted:#6f6d66;--cream:#f8f6f1;--paper:#fffdf9;--wine:#6d3024;--terracotta:#b66545;--gold:#c7984b;--line:#e7e1d7;--public-header-offset:4.5rem;--public-content-gap:clamp(2.5rem,4vw,4rem)}
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;overflow-x:hidden}
        body{margin:0;padding:0;font-family:'DM Sans',sans-serif;color:var(--ink);background:var(--cream)}
        h1,h2,h3,h4,h5,.display-font{font-family:'Playfair Display',Georgia,serif}
        a{text-decoration:none}
        .container,.container-fluid{padding-left:clamp(1rem,2vw,2rem);padding-right:clamp(1rem,2vw,2rem)}
        .row{margin-left:0;margin-right:0}
        .navbar{position:fixed;top:0;left:0;right:0;z-index:1035;width:100%;margin:0;background:rgba(255,253,249,.94)!important;border-bottom:1px solid rgba(32,32,29,.07);backdrop-filter:blur(14px)}
        main.page-content{padding-top:calc(var(--public-header-offset) + var(--public-content-gap))}
        main.page-content > :first-child.container,main.page-content > .about-hero > .container:first-child,main.page-content > .status-page > .container:first-child{padding-top:0!important}
        main.page-content > :first-child:is(.about-hero,.gallery-page,.status-page){margin-top:calc(-1 * (var(--public-header-offset) + var(--public-content-gap)));padding-top:calc(var(--public-header-offset) + var(--public-content-gap))!important}
        main.page-content[data-page="home"] > .hero{margin-top:calc(-1 * var(--public-header-offset))}
        .navbar-brand{display:inline-flex;align-items:center;gap:.75rem;color:var(--wine)!important;font-family:'Playfair Display',Georgia,serif;font-size:1.4rem;font-weight:800;letter-spacing:.02em}
        .navbar-brand-text{display:flex;flex-direction:column;line-height:1.05}
        .navbar-brand-text small{font-size:.62rem;letter-spacing:.18em;text-transform:uppercase;color:var(--muted);font-family:'DM Sans',sans-serif;font-weight:700}
        .nav-link{color:var(--ink)!important;font-size:.9rem;font-weight:600;padding:.8rem .85rem!important}
        .nav-link:hover,.nav-link.active{color:var(--terracotta)!important}
        .btn{border-radius:999px;font-weight:700;font-size:.88rem;letter-spacing:.02em;padding:.76rem 1.35rem;transition:.2s ease}
        .btn-primary{background:var(--wine);border-color:var(--wine)}
        .btn-primary:hover,.btn-primary:focus{background:#512218;border-color:#512218;transform:translateY(-2px)}
        .btn-outline-primary{color:var(--wine);border-color:var(--wine)}
        .btn-outline-primary:hover{background:var(--wine);border-color:var(--wine)}
        .eyebrow{color:var(--terracotta);font-size:.75rem;font-weight:800;letter-spacing:.16em;text-transform:uppercase}
        .theme-toggle{display:inline-flex;align-items:center;justify-content:center;border:1px solid var(--line);background:transparent;color:var(--ink);padding:.48rem .7rem;font-size:.78rem;font-weight:700;border-radius:999px;min-width:42px;min-height:42px;line-height:1}
        .footer{margin-top:3rem;padding:3.25rem 0 1rem;background:var(--cream);color:var(--ink);border-top:1px solid var(--line)}
        .footer a{color:inherit;text-decoration:none}
        .footer a:hover{color:var(--terracotta)}
        .footer-title{margin:.55rem 0 1rem;color:var(--wine);font-family:'Playfair Display',Georgia,serif;font-size:clamp(1.8rem,2.5vw,2.35rem);line-height:1.15;letter-spacing:-.03em}
        .footer-kicker{color:var(--wine);font-size:.7rem;font-weight:800;letter-spacing:.18em;text-transform:uppercase}
        .footer-heading{margin:0 0 1.1rem;color:var(--wine);font-size:.73rem;font-weight:800;letter-spacing:.15em;text-transform:uppercase}
        .footer-description,.footer-planning-copy{max-width:23rem;margin:0;color:var(--muted);font-size:.92rem;line-height:1.75}
        .footer-links{display:grid;gap:.62rem;font-size:.9rem;line-height:1.45}
        .footer-links a{width:max-content;max-width:100%;padding:.1rem 0}
        .footer-contact-list{display:grid;gap:.72rem;font-size:.86rem;line-height:1.55}
        .footer-contact-list a{width:max-content;max-width:100%;overflow-wrap:anywhere}
        .footer-contact-list a:hover{text-decoration:underline;text-decoration-color:var(--terracotta);text-underline-offset:3px}
        .footer-booking-link{display:inline-flex;align-items:center;justify-content:center;gap:.75rem;min-height:46px;margin-top:1.2rem;padding:.65rem 1.15rem;border:1px solid var(--wine);border-radius:999px;color:var(--wine)!important;font-size:.85rem;font-weight:700;transition:background-color .2s ease,color .2s ease,border-color .2s ease}
        .footer-booking-link:hover,.footer-booking-link:focus-visible{background:var(--wine);color:var(--paper)!important}
        .footer-booking-link:focus-visible,.footer-social-link:focus-visible,.footer-links a:focus-visible,.footer-contact a:focus-visible{outline:2px solid var(--terracotta);outline-offset:3px}
        .footer-bottom{display:flex;align-items:center;justify-content:space-between;gap:1rem;margin-top:2.5rem;padding-top:1rem;border-top:1px solid var(--line);color:var(--muted);font-size:.78rem;line-height:1.5}
        .footer-socials{display:flex;align-items:center;gap:.35rem}
        .footer-social-link{display:grid;place-items:center;width:40px;height:40px;border-radius:50%;color:var(--wine)!important;transition:background-color .2s ease,color .2s ease}
        .footer-social-link:hover{background:var(--paper);color:var(--terracotta)!important}
        .footer-social-link svg{width:17px;height:17px;fill:currentColor}
        body.dark-mode{--ink:#f5f1e9;--muted:#c9c3b9;--cream:#151515;--paper:#201f1d;--line:#575148;--wine:#e6ad92;--terracotta:#edb18f;--gold:#e7bd72;background:var(--cream);color:var(--ink)}
        body.dark-mode .navbar{background:rgba(27,26,24,.95)!important;border-color:#403c35}
        body.dark-mode .navbar-brand,body.dark-mode .nav-link,body.dark-mode .theme-toggle{color:#f5f1e9!important}
        body.dark-mode .theme-toggle{background:#2a2724;border-color:#575148}
        body.dark-mode .hero{background:linear-gradient(115deg,#1b1714 0%,#2a241f 55%,#221d1a 100%)!important}
        body.dark-mode .hero-copy{color:#d8d0c5!important}
        body.dark-mode main,body.dark-mode .bg-paper,body.dark-mode .section-heading,body.dark-mode .card,body.dark-mode .form-card,body.dark-mode .service-tile,body.dark-mode .home-package-card,body.dark-mode .process-card,body.dark-mode .cta-panel,body.dark-mode .occasion-panel,body.dark-mode .metric-card,body.dark-mode .mini-cta,body.dark-mode .reservation-sidebar,body.dark-mode .reservation-tile,body.dark-mode .process-item{background:#201f1d!important;color:#f5f1e9!important}
        body.dark-mode .card p,body.dark-mode .card h1,body.dark-mode .card h2,body.dark-mode .card h3,body.dark-mode .card h4,body.dark-mode .card h5,body.dark-mode .process-item strong,body.dark-mode .process-item p,body.dark-mode .service-tile h3,body.dark-mode .service-tile p,body.dark-mode .home-package-card h3,body.dark-mode .home-package-card p,body.dark-mode .mini-cta h3,body.dark-mode .mini-cta p,body.dark-mode .reservation-checklist li,body.dark-mode .reservation-tile p,body.dark-mode .form-label,body.dark-mode .text-muted,body.dark-mode .eyebrow{color:#f5f1e9!important}
        body.dark-mode .form-control,body.dark-mode .form-select{background:#151515;border-color:#555047;color:#f5f1e9}
        body.dark-mode .form-control::placeholder,body.dark-mode textarea::placeholder{color:#aebbc8;opacity:1}
        body.dark-mode .form-select{background-image:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23dce7f0' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");background-repeat:no-repeat;background-position:right .75rem center;background-size:16px 12px}
        body.dark-mode input[type="date"]{color-scheme:dark}
        body.dark-mode input[type="date"]::-webkit-calendar-picker-indicator{filter:invert(1);opacity:.82;cursor:pointer}
        body.dark-mode .form-text,body.dark-mode .date-availability{color:var(--muted)!important}
        body.dark-mode .date-availability.text-success{color:#8fe3aa!important}
        body.dark-mode .date-availability.text-danger{color:#ff9b9b!important}
        body.dark-mode .page-kicker{color:#75d8cf}
        body.dark-mode .btn-outline-primary{color:#f5f1e9;border-color:#d9b18f;background:transparent}
        body.dark-mode .btn-outline-primary:hover{background:#d9b18f;color:#1b1714}
        body.dark-mode .btn-primary{background:#d79c6a;border-color:#d79c6a;color:#1b1714}
        body.dark-mode .footer{background:var(--paper);color:var(--ink);border-color:var(--line)}
        body.dark-mode .footer-booking-link:hover,body.dark-mode .footer-booking-link:focus-visible{color:#1b1714!important}
        body.dark-mode .footer-social-link:hover{background:#292521}
        body.dark-mode .card,body.dark-mode .process-item{border-color:#403b36!important}
        body.dark-mode .navbar-toggler-icon{filter:invert(1)}
        @media(min-width:768px) and (max-width:991.98px){:root{--public-header-offset:4.75rem;--public-content-gap:clamp(2rem,4vw,3rem)}}
        @media(max-width:767.98px){:root{--public-header-offset:4.75rem;--public-content-gap:clamp(1.5rem,6vw,2.25rem)}}
        @media(max-width:767.98px){.footer{padding-top:2.5rem}.footer-bottom{align-items:flex-start;flex-direction:column;margin-top:2rem}.footer-socials{margin-left:-.45rem}}
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg sticky-top py-2" data-scroll-header>
        <div class="container">
            <a class="navbar-brand" href="{{ route('home') }}">
                <img src="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}" alt="3YOS Catering Services" style="height:48px;width:auto;object-fit:contain;border-radius:50%;background:transparent">
                <span class="navbar-brand-text">
                    <span>3YOS</span>
                    <small>Catering</small>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('services') ? 'active' : '' }}" href="{{ route('services') }}">Services</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('packages*') ? 'active' : '' }}" href="{{ route('packages') }}">Packages</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('gallery') ? 'active' : '' }}" href="{{ route('gallery') }}">Gallery</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('reservation.status') ? 'active' : '' }}" href="{{ route('reservation.status') }}">Status</a></li>
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('inquiry') ? 'active' : '' }}" href="{{ route('inquiry') }}">Inquiry</a></li>
                    <li class="nav-item ms-lg-2"><button type="button" class="theme-toggle" id="customerThemeToggle" aria-label="Enable dark mode" title="Enable dark mode"><span aria-hidden="true">&#9790;</span></button></li>
                    <li class="nav-item ms-lg-2"><a class="btn btn-primary" href="{{ route('reservation') }}">Book an event</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="page-content" data-page="{{ request()->route()?->getName() }}">@yield('content')</main>

    <footer class="footer">
        <div class="container">
            <div class="row gx-4 gy-4 gx-lg-5 footer-main">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="footer-kicker">3YOS Catering</div>
                    <h2 class="footer-title">3YOS Catering</h2>
                    <p class="footer-description">Thoughtful food, graceful styling, and dependable service for celebrations that deserve to feel effortless.</p>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <nav aria-label="Quick links">
                        <h2 class="footer-heading">Quick links</h2>
                        <div class="footer-links">
                            <a href="{{ route('home') }}">Home</a>
                            <a href="{{ route('services') }}">Services</a>
                            <a href="{{ route('packages') }}">Packages</a>
                            <a href="{{ route('reservation') }}">Book now</a>
                            <a href="{{ route('support') }}">Support</a>
                        </div>
                    </nav>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <h2 class="footer-heading">Get in touch</h2>
                    <address class="footer-contact-list mb-0">
                        <span>Marikina City, Metro Manila</span>
                        <a href="tel:+639982422719">0998 242 2719</a>
                        <a href="mailto:3yoscatering@gmail.com">3yoscatering@gmail.com</a>
                        <a href="https://www.facebook.com/profile.php?id=100063690915629" target="_blank" rel="noopener noreferrer">Message 3YOS Catering</a>
                        <a href="{{ route('inquiry') }}">Tell us about your event</a>
                    </address>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <h2 class="footer-heading">Start planning</h2>
                    <p class="footer-planning-copy">Share your date, venue, guest count, and celebration vision. We’ll help you build the right package.</p>
                    <a href="{{ route('reservation') }}" class="footer-booking-link">Book an event <span aria-hidden="true">→</span></a>
                </div>
            </div>
            <div class="footer-bottom">
                <span>&copy; {{ date('Y') }} 3YOS Catering Services & Party Needs. All rights reserved.</span>
                <nav class="footer-socials" aria-label="Social media links">
                    <a class="footer-social-link" href="https://www.facebook.com/profile.php?id=100063690915629" target="_blank" rel="noopener noreferrer" aria-label="Visit 3YOS Catering on Facebook">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.4 21v-8h2.7l.4-3.1h-3.1v-2c0-.9.3-1.5 1.6-1.5h1.7V3.6c-.3 0-1.3-.1-2.4-.1-2.4 0-4.1 1.5-4.1 4.2v2.3H7.5v3.1h2.7v8h3.2Z"/></svg>
                    </a>
                </nav>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (() => {
            const button = document.getElementById('customerThemeToggle');
            if (!button) return;
            const applyTheme = (dark) => {
                document.body.classList.toggle('dark-mode', dark);
                button.innerHTML = dark ? '&#9788;' : '&#9790;';
                button.setAttribute('aria-label', dark ? 'Enable light mode' : 'Enable dark mode');
                button.setAttribute('title', dark ? 'Enable light mode' : 'Enable dark mode');
                button.setAttribute('aria-pressed', String(dark));
            };
            applyTheme(localStorage.getItem('customer-theme') === 'dark');
            button.addEventListener('click', () => {
                const dark = !document.body.classList.contains('dark-mode');
                localStorage.setItem('customer-theme', dark ? 'dark' : 'light');
                applyTheme(dark);
            });
        })();
    </script>
    <script src="{{ asset('js/scroll-header.js') }}?v={{ filemtime(public_path('js/scroll-header.js')) }}"></script>
    <script>
        document.querySelectorAll('form input:not([type="hidden"]), form select, form textarea').forEach((field) => {
            if (field.title) return;
            const label = field.id ? Array.from(field.form?.querySelectorAll('label') || []).find((item) => item.htmlFor === field.id) : null;
            const fieldName = (label?.textContent || field.placeholder || field.name || 'this field').trim().replace(/\s+/g, ' ').toLowerCase();
            const instruction = field.type === 'email' ? 'Enter a valid email address.'
                : field.type === 'tel' ? 'Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces.'
                    : field.type === 'date' ? 'Choose the event date.'
                        : field.type === 'time' ? 'Choose the event time.'
                            : field.type === 'file' ? 'Choose a file that meets the accepted format and size.'
                                : field instanceof HTMLSelectElement ? `Choose ${fieldName}.`
                                    : field instanceof HTMLTextAreaElement ? `Describe ${fieldName}.`
                                        : `Enter ${fieldName}.`;
            field.title = instruction;
        });
    </script>
</body>
</html>
