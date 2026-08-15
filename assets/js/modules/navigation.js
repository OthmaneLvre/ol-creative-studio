/* =========================================
   NAVIGATION
   ========================================= */

export function initNavigation() {
    const header = document.querySelector('[data-header]');
    const menuToggle = document.querySelector('[data-menu-toggle]');
    const mobileNavigation = document.querySelector(
        '[data-mobile-navigation]'
    );

    if (!header) {
        return;
    }

    const mobileLinks = mobileNavigation
        ? mobileNavigation.querySelectorAll('a')
        : [];

    const mobileBreakpoint = window.matchMedia('(max-width: 820px)');

    let isMenuOpen = false;


    /* ==================================================
       HEADER SCROLL STATE
    ================================================== */

    const updateHeaderScrollState = () => {
        const isScrolled = window.scrollY > 24;

        header.classList.toggle(
            'is-scrolled',
            isScrolled
        );
    };

    updateHeaderScrollState();

    window.addEventListener(
        'scroll',
        updateHeaderScrollState,
        {
            passive: true,
        }
    );


    /* ==================================================
       MENU STATE
    ================================================== */

    const openMenu = () => {
        if (
            !menuToggle ||
            !mobileNavigation ||
            !mobileBreakpoint.matches
        ) {
            return;
        }

        isMenuOpen = true;

        header.classList.add('is-menu-open');
        document.body.classList.add('menu-open');

        menuToggle.setAttribute(
            'aria-expanded',
            'true'
        );

        menuToggle.setAttribute(
            'aria-label',
            'Fermer le menu'
        );

        mobileNavigation.setAttribute(
            'aria-hidden',
            'false'
        );

        const firstLink =
            mobileNavigation.querySelector('a');

        firstLink?.focus();
    };


    const closeMenu = ({
        restoreFocus = true,
    } = {}) => {
        if (
            !menuToggle ||
            !mobileNavigation
        ) {
            return;
        }

        isMenuOpen = false;

        header.classList.remove('is-menu-open');
        document.body.classList.remove('menu-open');

        menuToggle.setAttribute(
            'aria-expanded',
            'false'
        );

        menuToggle.setAttribute(
            'aria-label',
            'Ouvrir le menu'
        );

        mobileNavigation.setAttribute(
            'aria-hidden',
            'true'
        );

        if (restoreFocus) {
            menuToggle.focus();
        }
    };


    const toggleMenu = () => {
        if (isMenuOpen) {
            closeMenu();
            return;
        }

        openMenu();
    };


    /* ==================================================
       TOGGLE BUTTON
    ================================================== */

    menuToggle?.addEventListener(
        'click',
        toggleMenu
    );


    /* ==================================================
       CLOSE AFTER NAVIGATION
    ================================================== */

    mobileLinks.forEach((link) => {
        link.addEventListener('click', () => {

            link.blur();
            
            header.classList.add('is-navigating');

            closeMenu({
                restoreFocus: false,
            });
        });
    });


    /* ==================================================
       KEYBOARD
    ================================================== */

    document.addEventListener(
        'keydown',
        (event) => {
            if (!isMenuOpen) {
                return;
            }

            if (event.key === 'Escape') {
                closeMenu();
                return;
            }

            if (event.key !== 'Tab') {
                return;
            }

            trapFocus(event);
        }
    );


    /* ==================================================
       FOCUS TRAP
    ================================================== */

    const trapFocus = (event) => {
        if (
            !mobileNavigation ||
            !menuToggle
        ) {
            return;
        }

        const focusableElements = [
            menuToggle,
            ...mobileNavigation.querySelectorAll(
                [
                    'a[href]',
                    'button:not([disabled])',
                    'input:not([disabled])',
                    'select:not([disabled])',
                    'textarea:not([disabled])',
                    '[tabindex]:not([tabindex="-1"])',
                ].join(',')
            ),
        ];

        if (!focusableElements.length) {
            return;
        }

        const firstElement =
            focusableElements[0];

        const lastElement =
            focusableElements[
                focusableElements.length - 1
            ];

        if (
            event.shiftKey &&
            document.activeElement === firstElement
        ) {
            event.preventDefault();
            lastElement.focus();
            return;
        }

        if (
            !event.shiftKey &&
            document.activeElement === lastElement
        ) {
            event.preventDefault();
            firstElement.focus();
        }
    };


    /* ==================================================
       BREAKPOINT CHANGE
    ================================================== */

    const handleBreakpointChange = (event) => {
        if (!event.matches && isMenuOpen) {
            closeMenu({
                restoreFocus: false,
            });
        }
    };

    mobileBreakpoint.addEventListener(
        'change',
        handleBreakpointChange
    );
}