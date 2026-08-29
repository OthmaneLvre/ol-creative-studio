/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;


/**
 * Crée un IntersectionObserver réutilisable.
 */
const createRevealObserver = (
    threshold = 0.18,
    rootMargin = '0px 0px -70px 0px'
) => {

    return new IntersectionObserver(
        (entries, observer) => {

            for (const entry of entries) {

                if (!entry.isIntersecting) {
                    continue;
                }

                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }

        },
        {
            threshold,
            rootMargin
        }
    );

};


/**
 * Affiche immédiatement les éléments.
 * Utilisé notamment avec prefers-reduced-motion.
 */
const revealImmediately = (elements) => {

    for (const element of elements) {

        if (!element) {
            continue;
        }

        element.classList.add('is-visible');
    }

};


/*
|--------------------------------------------------------------------------
| HERO
|--------------------------------------------------------------------------
*/

function initHero() {

    const hero = document.querySelector('.home-hero');

    if (!hero) {
        return;
    }

    requestAnimationFrame(() => {
        hero.classList.add('is-loaded');
    });

    initHeroVisual(hero);
}


/*
|--------------------------------------------------------------------------
| HERO — PARALLAX LÉGER DU VISUEL
|--------------------------------------------------------------------------
*/

function initHeroVisual(hero) {

    const visual = hero.querySelector(
        '.home-hero__visual-card'
    );

    const canUsePointerEffect =
        visual &&
        window.matchMedia('(pointer: fine)').matches &&
        !prefersReducedMotion();

    if (!canUsePointerEffect) {
        return;
    }

    visual.addEventListener(
        'mousemove',
        (event) => {

            const rect =
                visual.getBoundingClientRect();

            const x =
                event.clientX - rect.left;

            const y =
                event.clientY - rect.top;

            const centerX =
                rect.width / 2;

            const centerY =
                rect.height / 2;

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
        }
    );

    visual.addEventListener(
        'mouseleave',
        () => {
            visual.style.transform = '';
        }
    );
}


/*
|--------------------------------------------------------------------------
| SERVICES
|--------------------------------------------------------------------------
*/

function initServices() {

    const section =
        document.querySelector('.home-services');

    if (!section) {
        return;
    }

    const header =
        section.querySelector(
            '.home-services__header'
        );

    const items =
        section.querySelectorAll(
            '.home-service'
        );

    const footer =
        section.querySelector(
            '.home-services__footer'
        );

    const elements = [
        header,
        ...items,
        footer
    ].filter(Boolean);

    if (prefersReducedMotion()) {

        revealImmediately(elements);
        return;
    }

    const observer =
        createRevealObserver(
            0.18,
            '0px 0px -70px 0px'
        );

    if (header) {
        observer.observe(header);
    }

    let index = 0;

    for (const item of items) {

        item.style.setProperty(
            '--service-delay',
            `${index * 110}ms`
        );

        observer.observe(item);

        index += 1;
    }

    if (footer) {
        observer.observe(footer);
    }
}


/*
|--------------------------------------------------------------------------
| FEATURED PROJECT
|--------------------------------------------------------------------------
*/

function initFeaturedProject() {

    const section =
        document.querySelector(
            '.home-featured-project'
        );

    if (!section) {
        return;
    }

    initFeaturedProjectReveal(section);
    initFeaturedProjectScroll(section);
}


/*
|--------------------------------------------------------------------------
| FEATURED PROJECT — REVEAL
|--------------------------------------------------------------------------
*/

function initFeaturedProjectReveal(section) {

    const elements = [
        section.querySelector(
            '.home-featured-project__header'
        ),
        section.querySelector(
            '.home-featured-project__visual'
        ),
        section.querySelector(
            '.home-featured-project__details'
        ),
        section.querySelector(
            '.home-featured-project__footer'
        )
    ].filter(Boolean);

    if (prefersReducedMotion()) {

        revealImmediately(elements);
        return;
    }

    const observer =
        createRevealObserver(
            0.15,
            '0px 0px -80px 0px'
        );

    for (const element of elements) {
        observer.observe(element);
    }
}


/*
|--------------------------------------------------------------------------
| FEATURED PROJECT — SCROLL INTERNE
|--------------------------------------------------------------------------
*/

