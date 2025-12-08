</main>

<footer class="footer" itemscope itemtype="https://schema.org/LocalBusiness">

    <div class="footer-container">

        <div class="footer-cols">

            <!-- COLONNE 1 -->
            <div class="footer-col">
                <img src="/assets/logo/logo_olCreativeStudio_1600.webp"
                    class="footer-logo"
                    alt="OL Creative Studio - Concepteur web à Céret"
                    width="150"
                    height="150"
                    >
                <p itemprop="description">
                    "Développeur Web & Graphiste Freelance à Céret et dans les Pyrénées-Orientales."
                </p>

                <!-- Réseaux & Plateformes -->
                <div class="footer-socials">
                    <a href="https://github.com/OthmaneLvre" target="_blank" aria-label="GitHub OL Creative Studio">
                        <img src="assets/icons/github.svg" alt="">
                    </a>
                    <a href="https://linkedin.com/in/olcreativestudio" target="_blank" aria-label="LinkedIn OL Creative Studio">
                        <img src="assets/icons/linkedin.svg" alt="">
                    </a>
                    <a href="https://www.upwork.com/freelancers/~012bfcf401f6a63a9c?mp_source=share" target="_blank" aria-label="Profil Upwork OL Creative Studio">
                        <img src="assets/icons/upwork.svg" alt="">
                    </a>
                </div>
            </div>
        
            <!-- COLONNE 2 -->
            <div class="footer-col">
                <h2>Navigation</h2>
                <nav>
                    <a href="index.php">Accueil</a>
                    <a href="services.php">Services</a>
                    <a href="portfolio.php">Portfolio</a>
                    <a href="contact.php">Contact</a>
                </nav>
            </div>
        
            <!-- COLONNE 3 -->
            <div class="footer-col">
                <h2>Contact</h2>

                <address class="footer-contact" itemprop="address" itemscope itemtype="https://schema.org/PostalAddress">
                    
                    <div class="footer-item">
                        <img src="/assets/icons/envelope.svg" alt="">
                        <a href="mailto:contact@olcreativestudio.com" class="footer-mail" itemprop="email">
                            Contact@olcreativestudio.com
                        </a>
                    </div>

                    <div class="footer-item">
                        <img src="/assets/icons/location.svg" alt="">
                        <span itemprop="streetAddress">66 400, Céret, France</span>
                    </div>

                    <div class="footer-item">
                        <img src="/assets/icons/phone.svg" alt="">
                        <a href="tel:+33767841013" itemprop="telephone">07 67 84 10 13</a>
                    </div>

                </address>
            </div>

        </div>

        <div class="footer-bottom">
            <p>© <?= date("Y") ?> OL Creative Studio - Tous droits réservés</p>
            <p>
                <a href="/docs/mentions-legales.php">Mentions légales</a> •
                <a href="/docs/politique-confidentialite.php">Politique de confidentialité</a> •
                <a href="/docs/cgv.php">CGV</a> •
                <a href="/docs/cgu.php">CGU</a>
            </p>

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

<script src="/js/main.js" defer></script>
<script src="/js/portfolio.js" defer></script>

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
