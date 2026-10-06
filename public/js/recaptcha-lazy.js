(() => {
    const widgets = Array.from(document.querySelectorAll('.g-recaptcha[data-sitekey]'));
    if (widgets.length === 0) return;

    const states = new WeakMap();
    let apiLoaded = Boolean(window.grecaptcha);
    let apiLoading = false;

    const setStatus = (widget, message) => {
        const status = widget.closest('[data-recaptcha-container]')?.querySelector('[data-recaptcha-status]');
        if (!status) return;
        status.textContent = message;
        status.hidden = message === '';
    };

    const renderVisibleWidgets = () => {
        if (!window.grecaptcha) return;

        window.grecaptcha.ready(() => {
            widgets.forEach((widget) => {
                const state = states.get(widget);
                if (!state.visible || state.widgetId !== null) return;

                state.widgetId = window.grecaptcha.render(widget, {
                    sitekey: widget.dataset.sitekey,
                    callback: () => setStatus(widget, ''),
                    'expired-callback': () => setStatus(widget, 'Verification expired. Please complete the checkbox again.'),
                    'error-callback': () => setStatus(widget, 'Verification could not be completed. Check your connection and try again.'),
                });
                setStatus(widget, '');
            });
        });
    };

    const loadApi = () => {
        if (window.grecaptcha) {
            apiLoaded = true;
            renderVisibleWidgets();
            return;
        }
        if (apiLoading) return;

        apiLoading = true;
        widgets.forEach((widget) => {
            if (states.get(widget).visible) setStatus(widget, 'Loading verification…');
        });

        const script = document.createElement('script');
        script.src = 'https://www.google.com/recaptcha/api.js?onload=on3yosRecaptchaLoaded&render=explicit';
        script.async = true;
        script.onload = () => {
            apiLoading = false;
            apiLoaded = Boolean(window.grecaptcha);
            if (apiLoaded) renderVisibleWidgets();
        };
        script.onerror = () => {
            apiLoading = false;
            widgets.forEach((widget) => {
                if (states.get(widget).visible) {
                    setStatus(widget, 'Verification could not load. Check your connection and try again.');
                }
            });
        };
        document.head.appendChild(script);
    };

    window.on3yosRecaptchaLoaded = () => {
        apiLoading = false;
        apiLoaded = true;
        renderVisibleWidgets();
    };

    widgets.forEach((widget) => {
        states.set(widget, { visible: false, widgetId: null });

        const form = widget.closest('form');
        if (!form) return;

        form.addEventListener('submit', (event) => {
            const state = states.get(widget);
            const response = apiLoaded && state.widgetId !== null
                ? window.grecaptcha.getResponse(state.widgetId).trim()
                : '';

            if (response !== '') return;

            event.preventDefault();
            state.visible = true;
            setStatus(widget, 'Please complete the reCAPTCHA checkbox before submitting.');
            loadApi();
            widget.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    });

    const observeWidgets = () => {
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    states.get(entry.target).visible = true;
                    loadApi();
                    observer.unobserve(entry.target);
                });
            }, { rootMargin: '160px 0px' });

            widgets.forEach((widget) => observer.observe(widget));
            return;
        }

        widgets.forEach((widget) => {
            states.get(widget).visible = true;
            loadApi();
        });
    };

    if (document.readyState === 'complete') {
        window.setTimeout(observeWidgets, 0);
    } else {
        window.addEventListener('load', observeWidgets, { once: true });
    }
})();
