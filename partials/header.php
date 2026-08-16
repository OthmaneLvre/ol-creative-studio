<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="GkyABtAfCI_8wSUZlN3rEMBB67Bm_3_idi40Jrqe2mU" />

    <?php
        // Canonical propre
        $canonical = "https://olcreativestudio.fr";
        $uri = strtok($_SERVER["REQUEST_URI"], "?");

        if ($uri !== "/" && $uri !== "/index.php") {
            $canonical .= $uri;
        }

        // Titre et description par défaut optimisés SEO local
        $defaultTitle = "Développeur Web Freelance à Céret | OL Creative Studio";
        $defaultDescription = "Développeur web freelance à Céret dans les Pyrénées-Orientales. Création de sites vitrines, boutiques en ligne, référencement SEO et identité visuelle pour professionnels et associations.";

        $finalTitle = isset($pageTitle) ? $pageTitle . " | OL Creative Studio" : $defaultTitle;
        $finalDescription = isset($pageDescription) ? $pageDescription : $defaultDescription;
    ?>

    <title><?= htmlspecialchars($finalTitle) ?></title>

    <meta name="description" content="<?= htmlspecialchars($finalDescription) ?>">

    <link rel="canonical" href="<?= htmlspecialchars($canonical) ?>">

    <!-- OpenGraph -->
    <meta property="og:title" content="<?= htmlspecialchars($finalTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($finalDescription) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio_1600.webp">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($finalTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($finalDescription) ?>">
    <meta name="twitter:image" content="https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio_1600.webp">

    <!-- Preload des fonts (amélioration performance SEO) -->
    <link rel="preload" href="/assets/fonts/Manrope-Medium.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Manrope-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Manrope-SemiBold.woff2" as="font" type="font/woff2" crossorigin>

    <link rel="preload" href="/assets/fonts/CormorantGaramond-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/CormorantGaramond-Regular.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- FAVICONS -->
    <link rel="icon" href="/assets/logo/favicon/favicon.ico" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="/assets/logo/favicon/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/assets/logo/favicon/favicon-16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/logo/favicon/apple-touch-icon.png">
    <meta name="theme-color" content="#0D1B2A">

    <!-- GLOBAL CSS (minifié et combiné) -->
    <link rel="preload" href="/assets/css/main.css" as="style">
    <link rel="stylesheet" href="/assets/css/main.css">

    <!-- Preload des images (amélioration performance SEO) -->
    <link rel="preload" as="image" href="/assets/images/hero.webp" fetchpriority="high">


    <!-- PAGE-SPECIFIC CSS (AVEC VERSIONING) -->
    <?php
        $page = basename($_SERVER["PHP_SELF"], ".php");
        
        $pageCssMap = [
            'index' => 'home',
            'services' => 'services',
            'portfolio' => 'portfolio',
            'portfolio-details' => 'portfolio-details',
            'contact' => 'contact',
            'mentions-legales' => 'legal',
            'politique-confidentialite' => 'legal',
            'cgv' => 'legal',
            'cgu' => 'legal',
        ];

        if (isset($pageCssMap[$page])) {
            $cssName = $pageCssMap[$page];
            $cssFile = $_SERVER['DOCUMENT_ROOT'] . "/assets/css/pages/{$cssName}.css";

            if (file_exists($cssFile)) {
                echo '<link rel="stylesheet" href="/assets/css/pages/'
                    . htmlspecialchars($cssName)
                    . '.css?v='
                    . filemtime($cssFile)
                    . '">';
            }
        }
    ?>

    <!-- Schema LocalBusiness (boost SEO local) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "OL Creative Studio",
        "image": "https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio_1600.webp",
        "logo": "https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio_1600.webp",
        "url": "https://olcreativestudio.fr",
        "telephone": "+33767841013",
        "description": "OL Creative Studio vous accompagne pour la création de sites web modernes, designs professionnels, identités visuelles et solutions digitales sur mesure à Céret et dans les Pyrénées-Orientales.",

        "address": {
            "@type": "PostalAddress",
            "streetAddress": "",
            "addressLocality": "Céret",
            "addressRegion": "Pyrénées-Orientales",
            "postalCode": "66400",
            "addressCountry": "FR"
        },

        "areaServed": [
            "Céret",
            "Le Boulou",
            "Amélie-les-bains",
            "Perpignan",
            "Pyrénées-Orientales",
            "Occitanie"
        ],
        
        "openingHours": [
            "Mo-Su 00:00-23:59"
        ],

        "priceRange": "€€",

        "sameAs": [
            "https://github.com/OthmaneLvre",
            "https://linkedin.com/in/olcreativestudio",
            "https://www.upwork.com/freelancers/~012bfcf401f6a63a9c?mp_source=share"
        ]
    }
    </script>

    <!-- cookies.js -->
    <script src="/assets/js/cookies.js" defer></script>

    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
    </script>

