document.addEventListener('DOMContentLoaded', () => {

    const hero = document.querySelector('.home-hero');

    if (!hero) {
        return;
    }

    /*
    |--------------------------------------------------------------------------
    | HERO — INTRODUCTION
    |--------------------------------------------------------------------------
    */

    requestAnimationFrame(() => {
        hero.classList.add('is-loaded');
    });


    /*
    |--------------------------------------------------------------------------
    | HERO — PARALLAX LÉGER DU VISUEL
    |--------------------------------------------------------------------------
    */

    const visual = hero.querySelector('.home-hero__visual-card');

    const canUsePointerEffect =
        visual &&
        window.matchMedia('(pointer: fine)').matches &&
        !window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (!canUsePointerEffect) {
        return;
    }

    visual.addEventListener('mousemove', (event) => {

        const rect = visual.getBoundingClientRect();

        const x = event.clientX - rect.left;
        const y = event.clientY - rect.top;

        const centerX = rect.width / 2;
        const centerY = rect.height / 2;

        const rotateX = ((y - centerY) / centerY) * -1.5;
        const rotateY = ((x - centerX) / centerX) * 1.5;

        visual.style.transform = `
            perspective(1000px)
            rotateX(${rotateX}deg)
            rotateY(${rotateY}deg)
            translateY(-4px)
        `;
    });

    visual.addEventListener('mouseleave', () => {
        visual.style.transform = '';
    });

    /*
    |--------------------------------------------------------------------------
    | SERVICES — REVEAL AU SCROLL
    |--------------------------------------------------------------------------
    */

    const servicesSection = document.querySelector('.home-services');

    if (servicesSection) {

        const servicesHeader = servicesSection.querySelector(
            '.home-services__header'
        );

        const serviceItems = servicesSection.querySelectorAll(
            '.home-service'
        );

        const servicesFooter = servicesSection.querySelector(
            '.home-services__footer'
        );

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        if (prefersReducedMotion) {

            servicesHeader?.classList.add('is-visible');

            serviceItems.forEach((item) => {
                item.classList.add('is-visible');
            });

            servicesFooter?.classList.add('is-visible');

        } else {

            const servicesObserver = new IntersectionObserver(
                (entries, observer) => {

                    entries.forEach((entry) => {

                        if (!entry.isIntersecting) {
                            return;
                        }

                        const target = entry.target;

                        target.classList.add('is-visible');

                        observer.unobserve(target);

                    });

                },
                {
                    threshold: 0.18,
                    rootMargin: '0px 0px -70px 0px'
                }
            );

            if (servicesHeader) {
                servicesObserver.observe(servicesHeader);
            }

            serviceItems.forEach((item, index) => {

                item.style.setProperty(
                    '--service-delay',
                    `${index * 110}ms`
                );

                servicesObserver.observe(item);
            });

            if (servicesFooter) {
                servicesObserver.observe(servicesFooter);
            }

        }

    }

});