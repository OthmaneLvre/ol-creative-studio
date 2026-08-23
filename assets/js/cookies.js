/* =========================================
   OL CREATIVE STUDIO — COOKIE CONSENT
   ========================================= */

document.addEventListener(
    'DOMContentLoaded',
    () => {

        /* =========================================
           CONFIGURATION
           ========================================= */

        const STORAGE_KEY =
            'ol_cookie_consent';

        const CONSENT_VERSION = 1;

        const CONSENT_DURATION =
            180 * 24 * 60 * 60 * 1000;


        /* =========================================
           ELEMENTS
           ========================================= */

        const banner =
            document.getElementById(
                'cookie-banner'
            );

        const modal =
            document.getElementById(
                'cookie-modal'
            );

        const acceptButton =
            document.getElementById(
                'cookie-accept'
            );

        const refuseButton =
            document.getElementById(
                'cookie-refuse'
            );

        const customizeButton =
            document.getElementById(
                'cookie-customize'
            );

        const cancelButton =
            document.getElementById(
                'cookie-cancel'
            );

        const saveButton =
            document.getElementById(
                'cookie-save'
            );

        const analyticsCheckbox =
            document.getElementById(
                'analytics-consent'
            );


        /* =========================================
           GUARD
           ========================================= */

        if (
            !banner ||
            !modal ||
            !acceptButton ||
            !refuseButton ||
            !customizeButton ||
            !cancelButton ||
            !saveButton ||
            !analyticsCheckbox
        ) {
            return;
        }


        /* =========================================
           GOOGLE CONSENT
           ========================================= */

        const updateGoogleConsent = (
            analyticsAllowed
        ) => {

            if (
                typeof window.gtag
                !== 'function'
            ) {
                return;
            }

            window.gtag(
                'consent',
                'update',
                {
                    analytics_storage:
                        analyticsAllowed
                            ? 'granted'
                            : 'denied',

                    ad_storage:
                        'denied',

                    ad_user_data:
                        'denied',

                    ad_personalization:
                        'denied',
                }
            );
        };


        /* =========================================
           STORAGE
           ========================================= */

        const readConsent = () => {

            try {

                const stored =
                    localStorage.getItem(
                        STORAGE_KEY
                    );

                if (!stored) {
                    return null;
                }

                const consent =
                    JSON.parse(stored);

                if (
                    typeof consent !== 'object' ||
                    consent === null
                ) {
                    return null;
                }

                if (
                    consent.version
                    !== CONSENT_VERSION
                ) {
                    return null;
                }

                if (
                    typeof consent.createdAt
                    !== 'number'
                ) {
                    return null;
                }

                const expired =
                    Date.now()
                    - consent.createdAt
                    > CONSENT_DURATION;

                if (expired) {

                    localStorage.removeItem(
                        STORAGE_KEY
                    );

                    return null;
                }

                return consent;

            } catch {

                localStorage.removeItem(
                    STORAGE_KEY
                );

                return null;
            }
        };


        const saveConsent = (
            analytics
        ) => {

            const consent = {
                version:
                    CONSENT_VERSION,

                analytics:
                    analytics,

                createdAt:
                    Date.now(),
            };

            try {

                localStorage.setItem(
                    STORAGE_KEY,
                    JSON.stringify(consent)
                );

            } catch {
                // Le stockage local peut
                // être indisponible.
            }

            return consent;
        };


        /* =========================================
           GOOGLE ANALYTICS
           ========================================= */

        let analyticsLoaded = false;

        const enableAnalytics = () => {

            if (analyticsLoaded) {
                return;
            }

            updateGoogleConsent(true);

            if (
                typeof window
                    .loadGoogleAnalytics
                === 'function'
            ) {

                window
                    .loadGoogleAnalytics();

                analyticsLoaded = true;
            }
        };


        const disableAnalytics = () => {

            updateGoogleConsent(false);
        };


        /* =========================================
           UI
           ========================================= */

        const showBanner = () => {
            banner.style.display = 'flex';
        };


        const hideBanner = () => {
            banner.style.display = 'none';
        };


        const openModal = () => {

            const consent =
                readConsent();

            analyticsCheckbox.checked =
                consent?.analytics
                === true;

            modal.style.display = 'flex';
        };


        const closeModal = () => {
            modal.style.display = 'none';
        };


        /* =========================================
           DEFAULT CONSENT
           ========================================= */

        updateGoogleConsent(false);


        /* =========================================
           INITIAL STATE
           ========================================= */

        const consent =
            readConsent();

        if (!consent) {

            showBanner();

        } else if (
            consent.analytics
            === true
        ) {

            enableAnalytics();

        } else {

            disableAnalytics();
        }


        /* =========================================
           ACCEPT ALL
           ========================================= */

        acceptButton.addEventListener(
            'click',
            () => {

                saveConsent(true);

                enableAnalytics();

                hideBanner();
                closeModal();
            }
        );


        /* =========================================
           REFUSE
           ========================================= */

        refuseButton.addEventListener(
            'click',
            () => {

                saveConsent(false);

                disableAnalytics();

                analyticsCheckbox.checked =
                    false;

                hideBanner();
                closeModal();
            }
        );


        /* =========================================
           CUSTOMIZE
           ========================================= */

        customizeButton.addEventListener(
            'click',
            openModal
        );


        /* =========================================
           CANCEL
           ========================================= */

        cancelButton.addEventListener(
            'click',
            closeModal
        );


        /* =========================================
           SAVE CUSTOM CHOICE
           ========================================= */

        saveButton.addEventListener(
            'click',
            () => {

                const allowAnalytics =
                    analyticsCheckbox
                        .checked;

                saveConsent(
                    allowAnalytics
                );

                if (allowAnalytics) {

                    enableAnalytics();

                } else {

                    disableAnalytics();
                }

                closeModal();
                hideBanner();
            }
        );


        /* =========================================
           MANAGE CONSENT
           ========================================= */

        const settingsButtons =
            document.querySelectorAll(
                '[data-cookie-settings]'
            );

        for (
            const button
            of settingsButtons
        ) {

            button.addEventListener(
                'click',
                (event) => {

                    event.preventDefault();

                    openModal();
                }
            );
        }


        /* =========================================
           ESCAPE KEY
           ========================================= */

        document.addEventListener(
            'keydown',
            (event) => {

                if (
                    event.key
                    !== 'Escape'
                ) {
                    return;
                }

                if (
                    modal.style.display
                    === 'flex'
                ) {
                    closeModal();
                }
            }
        );

    }
);