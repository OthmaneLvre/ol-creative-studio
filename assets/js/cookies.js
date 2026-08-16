document.addEventListener("DOMContentLoaded", () => {

    /* =========================================
       ELEMENTS
       ========================================= */

    const banner = document.getElementById("cookie-banner");
    const modal = document.getElementById("cookie-modal");

    const acceptButton = document.getElementById("cookie-accept");
    const refuseButton = document.getElementById("cookie-refuse");
    const customizeButton = document.getElementById("cookie-customize");
    const cancelButton = document.getElementById("cookie-cancel");
    const saveButton = document.getElementById("cookie-save");

    const analyticsCheckbox =
        document.getElementById("analytics-consent");


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
       CONSENT STATE
       ========================================= */

    const choice = localStorage.getItem("cookieConsent");


    /* =========================================
       INITIAL STATE
       ========================================= */

    if (!choice) {

        banner.style.display = "flex";

        if (typeof gtag === "function") {

            gtag("consent", "default", {
                ad_storage: "denied",
                analytics_storage: "denied"
            });

        }

    } else if (
        choice === "accepted" ||
        choice === "custom-accepted"
    ) {

        if (typeof loadGoogleAnalytics === "function") {
            loadGoogleAnalytics();
        }

    }


    /* =========================================
       ACCEPT
       ========================================= */

    acceptButton.addEventListener("click", () => {

        localStorage.setItem(
            "cookieConsent",
            "accepted"
        );

        if (typeof gtag === "function") {

            gtag("consent", "update", {
                ad_storage: "granted",
                analytics_storage: "granted"
            });

        }

        if (typeof loadGoogleAnalytics === "function") {
            loadGoogleAnalytics();
        }

        banner.style.display = "none";

    });


    /* =========================================
       REFUSE
       ========================================= */

    refuseButton.addEventListener("click", () => {

        localStorage.setItem(
            "cookieConsent",
            "refused"
        );

        if (typeof gtag === "function") {

            gtag("consent", "update", {
                ad_storage: "denied",
                analytics_storage: "denied"
            });

        }

        banner.style.display = "none";

    });


    /* =========================================
       CUSTOMIZE
       ========================================= */

    customizeButton.addEventListener("click", () => {

        modal.style.display = "flex";

    });


    /* =========================================
       CANCEL
       ========================================= */

    cancelButton.addEventListener("click", () => {

        modal.style.display = "none";

    });


    /* =========================================
       SAVE CUSTOM CHOICE
       ========================================= */

    saveButton.addEventListener("click", () => {

        const allowAnalytics =
            analyticsCheckbox.checked;

        const consentValue = allowAnalytics
            ? "custom-accepted"
            : "custom-refused";

        localStorage.setItem(
            "cookieConsent",
            consentValue
        );

        if (typeof gtag === "function") {

            gtag("consent", "update", {
                analytics_storage:
                    allowAnalytics
                        ? "granted"
                        : "denied",

                ad_storage: "denied"
            });

        }

        if (
            allowAnalytics &&
            typeof loadGoogleAnalytics === "function"
        ) {
            loadGoogleAnalytics();
        }

        modal.style.display = "none";
        banner.style.display = "none";

    });

});