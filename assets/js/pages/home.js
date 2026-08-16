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

});