</head>

<body id="top">

<?php
$currentPage = basename($_SERVER['PHP_SELF']);

function isActivePage(string $page, string $currentPage): string
{
    return $page === $currentPage
        ? ' is-active'
        : '';
}
?>

<header class="site-header" data-header>

    <div class="container">
        <div class="site-header__inner">

            <!-- BRAND -->
            <a
                href="/index.php"
                class="site-header__brand"
                aria-label="OL Creative Studio - Accueil"
            >
                <!-- LOGO DARK -->
                <img
                    src="/assets/logo/dark/logo-dark@1x.webp"
                    srcset="
                        /assets/logo/dark/logo-dark@1x.webp 65w,
                        /assets/logo/dark/logo-dark@2x.webp 130w,
                    "
                    alt=""
                    class="site-header__logo site-header__logo--dark"
                >

                <!-- LOGO LIGHT -->
                <img
                    src="/assets/logo/light/logo-light@1x.webp"
                    srcset="
                        /assets//logo/light/logo-light@1x.webp 65w,
                        /assets/logo/light/logo-light@2x.webp 130w,
                    "
                    alt=""
                    class="site-header__logo site-header__logo--light"
                >
            </a>

            <!-- NAVIGATION DESKTOP -->
            <nav
                class="site-nav"
                aria-label="Navigation principale"
            >
                <a
                    href="/index.php"
                    class="site-nav__link<?= isActivePage('index.php', $currentPage) ?>"
                >
                    Accueil
                </a>

                <a
                    href="/services.php"
                    class="site-nav__link<?= isActivePage('services.php', $currentPage) ?>"
                >
                    Services
                </a>

                <a
                    href="/portfolio.php"
                    class="site-nav__link<?= isActivePage('portfolio.php', $currentPage) ?>"
                >
                    Portfolio
                </a>

                <a
                    href="/contact.php"
                    class="site-nav__link<?= isActivePage('contact.php', $currentPage) ?>"
                >
                    Contact
                </a>
            </nav>

            <!-- ACTIONS -->
            <div class="site-header__actions">

                <a
                    href="/contact.php"
                    class="button button--primary site-header__cta"
                >
                    <span>Parler de votre projet</span>

                    <span
                        class="button__icon"
                        aria-hidden="true"
                    >
                        ↗
                    </span>
                </a>

                <!-- MOBILE TOGGLE -->
                <button
                    class="menu-toggle"
                    type="button"
                    aria-label="Ouvrir le menu"
                    aria-expanded="false"
                    aria-controls="mobile-navigation"
                    data-menu-toggle
                >
                    <span class="menu-toggle__line"></span>
                    <span class="menu-toggle__line"></span>
                </button>

            </div>

        </div>
    </div>
    

    <!-- MOBILE NAVIGATION -->
    <div
        class="mobile-navigation"
        id="mobile-navigation"
        data-mobile-navigation
        aria-hidden="true"
    >
        <nav
            class="mobile-navigation__nav"
            aria-label="Navigation mobile"
        >
            <a
                href="/index.php"
                class="mobile-navigation__link<?= isActivePage('index.php', $currentPage) ?>"
            >
                <span class="mobile-navigation__index">01</span>
                <span>Accueil</span>
            </a>

            <a
                href="/services.php"
                class="mobile-navigation__link<?= isActivePage('services.php', $currentPage) ?>"
            >
                <span class="mobile-navigation__index">02</span>
                <span>Services</span>
            </a>

            <a
                href="/portfolio.php"
                class="mobile-navigation__link<?= isActivePage('portfolio.php', $currentPage) ?>"
            >
                <span class="mobile-navigation__index">03</span>
                <span>Portfolio</span>
            </a>

            <a
                href="/contact.php"
                class="mobile-navigation__link<?= isActivePage('contact.php', $currentPage) ?>"
            >
                <span class="mobile-navigation__index">04</span>
                <span>Contact</span>
            </a>

        </nav>

        <div class="mobile-navigation__actions">
            <a
                href="/contact.php"
                class="button button--primary button--full"
            >
                Démarrer un projet
            </a>
        </div>

        <div class="mobile-navigation__footer">
            <span>OL Creative Studio</span>
            <span>Céret · France</span>
        </div>

    </div>

</header>