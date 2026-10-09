(() => {
    const page = document.querySelector('.page');
    if (!page) return;

    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!reducedMotion && 'IntersectionObserver' in window) {
        const sections = page.querySelectorAll(
            ':scope > .page-heading, :scope > .panel, :scope > .content-grid, :scope > .report-layout, :scope > .table-toolbar'
        );
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-visible');
                currentObserver.unobserve(entry.target);
            });
        }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });

        sections.forEach((section) => {
            section.classList.add('motion-reveal');
            observer.observe(section);
        });
    }

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const link = event.target.closest('a[href]');
        if (!link || link.closest('.topbar') || link.hasAttribute('data-student-qr-link') || link.hasAttribute('download') || link.target && link.target !== '_self') return;

        const destination = new URL(link.href, window.location.href);
        if (!['http:', 'https:'].includes(destination.protocol) || destination.origin !== window.location.origin) return;

        const sameDocument = destination.pathname === window.location.pathname
            && destination.search === window.location.search;
        if (sameDocument && destination.hash) {
            const target = document.getElementById(decodeURIComponent(destination.hash.slice(1)));
            if (!target) return;

            event.preventDefault();
            window.history.pushState(null, '', destination.hash);
            target.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
            return;
        }

        if (sameDocument) return;

        event.preventDefault();
        if (reducedMotion) {
            window.location.assign(destination.href);
            return;
        }

        let navigated = false;
        const navigate = () => {
            if (navigated) return;
            navigated = true;
            window.location.assign(destination.href);
        };

        page.addEventListener('animationend', (animationEvent) => {
            if (animationEvent.animationName === 'page-leave') navigate();
        }, { once: true });
        page.classList.add('is-leaving');
        window.setTimeout(navigate, 180);
    }, true);
})();