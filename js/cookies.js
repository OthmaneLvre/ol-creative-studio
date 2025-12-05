document.addEventListener("DOMContentLoaded", () => {

    const banner = document.getElementById("cookie-banner");
    const modal = document.getElementById("cookie-modal");
    const analyticsCheckbox = document.getElementById("analytics-consent");

    // Montrer la bannière si pas encore choisi
    if (!localStorage.getItem("cookieConsent")) {
        banner.style.display = "flex";
    }

    // --- CONSENT MODE PAR DEFAUT ---
    gtag('consent', 'default', {
        'ad_storage': 'denied',
        'analytics_storage': 'denied'
    });

    // Bouton ACCEPTER
    document.getElementById("cookie-accept").addEventListener("click", () => {
        localStorage.setItem("cookieConsent", "accepted");

        gtag('consent', 'update', {
            'ad_storage': 'granted',
            'analytics_storage': 'granted'
        });

        gtag('config', 'G-SVKMC2KRPX');
        banner.style.display = "none";
    });

    // Bouton REFUSER
    document.getElementById("cookie-refuse").addEventListener("click", () => {
        localStorage.setItem("cookieConsent", "refused");

        gtag('consent', 'update', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied'
        });

        banner.style.display = "none";
    });

    // Bouton PERSONNALISER
    document.getElementById("cookie-customize").addEventListener("click", () => {
        modal.style.display = "flex";
    });

    // Bouton ANNULER
    document.getElementById("cookie-cancel").addEventListener("click", () => {
        modal.style.display = "none";
    });

    // Bouton ENREGISTRER
    document.getElementById("cookie-save").addEventListener("click", () => {
        const allowAnalytics = analyticsCheckbox.checked;

        localStorage.setItem("cookieConsent", allowAnalytics ? "custom-accepted" : "custom-refused");

        gtag('consent', 'update', {
            'analytics_storage': allowAnalytics ? 'granted' : 'denied',
            'ad_storage': 'denied'
        });

        if (allowAnalytics) {
            gtag('config', 'G-SVKMC2KRPX');
        }

        modal.style.display = "none";
        banner.style.display = "none";
    });
});
