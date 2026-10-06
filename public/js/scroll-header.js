// Shared by the guest and admin layouts: hides the [data-scroll-header] element while
// scrolling down and reveals it when scrolling up or back at the top of the page.
(() => {
    const header = document.querySelector('[data-scroll-header]');
    if (!header) return;

    let previousScrollY = window.scrollY;
    const threshold = 12;

    const showHeader = () => header.classList.remove('header-hidden');
    const hideHeader = () => header.classList.add('header-hidden');

    const handleScroll = () => {
        const currentScrollY = window.scrollY;

        if (currentScrollY <= 0) {
            showHeader();
            previousScrollY = currentScrollY;
            return;
        }

        if (Math.abs(currentScrollY - previousScrollY) < threshold) {
            return;
        }

        if (currentScrollY > previousScrollY) {
            hideHeader();
        } else {
            showHeader();
        }

        previousScrollY = currentScrollY;
    };

    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll();
})();
