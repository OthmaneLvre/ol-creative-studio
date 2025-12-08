document.addEventListener("DOMContentLoaded", () => {

    const banner = document.getElementById("cookie-banner");
    const modal = document.getElementById("cookie-modal");
    const analyticsCheckbox = document.getElementById("analytics-consent");

    // Vérifier si un choix existe déjà
    const choice = localStorage.getItem("cookieConsent");

    if (!choice) {
        banner.style.display = "flex";
    } else if (choice === "accepted" || choice === "custom-accepted") {
        // Charger GA automatiquement si déjà accepté
        loadGoogleAnalytics();
    }

    // CONSENT MODE PAR DÉFAUT
    if (!choice) {
        gtag('consent', 'default', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied'
        });
    }

    // --- BOUTON ACCEPTER ---
    document.getElementById("cookie-accept").addEventListener("click", () => {
        localStorage.setItem("cookieConsent", "accepted");

        gtag('consent', 'update', {
            'ad_storage': 'granted',
            'analytics_storage': 'granted'
        });

        loadGoogleAnalytics(); // ← CHARGEMENT DE GA ICI

        banner.style.display = "none";
    });

    // --- BOUTON REFUSER ---
    document.getElementById("cookie-refuse").addEventListener("click", () => {
        localStorage.setItem("cookieConsent", "refused");

        gtag('consent', 'update', {
            'ad_storage': 'denied',
            'analytics_storage': 'denied'
        });

        banner.style.display = "none";
    });

    // --- PERSONNALISER ---
    document.getElementById("cookie-customize").addEventListener("click", () => {
        modal.style.display = "flex";
    });

    // --- ANNULER ---
    document.getElementById("cookie-cancel").addEventListener("click", () => {
        modal.style.display = "none";
    });

    // --- ENREGISTRER ---
    document.getElementById("cookie-save").addEventListener("click", () => {
        const allowAnalytics = analyticsCheckbox.checked;

        localStorage.setItem("cookieConsent", allowAnalytics ? "custom-accepted" : "custom-refused");

        gtag('consent', 'update', {
            'analytics_storage': allowAnalytics ? 'granted' : 'denied',
            'ad_storage': 'denied'
        });

        if (allowAnalytics) {
            loadGoogleAnalytics(); // ← Chargement uniquement si accepté
        }

        modal.style.display = "none";
        banner.style.display = "none";
    });
});
