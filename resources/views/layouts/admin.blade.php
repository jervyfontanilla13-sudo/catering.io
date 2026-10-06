<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>3YOS Operations</title>
    <link rel="icon" type="image/png" href="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}">
    <link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/admin-workspace.css') }}?v={{ filemtime(public_path('css/admin-workspace.css')) }}">
</head>
<body>
@php
    $manageWebsiteRouteActive = request()->routeIs('admin.packages.*', 'admin.services.*', 'admin.gallery.*');
    $manageWebsiteExpiresAt = session('manage_website_auth_expires_at');
    $manageWebsiteAuthenticated = session('admin_role') === 'full'
        && session('manage_website_auth_user_id') === session('admin_user_id')
        && session('manage_website_auth_source') === session('admin_auth_source')
        && session('manage_website_auth_email') === strtolower((string) session('admin_email'))
        && is_numeric($manageWebsiteExpiresAt)
        && (int) $manageWebsiteExpiresAt > now()->timestamp;
    $manageWebsiteOpen = $manageWebsiteAuthenticated || $manageWebsiteRouteActive;
    $adminHeaderName = session('admin_name', 'Administrator');
    $adminHeaderDate = now(config('app.timezone'))->format('F j, Y');
    $adminHeaderContext = match (request()->route()?->getName()) {
        'admin.dashboard' => "Today's operations and business overview",
        'admin.reservations', 'admin.reservations.export' => 'Manage and review catering reservations',
        'admin.reservations.create', 'admin.reservations.store' => 'Create a reservation for a client',
        'admin.reservations.show' => 'Review event details, contracts, payments, and activity',
        'admin.reservations.payments', 'admin.reservations.payments.print' => 'Review payment records, receipts, and balances',
        'admin.inquiries', 'admin.inquiries.show' => 'Manage customer inquiries and replies',
        'admin.packages.index', 'admin.packages.create', 'admin.packages.edit' => 'Manage catering packages',
        'admin.services.index', 'admin.services.create', 'admin.services.edit' => 'Manage catering services',
        'admin.gallery.index', 'admin.gallery.create', 'admin.gallery.edit' => 'Manage event gallery items',
        'admin.reports', 'admin.reports.export', 'admin.reports.export.excel' => 'Review and export business reports',
        'admin.analytics' => 'Explore catering business performance',
        'admin.users' => 'Manage administrator accounts',
        'admin.activity-logs' => 'Review administrator activity',
        'admin.backups' => 'Manage secure system backups',
        'admin.support' => 'Help and system documentation',
        default => 'Catering operations',
    };
