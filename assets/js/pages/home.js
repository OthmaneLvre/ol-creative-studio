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

    if (canUsePointerEffect) {

        visual.addEventListener('mousemove', (event) => {

            const rect = visual.getBoundingClientRect();

            const x = event.clientX - rect.left;
            const y = event.clientY - rect.top;

            const centerX = rect.width / 2;
            const centerY = rect.height / 2;

            const rotateX =
                ((y - centerY) / centerY) * -1.5;

            const rotateY =
                ((x - centerX) / centerX) * 1.5;

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

    }
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

    /*
    |--------------------------------------------------------------------------
    | FEATURED PROJECT — REVEAL
    |--------------------------------------------------------------------------
    */

    const featuredProject = document.querySelector('.home-featured-project');

    if (featuredProject) {

        const projectHeader = featuredProject.querySelector(
            '.home-featured-project__header'
        );

        const projectVisual = featuredProject.querySelector(
            '.home-featured-project__visual'
        );

        const projectDetails = featuredProject.querySelector(
            '.home-featured-project__details'
        );

        const projectFooter = featuredProject.querySelector(
            '.home-featured-project__footer'
        );

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        const revealElements = [
            projectHeader,
            projectVisual,
            projectDetails,
            projectFooter
        ].filter(Boolean);

        if (prefersReducedMotion) {

            revealElements.forEach((element) => {
                element.classList.add('is-visible');
            });

        } else {

            const projectObserver = new IntersectionObserver(
                (entries, observer) => {

                    entries.forEach((entry) => {

                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);

                    });

                },
                {
                    threshold: 0.15,
                    rootMargin: '0px 0px -80px 0px'
                }
            );

            revealElements.forEach((element) => {
                projectObserver.observe(element);
            });

        }


        /*
        |--------------------------------------------------------------------------
        | FEATURED PROJECT — SCROLL INTERNE
        |--------------------------------------------------------------------------
        */

        const media = featuredProject.querySelector(
            '.home-featured-project__media'
        );

        const projectImage = featuredProject.querySelector(
            '.home-featured-project__media img'
        );

        const canAnimateProject =
            media &&
            projectImage &&
            window.matchMedia('(pointer: fine)').matches &&
            !prefersReducedMotion;

        if (canAnimateProject) {

            let currentTranslate = 0;

            const getMaxTranslate = () => {
                return Math.max(
                    0,
                    projectImage.offsetHeight - media.offsetHeight
                );
            };

            const updateImagePosition = () => {

                projectImage.style.transform =
                    `translate3d(0, -${currentTranslate}px, 0)`;
            };

            media.addEventListener(
                'wheel',
                (event) => {

                    const maxTranslate = getMaxTranslate();

                    /*
                    |--------------------------------------------------------------------------
                    | Aucun scroll possible dans l'image
                    |--------------------------------------------------------------------------
                    */

                    if (maxTranslate <= 0) {
                        return;
                    }

                    const scrollingDown = event.deltaY > 0;
                    const scrollingUp = event.deltaY < 0;

                    const isAtTop =
                        currentTranslate <= 0;

                    const isAtBottom =
                        currentTranslate >= maxTranslate;

                    /*
                    |--------------------------------------------------------------------------
                    | Si on est arrivé à une extrémité,
                    | on rend le scroll à la page.
                    |--------------------------------------------------------------------------
                    */

                    if (
                        (scrollingUp && isAtTop) ||
                        (scrollingDown && isAtBottom)
                    ) {
                        return;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Sinon, on bloque le scroll de la page
                    | et on fait défiler uniquement Below Dreams.
                    |--------------------------------------------------------------------------
                    */

                    event.preventDefault();

                    currentTranslate += event.deltaY * 0.5;

                    currentTranslate = Math.max(
                        0,
                        Math.min(
                            maxTranslate,
                            currentTranslate
                        )
                    );

                    updateImagePosition();

                },
                {
                    passive: false
                }
            );

            window.addEventListener(
                'resize',
                () => {

                    const maxTranslate = getMaxTranslate();

                    currentTranslate = Math.min(
                        currentTranslate,
                        maxTranslate
                    );

                    updateImagePosition();

                }
            );

        }

    }

    /*
    |--------------------------------------------------------------------------
    | ABOUT — REVEAL AU SCROLL
    |--------------------------------------------------------------------------
    */

    const aboutSection = document.querySelector('.home-about');

    if (aboutSection) {

        const aboutHeader = aboutSection.querySelector(
            '.home-about__header'
        );

        const aboutStatement = aboutSection.querySelector(
            '.home-about__statement'
        );

        const aboutDetails = aboutSection.querySelector(
            '.home-about__details'
        );

        const aboutMetaItems = aboutSection.querySelectorAll(
            '.home-about__meta-item'
        );

        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;

        if (prefersReducedMotion) {

            aboutHeader?.classList.add('is-visible');
            aboutStatement?.classList.add('is-visible');
            aboutDetails?.classList.add('is-visible');

            aboutMetaItems.forEach((item) => {
                item.classList.add('is-visible');
            });

        } else {

            const aboutObserver = new IntersectionObserver(
                (entries, observer) => {

                    entries.forEach((entry) => {

                        if (!entry.isIntersecting) {
                            return;
                        }

                        entry.target.classList.add('is-visible');

                        observer.unobserve(entry.target);

                    });

                },
                {
                    threshold: 0.18,
                    rootMargin: '0px 0px -70px 0px'
                }
            );

            if (aboutHeader) {
                aboutObserver.observe(aboutHeader);
            }

            if (aboutStatement) {
                aboutObserver.observe(aboutStatement);
            }

            if (aboutDetails) {
                aboutObserver.observe(aboutDetails);
            }

            aboutMetaItems.forEach((item, index) => {

                item.style.setProperty(
                    '--about-delay',
                    `${index * 120}ms`
                );

                aboutObserver.observe(item);

            });

        }

    }
});