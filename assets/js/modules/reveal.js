export function initRevealAnimations() {

    const elements = document.querySelectorAll('.reveal');

    if (!elements.length) {
        return;
    }

    const prefersReducedMotion = window.matchMedia(
        '(prefers-reduced-motion: reduce)'
    ).matches;

    if (prefersReducedMotion) {

        for (const element of elements) {
            element.classList.add('is-visible');
        }

        return;
    }

    const observer = new IntersectionObserver(
        (entries, revealObserver) => {

            for (const entry of entries) {

                if (!entry.isIntersecting) {
                    continue;
                }

                entry.target.classList.add('is-visible');
                revealObserver.unobserve(entry.target);
            }

        },
        {
            threshold: 0.16,
            rootMargin: '0px 0px -60px 0px'
        }
    );

    for (const element of elements) {
        observer.observe(element);
    }

}