function initFeaturedProjectScroll(section) {

    const media =
        section.querySelector(
            '.home-featured-project__media'
        );

    const projectImage =
        section.querySelector(
            '.home-featured-project__media img'
        );

    const canAnimateProject =
        media &&
        projectImage &&
        window.matchMedia('(pointer: fine)').matches &&
        !prefersReducedMotion();

    if (!canAnimateProject) {
        return;
    }

    let currentTranslate = 0;


    const getMaxTranslate = () => {

        return Math.max(
            0,
            projectImage.offsetHeight -
            media.offsetHeight
        );
    };


    const updateImagePosition = () => {

        projectImage.style.transform =
            `translate3d(
                0,
                -${currentTranslate}px,
                0
            )`;
    };


    media.addEventListener(
        'wheel',
        (event) => {

            const maxTranslate =
                getMaxTranslate();

            if (maxTranslate <= 0) {
                return;
            }

            const scrollingDown =
                event.deltaY > 0;

            const scrollingUp =
                event.deltaY < 0;

            const isAtTop =
                currentTranslate <= 0;

            const isAtBottom =
                currentTranslate >= maxTranslate;

            const shouldReleaseScroll =
                (scrollingUp && isAtTop) ||
                (scrollingDown && isAtBottom);

            if (shouldReleaseScroll) {
                return;
            }

            event.preventDefault();

            currentTranslate +=
                event.deltaY * 0.5;

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

            const maxTranslate =
                getMaxTranslate();

            currentTranslate =
                Math.min(
                    currentTranslate,
                    maxTranslate
                );

            updateImagePosition();
        }
    );
}


/*
|--------------------------------------------------------------------------
| ABOUT
|--------------------------------------------------------------------------
*/

function initAbout() {

    const section =
        document.querySelector('.home-about');

    if (!section) {
        return;
    }

    const header =
        section.querySelector(
            '.home-about__header'
        );

    const statement =
        section.querySelector(
            '.home-about__statement'
        );

    const details =
        section.querySelector(
            '.home-about__details'
        );

    const metaItems =
        section.querySelectorAll(
            '.home-about__meta-item'
        );

    const elements = [
        header,
        statement,
        details,
        ...metaItems
    ].filter(Boolean);

    if (prefersReducedMotion()) {

        revealImmediately(elements);
        return;
    }

    const observer =
        createRevealObserver(
            0.18,
            '0px 0px -70px 0px'
        );

    if (header) {
        observer.observe(header);
    }

    if (statement) {
        observer.observe(statement);
    }

    if (details) {
        observer.observe(details);
    }

    let index = 0;

    for (const item of metaItems) {

        item.style.setProperty(
            '--about-delay',
            `${index * 120}ms`
        );

        observer.observe(item);

        index += 1;
    }
}


/*
|--------------------------------------------------------------------------
| PORTFOLIO
|--------------------------------------------------------------------------
*/

function initPortfolio() {

    const section =
        document.querySelector(
            '.home-portfolio'
        );

    if (!section) {
        return;
    }

    initPortfolioReveal(section);
    initPortfolioParallax(section);
}


/*
|--------------------------------------------------------------------------
| PORTFOLIO — REVEAL
|--------------------------------------------------------------------------
*/

function initPortfolioReveal(section) {

    const header =
        section.querySelector(
            '.home-portfolio__header'
        );

    const projects =
        section.querySelectorAll(
            '.home-project'
        );

    const footer =
        section.querySelector(
            '.home-portfolio__footer'
        );

    const elements = [
        header,
        ...projects,
        footer
    ].filter(Boolean);

    if (prefersReducedMotion()) {

        revealImmediately(elements);
        return;
    }

    const observer =
        createRevealObserver(
            0.12,
            '0px 0px -70px 0px'
        );

    if (header) {
        observer.observe(header);
    }

    let index = 0;

    for (const project of projects) {

        project.style.setProperty(
            '--portfolio-delay',
            `${index * 120}ms`
        );

        observer.observe(project);

        index += 1;
    }

    if (footer) {
        observer.observe(footer);
    }
}


/*
|--------------------------------------------------------------------------
| PORTFOLIO — PARALLAX AU SCROLL
|--------------------------------------------------------------------------
*/

function initPortfolioParallax(section) {

    const canUseParallax =
        !prefersReducedMotion() &&
        window.matchMedia(
            '(min-width: 769px)'
        ).matches;

    if (!canUseParallax) {
        return;
    }

    const mediaElements =
        section.querySelectorAll(
            '.home-project__media'
        );

    let ticking = false;


    const updatePortfolioParallax = () => {

        const viewportHeight =
            window.innerHeight;

        for (const media of mediaElements) {

            updatePortfolioMedia(
                media,
                viewportHeight
            );
        }

        ticking = false;
    };


    const requestPortfolioParallax = () => {

        if (ticking) {
            return;
        }

        ticking = true;

        requestAnimationFrame(
            updatePortfolioParallax
        );
    };


    window.addEventListener(
        'scroll',
        requestPortfolioParallax,
        {
            passive: true
        }
    );

    window.addEventListener(
        'resize',
        requestPortfolioParallax
    );

    updatePortfolioParallax();
}


/*
|--------------------------------------------------------------------------
| PORTFOLIO — POSITION D'UN SCREENSHOT
|--------------------------------------------------------------------------
*/

