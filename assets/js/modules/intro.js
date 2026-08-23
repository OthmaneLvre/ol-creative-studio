/* =========================================
   SITE INTRO
   ========================================= */

const STORAGE_KEY = 'ol_intro_seen';


export function initIntro() {
    const intro =
        document.querySelector('[data-site-intro]');

    if (!intro) {
        return;
    }


    const prefersReducedMotion =
        window.matchMedia(
            '(prefers-reduced-motion: reduce)'
        ).matches;


    let introSeen = false;

    try {
        introSeen =
            sessionStorage.getItem(STORAGE_KEY)
            === 'true';
    } catch {
        introSeen = false;
    }

    const hideIntro = () => {
        intro.classList.remove(
            'has-failsafe',
            'is-running',
            'is-leaving'
        );

        intro.classList.add(
            'is-hidden'
        );

        document.body.classList.remove(
            'has-site-intro'
        );
    };


    /*
    |--------------------------------------------------------------------------
    | Intro désactivée
    |--------------------------------------------------------------------------
    */

    if (
        prefersReducedMotion ||
        introSeen
    ) {
        hideIntro();
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | Démarrage
    |--------------------------------------------------------------------------
    */

    document.body.classList.add(
        'has-site-intro'
    );

    intro.classList.add(
        'has-failsafe'
    );

    window.setTimeout(() => {
        intro.classList.add(
            'is-running'
        );
    }, 150);


    /*
    |--------------------------------------------------------------------------
    | Sortie
    |--------------------------------------------------------------------------
    */

    window.setTimeout(() => {

        intro.classList.add(
            'is-leaving'
        );

        try {
            sessionStorage.setItem(
                STORAGE_KEY,
                'true'
            );
        } catch {
            // Rien à faire.
        }

    }, 2200);


    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    window.setTimeout(
        hideIntro,
        3000
    );
}