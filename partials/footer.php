</main>

<footer class="site-footer" itemscope itemtype="https://schema.org/LocalBusiness">

    <!-- CTA FOOTER -->
    <div class="container">

        <div class="site-footer__cta">

            <div class="site-footer__cta-content">

                <span class="site-footer__eyebrow">
                    Un projet en tête ?
                </span>

                <h2 class="site-footer__title">
                    Faisons quelque chose
                    <em>qui marque.</em>
                </h2>

            </div>

            <a
                href="/contact.php"
                class="button button--primary button--lg site-footer__cta-button"
            >
                <span>Parler de votre projet</span>

                <span
                    class="button__icon"
                    aria-hidden="true"
                >
                    ↗
                </span>
            </a>

        </div>

    </div>

            <!-- MAIN FOOTER -->
            <div class="site-footer__main">

                <div class="container">

                    <div class="site-footer__grid">

                        <!-- BRAND -->
                        <div class="site-footer__brand">

                            <a
                                href="/index.php"
                                class="site-footer__logo-link"
                                aria-label="OL Creative Studio - Accueil"
                            >

                                <img
                                    src="/assets/logo/light/logo-light@1x.webp"
                                    srcset="
                                        /assets/logo/light/logo-light@1x.webp 1x,
                                        /assets/logo/light/logo-light@2x.webp 2x
                                    "
                                    alt=""
                                    class="site-footer__logo"
                                >
                            </a>

                            <p class="site-footer__description">
                                Développement web, identité visuelle et solutions
                                digitales sur mesure pour les entreprises,
                                indépendants et associations.
                            </p>

                            <p class="site-footer__location">
                                Céret · Pyrénées-Orientales · France
                            </p>
                        </div>

                        <!-- NAVIGATION -->
                        <div class="site-footer__column">

                            <span class="site-footer__label">
                                Navigation
                            </span>

                            <nav
                                class="site-footer__nav"
                                aria-label="Navigation du pied de page"
                            >
                                <a href="/index.php">
                                    Accueil
                                </a>

                                <a href="/services.php">
                                    Services
                                </a>

                                <a href="/portfolio.php">
                                    Portfolio
                                </a>

                                <a href="/contact.php">
                                    Contact
                                </a>
                            </nav>

                        </div>

                        <!-- CONTACT -->
                        <div class="site-footer__column">

                            <span class="site-footer__label">
                                Contact
                            </span>

                            <div class="site-footer__links">

                                <a href="mailto:contact@olcreativestudio.com">
                                    contact@olcreativestudio.com
                                </a>

                                <a href="tel:+33767841013">
                                    07 67 84 10 13
                                </a>

                            </div>

                        </div>

                        <!-- SOCIAL -->
                        <div class="site-footer__column">

                            <span class="site-footer__label">
                                Réseaux
                            </span>

                            <div class="site-footer__links">

                                <a
                                    href="https://github.com/OthmaneLvre"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span>GitHub</span>
                                    <span aria-hidden="true">↗</span>
                                </a>

                                <a
                                    href="https://linkedin.com/in/olcreativestudio"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span>LinkedIn</span>
                                    <span aria-hidden="true">↗</span>
                                </a>

                                <a
                                    href="https://www.instagram.com/olcreativestudio?utm_source=qr"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span>Instagram</span>
                                    <span aria-hidden="true">↗</span>
                                </a>

                                <a
                                    href="https://www.upwork.com/freelancers/~012bfcf401f6a63a9c?mp_source=share"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    <span>Upwork</span>
                                    <span aria-hidden="true">↗</span>
                                </a>

                            </div>

                        </div>

                    </div>

                    <!-- BOTTOM -->
                    <div class="site-footer__bottom">

                        <p>
                            © <?= date('Y') ?> OL Creative Studio
                        </p>

                        <div class="site-footer__legal">

                            <a href="/docs/mentions-legales.php">
                                Mentions légales
                            </a>

                            <a href="/docs/politique-confidentialite.php">
                                Confidentialité
                            </a>

                            <a href="/docs/cgv.php">
                                CGV
                            </a>

                            <a href="/docs/cgu.php">
                                CGU
                            </a>

                        </div>

                        <a
                            href="#top"
                            class="site-footer__back-top"
                            aria-label="Retour en haut de la page"
                        >
                            <span>Haut de page</span>
                            <span aria-hidden="true">↑</span>
                        </a>

                    </div>

                </div>

            </div>

</footer>

<div id="cookie-banner" class="cookie-banner">
    <p>
        Ce site utilise des cookies pour améliorer votre expérience et mesurer l’audience.
        Vous pouvez accepter, refuser ou personnaliser vos choix.
    </p>

    <div class="cookie-buttons">
        <button id="cookie-accept">Accepter</button>
        <button id="cookie-customize">Personnaliser</button>
        <button id="cookie-refuse">Refuser</button>
    </div>
</div>

<!-- Fenêtre de personnalisation -->
<div id="cookie-modal" class="cookie-modal">
    <div class="cookie-modal-content">
        <h3>Préférences de cookies</h3>

        <label class="cookie-option">
            <input type="checkbox" id="analytics-consent">
            Autoriser les cookies de mesure d’audience (Google Analytics)
        </label>

        <div class="cookie-modal-buttons">
            <button id="cookie-save">Enregistrer</button>
            <button id="cookie-cancel">Annuler</button>
        </div>
    </div>
</div>

<script
    type="module"
    src="/assets/js/main.js"
></script>

<script>
function loadGoogleAnalytics() {
    // Charger dynamiquement le script GA
    const s = document.createElement("script");
    s.src = "https://www.googletagmanager.com/gtag/js?id=G-SVKMC2KRPX";
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);

    // Activer GA
    window.dataLayer = window.dataLayer || [];
    function gtag(){ dataLayer.push(arguments); }
    
    gtag('js', new Date());
    gtag('config', 'G-SVKMC2KRPX');
}
</script>

</body>
</html>