function updatePortfolioMedia(
    media,
    viewportHeight
) {

    const image =
        media.querySelector('img');

    if (!image) {
        return;
    }

    const rect =
        media.getBoundingClientRect();

    const isOutsideViewport =
        rect.bottom < 0 ||
        rect.top > viewportHeight;

    if (isOutsideViewport) {
        return;
    }

    const progress =
        (viewportHeight - rect.top) /
        (viewportHeight + rect.height);

    const normalizedProgress =
        Math.max(
            0,
            Math.min(1, progress)
        );

    const maxTranslate =
        Math.max(
            0,
            image.offsetHeight -
            media.offsetHeight
        );

    const parallaxDistance =
        Math.min(
            maxTranslate,
            110
        );

    const translateY =
        normalizedProgress *
        parallaxDistance;

    image.style.transform =
        `translate3d(
            0,
            -${translateY}px,
            0
        )`;
}


/*
|--------------------------------------------------------------------------
| TESTIMONIALS
|--------------------------------------------------------------------------
*/

function initTestimonials() {

    const section =
        document.querySelector(
            '.home-testimonials'
        );

    if (!section) {
        return;
    }

    initTestimonialsSlider(section);
    initTestimonialsReveal(section);
}


/*
|--------------------------------------------------------------------------
| TESTIMONIALS — SLIDER
|--------------------------------------------------------------------------
*/

function initTestimonialsSlider(section) {

    if (
        !Array.isArray(
            window.homeTestimonials
        )
    ) {
        return;
    }

    const slider =
        section.querySelector(
            '[data-testimonials]'
        );

    const textElement =
        section.querySelector(
            '[data-testimonial-text]'
        );

    const nameElement =
        section.querySelector(
            '[data-testimonial-name]'
        );

    const projectElement =
        section.querySelector(
            '[data-testimonial-project]'
        );

    const currentElement =
        section.querySelector(
            '[data-testimonial-current]'
        );

    const prevButton =
        section.querySelector(
            '[data-testimonial-prev]'
        );

    const nextButton =
        section.querySelector(
            '[data-testimonial-next]'
        );

    if (
        !slider ||
        !textElement ||
        !nameElement ||
        !projectElement ||
        !currentElement
    ) {
        return;
    }

    const testimonials =
        window.homeTestimonials;

    let currentIndex = 0;
    let isAnimating = false;


    const updateTestimonial = (index) => {

        if (isAnimating) {
            return;
        }

        isAnimating = true;

        slider.classList.add(
            'is-changing'
        );

        window.setTimeout(
            () => {

                const testimonial =
                    testimonials[index];

                textElement.textContent =
                    testimonial.commentaire ?? '';

                nameElement.textContent =
                    testimonial.nom ?? 'Client';

                projectElement.textContent =
                    testimonial.categorie ??
                    'Projet digital';

                currentElement.textContent =
                    String(index + 1)
                        .padStart(2, '0');

                slider.classList.remove(
                    'is-changing'
                );

                window.setTimeout(
                    () => {
                        isAnimating = false;
                    },
                    350
                );

            },
            220
        );
    };


    const showPrevious = () => {

        currentIndex =
            (
                currentIndex -
                1 +
                testimonials.length
            ) %
            testimonials.length;

        updateTestimonial(
            currentIndex
        );
    };


    const showNext = () => {

        currentIndex =
            (
                currentIndex +
                1
            ) %
            testimonials.length;

        updateTestimonial(
            currentIndex
        );
    };


    prevButton?.addEventListener(
        'click',
        showPrevious
    );

    nextButton?.addEventListener(
        'click',
        showNext
    );
}


/*
|--------------------------------------------------------------------------
| TESTIMONIALS — REVEAL
|--------------------------------------------------------------------------
*/

function initTestimonialsReveal(section) {

    const elements = [
        section.querySelector(
            '.home-testimonials__header'
        ),
        section.querySelector(
            '.home-testimonials__slider'
        )
    ].filter(Boolean);

    if (prefersReducedMotion()) {

        revealImmediately(elements);
        return;
    }

    const observer =
        createRevealObserver(
            0.16,
            '0px 0px -70px 0px'
        );

    for (const element of elements) {
        observer.observe(element);
    }
}


/*
|--------------------------------------------------------------------------
| FINAL CTA
|--------------------------------------------------------------------------
*/

function initFinalCta() {

    const section =
        document.querySelector(
            '.home-cta'
        );

    if (!section) {
        return;
    }

    const inner =
        section.querySelector(
            '.home-cta__inner'
        );

    if (!inner) {
        return;
    }

    if (prefersReducedMotion()) {

        inner.classList.add(
            'is-visible'
        );

        return;
    }

    const observer =
        createRevealObserver(
            0.22,
            '0px 0px -60px 0px'
        );

    observer.observe(inner);
}


/*
|--------------------------------------------------------------------------
| INITIALISATION
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    () => {

        initHero();
        initServices();
        initFeaturedProject();
        initAbout();
        initPortfolio();
        initTestimonials();
        initFinalCta();

    }
);