@endphp
<button class="admin-nav-backdrop" id="adminNavBackdrop" type="button" aria-label="Close navigation"></button>
<div class="container-fluid"><div class="admin-layout">
    <aside class="sidebar text-white p-0" id="adminSidebar" aria-label="Primary navigation">
        <div class="brand">
            <img src="{{ request()->getBaseUrl() }}/images/logo-transparent.png?v={{ filemtime(public_path('images/logo-transparent.png')) }}" alt="3YOS Catering Services">
            <div><h4>3YOS</h4><div class="brand-subtitle">Catering operations</div></div>
        </div>
        <nav aria-label="Admin sections">
        <div class="nav-caption">Workspace</div>
        <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}" @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
            <span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg></span><span>Overview</span>
        </a>
        <a class="nav-link {{ request()->routeIs('admin.reservations*') ? 'active' : '' }}" href="{{ route('admin.reservations') }}" @if(request()->routeIs('admin.reservations*')) aria-current="page" @endif>
            <span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M7.5 3.5v3M16.5 3.5v3M3.5 9.5h17M8 13h3M8 16h6"/></svg></span><span>Reservations</span>
        </a>
        <a class="nav-link {{ request()->routeIs('admin.inquiries*') ? 'active' : '' }}" href="{{ route('admin.inquiries') }}" @if(request()->routeIs('admin.inquiries*')) aria-current="page" @endif>
            <span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M20 11.5a7.5 7.5 0 0 1-7.5 7.5H7l-3.5 2v-5A7.5 7.5 0 1 1 20 11.5Z"/><path d="M8 11.5h.01M12 11.5h.01M16 11.5h.01"/></svg></span><span>Inquiries</span>
        </a>
        @if(session('admin_role') === 'full')
        <div class="nav-caption mt-3">Content & insights</div>
        <button class="nav-link manage-website-toggle {{ $manageWebsiteRouteActive ? 'active' : '' }}" id="manageWebsiteToggle" type="button" aria-expanded="{{ $manageWebsiteOpen ? 'true' : 'false' }}" aria-controls="manageWebsiteSubnav" data-manage-website-unlocked="{{ $manageWebsiteAuthenticated ? 'true' : 'false' }}">
            <span class="manage-website-label"><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 1 1 8 0v3M12 14v3"/></svg></span><span>Manage Website</span></span>
            <span class="manage-website-chevron" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></span>
        </button>
        <div class="manage-website-subnav" id="manageWebsiteSubnav" @if(! $manageWebsiteOpen) hidden @endif>
            <a class="nav-link {{ request()->routeIs('admin.packages.*') ? 'active' : '' }}" href="{{ route('admin.packages.index') }}" @if(request()->routeIs('admin.packages.*')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="m12 3 8.5 4.5v9L12 21l-8.5-4.5v-9L12 3Z"/><path d="m3.8 7.7 8.2 4.5 8.2-4.5M12 12.2V21M8 5.1l8.4 4.6"/></svg></span><span>Packages</span></a>
            <a class="nav-link {{ request()->routeIs('admin.services.*') ? 'active' : '' }}" href="{{ route('admin.services.index') }}" @if(request()->routeIs('admin.services.*')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 4v5a4 4 0 0 0 8 0V4M8 4v6M4 9h8M16 4v16M16 4a4 4 0 0 1 4 4v3h-4"/></svg></span><span>Services</span></a>
            <a class="nav-link {{ request()->routeIs('admin.gallery.*') ? 'active' : '' }}" href="{{ route('admin.gallery.index') }}" @if(request()->routeIs('admin.gallery.*')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8.5" cy="9" r="1.5"/><path d="m21 15-5-5L5 20"/></svg></span><span>Gallery</span></a>
        </div>
        <a class="nav-link {{ request()->routeIs('admin.analytics') ? 'active' : '' }}" href="{{ route('admin.analytics') }}" @if(request()->routeIs('admin.analytics')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M4 20V5M4 20h17"/><path d="m7 15 4-4 3 2 6-7"/><circle cx="7" cy="15" r=".7"/><circle cx="11" cy="11" r=".7"/><circle cx="14" cy="13" r=".7"/><circle cx="20" cy="6" r=".7"/></svg></span><span>Analytics</span></a>
        <a class="nav-link {{ request()->routeIs('admin.reports*') ? 'active' : '' }}" href="{{ route('admin.reports') }}" @if(request()->routeIs('admin.reports*')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M6 3.5h8l4 4V20a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 6 20V3.5Z"/><path d="M14 3.5v5h5M9 13h6M9 16.5h6"/></svg></span><span>Reports</span></a>
        <a class="nav-link {{ request()->routeIs('admin.users') ? 'active' : '' }}" href="{{ route('admin.users') }}" @if(request()->routeIs('admin.users')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><path d="M2.8 20a6.2 6.2 0 0 1 12.4 0M16 5a3.4 3.4 0 0 1 0 6.7M17.3 14.2a5.8 5.8 0 0 1 4 5.3"/></svg></span><span>Team Admins</span></a>
        <a class="nav-link {{ request()->routeIs('admin.activity-logs') ? 'active' : '' }}" href="{{ route('admin.activity-logs') }}" @if(request()->routeIs('admin.activity-logs')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3.5 2"/></svg></span><span>Activity logs</span></a>
        <div class="nav-caption mt-3">System</div>
        <a class="nav-link {{ request()->routeIs('admin.backups') ? 'active' : '' }}" href="{{ route('admin.backups') }}" @if(request()->routeIs('admin.backups')) aria-current="page" @endif><span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><ellipse cx="12" cy="5.5" rx="8" ry="3"/><path d="M4 5.5v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6M4 11.5v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/><path d="M4 17.5c0 1.7 3.6 3 8 3s8-1.3 8-3"/></svg></span><span>Backups</span></a>
        @endif
        <div class="nav-caption mt-3">Support</div>
        <a class="nav-link {{ request()->routeIs('admin.support*') ? 'active' : '' }}" href="{{ route('admin.support') }}" target="_blank" rel="noopener" @if(request()->routeIs('admin.support*')) aria-current="page" @endif>
            <span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><path d="M9.3 9a2.7 2.7 0 0 1 5.2.9c0 1.8-2.5 2-2.5 3.6"/><path d="M12 17.2h.01"/></svg></span><span>Support</span>
        </a>
        </nav>
        <div class="sidebar-utility">
            <button class="nav-link sidebar-utility-button theme-toggle" id="themeToggle" type="button" aria-label="Enable dark mode" title="Enable dark mode" aria-pressed="false">
                <span class="sidebar-icon" aria-hidden="true">&#9790;</span><span>Dark Mode</span>
            </button>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="nav-link sidebar-utility-button sidebar-sign-out" type="submit">
                    <span class="sidebar-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M10 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H10"/><path d="M13 8l4 4-4 4M17 12H9"/></svg></span><span>Sign Out</span>
                </button>
            </form>
        </div>
        <form id="manageWebsiteAuthForm" class="d-none" method="POST" action="{{ route('admin.manage-website.reauthenticate') }}" data-password-confirm data-password-title="Unlock Manage Website" data-password-label="Current administrator password" data-password-button="Unlock" data-password-message="{{ $errors->first('manage_website_password') ?: 'Enter your current administrator password to access Packages, Services, and Gallery.' }}">
            @csrf
            <input type="hidden" name="return_to" value="{{ session('manage_website_return_to', request()->getRequestUri()) }}">
        </form>
    </aside>
    <div class="admin-main">
        <header class="header-bar">
            <div class="admin-heading">
                <button class="header-btn" type="button" id="adminMobileMenu" aria-label="Open navigation" aria-controls="adminSidebar" aria-expanded="false" title="Open navigation"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button>
                <div class="admin-heading-context">
                    <h4 class="mb-0">Welcome, <span class="admin-header-name">{{ $adminHeaderName }}</span><span class="admin-header-date"> | {{ $adminHeaderDate }}</span></h4>
                    <p class="mb-0">{{ $adminHeaderContext }}</p>
                </div>
            </div>
            <div class="admin-header-actions">
                <a href="{{ route('home') }}" class="header-btn">View website</a>
            </div>
        </header>
        <div class="admin-page-content">@yield('content')</div>
    </div>
</div></div>
<dialog id="admin-password-dialog" aria-labelledby="admin-password-title" style="width:min(440px,calc(100vw - 2rem));padding:1.35rem;border:1px solid var(--line);border-radius:12px;color:var(--ink);background:var(--surface);box-shadow:0 18px 48px rgba(0,0,0,.24)">
    <h2 id="admin-password-title" class="h5 mb-2">Confirm administrator password</h2>
    <p id="admin-password-current-admin" class="small mb-2" hidden></p>
    <p id="admin-password-message" class="text-muted mb-3"></p>
    <form id="admin-password-dialog-form">
        <label id="admin-password-label" for="admin-password-input" class="form-label">Administrator password</label>
        <input id="admin-password-input" type="password" class="form-control" autocomplete="current-password" required>
        <div class="d-flex justify-content-end gap-2 mt-4">
            <button type="button" id="admin-password-cancel" class="btn btn-outline-secondary">Cancel</button>
            <button id="admin-password-submit" type="submit" class="btn luxury-btn">Continue</button>
        </div>
    </form>
</dialog>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const sidebar = document.getElementById('adminSidebar');
    const mobileMenu = document.getElementById('adminMobileMenu');
    const navBackdrop = document.getElementById('adminNavBackdrop');
    const mobileBreakpoint = window.matchMedia('(max-width: 991.98px)');
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = themeToggle?.querySelector('.sidebar-icon');
    const savedTheme = localStorage.getItem('admin-theme');

    const setNavigationOpen = (open, restoreFocus = false) => {
        document.body.classList.toggle('mobile-admin-nav-open', open);
        mobileMenu?.setAttribute('aria-expanded', String(open));
        mobileMenu?.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
        mobileMenu?.setAttribute('title', open ? 'Close navigation' : 'Open navigation');
        if (sidebar) sidebar.inert = mobileBreakpoint.matches && !open;
        if (navBackdrop) navBackdrop.tabIndex = open ? 0 : -1;
        if (open) sidebar?.querySelector('.nav-link')?.focus();
        else if (restoreFocus) mobileMenu?.focus();
    };

    setNavigationOpen(false);
    mobileMenu?.addEventListener('click', () => {
        setNavigationOpen(!document.body.classList.contains('mobile-admin-nav-open'));
    });
    navBackdrop?.addEventListener('click', () => setNavigationOpen(false, true));
    document.querySelectorAll('.sidebar .nav-link').forEach((link) => {
        link.addEventListener('click', () => setNavigationOpen(false));
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && document.body.classList.contains('mobile-admin-nav-open')) {
            setNavigationOpen(false, true);
        }
    });
    mobileBreakpoint.addEventListener('change', (event) => {
        if (!event.matches) setNavigationOpen(false);
        else if (!document.body.classList.contains('mobile-admin-nav-open') && sidebar) sidebar.inert = true;
    });

    const applyAdminTheme = (dark) => {
        document.body.classList.toggle('dark-mode', dark);
        if (themeIcon) themeIcon.innerHTML = dark ? '&#9788;' : '&#9790;';
        themeToggle?.setAttribute('aria-label', dark ? 'Enable light mode' : 'Enable dark mode');
        themeToggle?.setAttribute('title', dark ? 'Enable light mode' : 'Enable dark mode');
        themeToggle?.setAttribute('aria-pressed', String(dark));
    };

    applyAdminTheme(savedTheme === 'dark');
    themeToggle?.addEventListener('click', () => {
        const dark = !document.body.classList.contains('dark-mode');
        applyAdminTheme(dark);
        localStorage.setItem('admin-theme', dark ? 'dark' : 'light');
    });
})();
</script>
<script>
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get' || form.hasAttribute('onsubmit') || form.hasAttribute('data-password-confirm')) return;

    const label = event.submitter?.textContent.trim().toLowerCase() || '';
    let message = form.dataset.confirmMessage || '';

    if (!message && /^(add|create|upload)\b/.test(label)) message = 'Add this item with the details entered?';
    if (!message && /^(save|update)\b/.test(label)) message = 'Update this item with the changes entered?';

    if (message && !window.confirm(message)) event.preventDefault();
});
// Registered after the confirm handler so a cancelled confirmation leaves the form usable.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-submit-once') || event.defaultPrevented) return;
    if (form.dataset.submitting === 'true') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = 'true';
    form.querySelectorAll('button[type="submit"], button:not([type])').forEach((button) => {
        button.disabled = true;
        button.textContent = 'Saving…';
    });
});
window.addEventListener('pageshow', (event) => {
    // Returning via the back button restores a frozen, disabled form; reload it fresh instead.
    if (event.persisted && document.querySelector('form[data-submit-once][data-submitting="true"]')) window.location.reload();
});
document.querySelectorAll('form input:not([type="hidden"]), form select, form textarea').forEach((field) => {
    if (field.title) return;
    const label = field.id ? Array.from(field.form?.querySelectorAll('label') || []).find((item) => item.htmlFor === field.id) : null;
    const fieldName = (label?.textContent || field.placeholder || field.name || 'this field').trim().replace(/\s+/g, ' ').toLowerCase();
    const instruction = field.type === 'email' ? 'Enter a valid email address.'
        : field.type === 'tel' ? 'Enter 09 followed by 9 digits or +63 followed by 10 digits, with no spaces.'
            : field.type === 'date' ? 'Choose a date.'
            : field.type === 'time' ? 'Choose a time.'
                : field.type === 'file' ? 'Choose a file that meets the accepted format and size.'
                    : field instanceof HTMLSelectElement ? `Choose ${fieldName}.`
                        : field instanceof HTMLTextAreaElement ? `Describe ${fieldName}.`
                            : field.type === 'password' ? 'Enter your password.'
                                : `Enter ${fieldName}.`;
    field.title = instruction;
});
</script>
<script>
(() => {
    const dialog = document.getElementById('admin-password-dialog');
    const dialogForm = document.getElementById('admin-password-dialog-form');
    const passwordInput = document.getElementById('admin-password-input');
    const passwordMessage = document.getElementById('admin-password-message');
    const passwordTitle = document.getElementById('admin-password-title');
    const passwordLabel = document.getElementById('admin-password-label');
    const passwordSubmit = document.getElementById('admin-password-submit');
    const currentAdmin = document.getElementById('admin-password-current-admin');
    let pendingForm = null;
    let pendingSubmitter = null;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-password-confirm')) return;
        if (form.dataset.passwordConfirmed === 'true') {
            delete form.dataset.passwordConfirmed;
            return;
        }

        event.preventDefault();
        pendingForm = form;
        pendingSubmitter = event.submitter;
        passwordTitle.textContent = form.dataset.passwordTitle || 'Confirm administrator password';
        passwordLabel.textContent = form.dataset.passwordLabel || 'Administrator password';
        passwordSubmit.textContent = form.dataset.passwordButton || 'Continue';
        currentAdmin.hidden = !form.dataset.currentAdminName;
        currentAdmin.textContent = form.dataset.currentAdminName
            ? `Currently authenticated Primary Admin: ${form.dataset.currentAdminName}`
            : '';
        passwordMessage.textContent = form.dataset.passwordMessage || 'Confirm your administrator password to continue.';
        passwordInput.value = '';
        dialog.showModal();
        passwordInput.focus();
    });

    document.getElementById('admin-password-cancel').addEventListener('click', () => dialog.close('cancel'));
    dialog.addEventListener('cancel', () => {
        pendingForm = null;
        pendingSubmitter = null;
    });

    dialogForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!dialogForm.reportValidity() || !pendingForm) return;

        const form = pendingForm;
        const submitter = pendingSubmitter;
        pendingForm = null;
        pendingSubmitter = null;

        const confirmation = document.createElement('input');
        confirmation.type = 'hidden';
        confirmation.name = 'current_admin_password';
        confirmation.value = passwordInput.value;
        form.append(confirmation);
        form.dataset.passwordConfirmed = 'true';
        dialog.close('confirmed');

        if (submitter instanceof HTMLElement && submitter.form === form) {
            form.requestSubmit(submitter);
        } else {
            form.requestSubmit();
        }
    });
})();
</script>
<script>
(() => {
    const toggle = document.getElementById('manageWebsiteToggle');
    const submenu = document.getElementById('manageWebsiteSubnav');
    const authForm = document.getElementById('manageWebsiteAuthForm');

    toggle?.addEventListener('click', () => {
        if (toggle.dataset.manageWebsiteUnlocked !== 'true') {
            authForm?.requestSubmit();
            return;
        }

        const open = toggle.getAttribute('aria-expanded') !== 'true';
        toggle.setAttribute('aria-expanded', String(open));
        submenu.hidden = !open;
    });

    @if(session('manage_website_auth_required') || $errors->has('manage_website_password'))
        authForm?.requestSubmit();
    @endif
})();
</script>
<script>
(() => {
    const forms = [...document.querySelectorAll('form[data-live-filter]')];
    if (forms.length === 0) return;

    const timers = new WeakMap();
    const requests = new WeakMap();

    const formUrl = (form) => {
        const url = new URL(form.action || window.location.href, window.location.href);
        url.search = '';
        url.searchParams.delete('page');
        for (const [key, value] of new FormData(form)) {
            if (typeof value === 'string' && value.trim() !== '') url.searchParams.set(key, value.trim());
        }
        return url;
    };

    const syncFormFromUrl = (form, url) => {
        for (const field of form.elements) {
            if (!field.name || field.type === 'hidden' || field.type === 'submit') continue;
            const value = url.searchParams.get(field.name) || '';
            if (field.type === 'checkbox' || field.type === 'radio') field.checked = value !== '' && field.value === value;
            else field.value = value;
        }
    };

    const refreshResults = async (form, url, historyMode = 'replace') => {
        const selector = form.dataset.liveFilterTarget;
        const currentTarget = document.querySelector(selector);
        const loadingTarget = document.getElementById('activity-log-loading');
        if (!currentTarget) return;

        requests.get(form)?.abort();
        const controller = new AbortController();
        requests.set(form, controller);
        currentTarget.setAttribute('aria-busy', 'true');
        if (loadingTarget) {
            loadingTarget.hidden = false;
            loadingTarget.textContent = 'Loading activity logs...';
        }

        try {
            const response = await fetch(url.href, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error(`Filter request failed (${response.status}).`);

            const html = await response.text();
            const responseDocument = new DOMParser().parseFromString(html, 'text/html');
            const replacement = responseDocument.querySelector(selector);
            if (!replacement) throw new Error('The filtered results could not be found in the response.');

            const countSelector = form.dataset.liveFilterCount;
            if (countSelector) {
                const nextCount = responseDocument.querySelector(countSelector);
                const currentCount = document.querySelector(countSelector);
                if (nextCount && currentCount) currentCount.textContent = nextCount.textContent;
            }

            document.querySelector(selector)?.replaceWith(replacement);
            if (historyMode === 'push') window.history.pushState({ liveFilter: true }, '', url.href);
            else if (historyMode === 'replace') window.history.replaceState({ liveFilter: true }, '', url.href);
        } catch (error) {
            if (error.name !== 'AbortError') console.error(error);
        } finally {
            document.querySelector(selector)?.removeAttribute('aria-busy');
            if (loadingTarget) loadingTarget.hidden = true;
        }
    };

    const applyFormFilters = (form, historyMode = 'replace') => refreshResults(form, formUrl(form), historyMode);
    const scheduleFormFilters = (form) => {
        clearTimeout(timers.get(form));
        timers.set(form, setTimeout(() => applyFormFilters(form), 350));
    };

    forms.forEach((form) => {
        form.addEventListener('input', () => scheduleFormFilters(form));
        form.addEventListener('change', () => {
            clearTimeout(timers.get(form));
            applyFormFilters(form);
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            clearTimeout(timers.get(form));
            applyFormFilters(form);
        });
    });

    document.addEventListener('click', (event) => {
        const clearLink = event.target.closest('[data-live-filter-clear]');
        if (clearLink) {
            event.preventDefault();
            const form = document.querySelector(clearLink.dataset.liveFilterClear);
            if (!form) return;
            form.querySelectorAll('input:not([type="hidden"])').forEach((input) => { input.value = ''; });
            form.querySelectorAll('select').forEach((select) => { select.selectedIndex = 0; });
            clearTimeout(timers.get(form));
            applyFormFilters(form);
            return;
        }

        const link = event.target.closest('a');
        if (!link || !link.closest('.pagination')) return;
        const form = forms.find((candidate) => document.querySelector(candidate.dataset.liveFilterTarget)?.contains(link));
        if (!form) return;

        event.preventDefault();
        refreshResults(form, new URL(link.href, window.location.href), 'push');
    });

    window.addEventListener('popstate', () => {
        const url = new URL(window.location.href);
        forms.forEach((form) => {
            const formPath = new URL(form.action || window.location.href, window.location.href).pathname;
            if (formPath !== url.pathname) return;
            syncFormFromUrl(form, url);
            refreshResults(form, url, 'none');
        });
    });
})();
</script>
</body></html>
