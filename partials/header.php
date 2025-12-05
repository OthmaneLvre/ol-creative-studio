<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="google-site-verification" content="GkyABtAfCI_8wSUZlN3rEMBB67Bm_3_idi40Jrqe2mU" />
    
    <!-- Titre dynamique -->
    <title>
        <?php
            echo isset($pageTitle) ? $pageTitle . " | OL Creative Studio" : "OL Creative Studio – Création de sites web à Céret";
        ?>
    </title>

    <!-- Meta Description dynamique -->
    <meta name="description" content="<?php
        echo isset($pageDescription)
        ? $pageDescription
        : 'Création de sites web vitrines, e-commerce et identités visuelles à Céret et dans les Pyrénées-Orientales. Freelance réactif, moderne et professionnel.';
    ?>">

    <!-- OpenGraph (SEO réseaux sociaux) -->
    <meta property="og:title" content="<?= isset($pageTitle) ? $pageTitle : 'OL Creative Studio' ?>">
    <meta property="og:description" content="<?= isset($pageDescription) ? $pageDescription : 'Création de sites web, design et identité visuelle à Céret.' ?>">
    <meta property="og:url" content="<?= $canonical ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio.png">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($pageTitle) ? $pageTitle : 'OL Creative Studio' ?>">
    <meta name="twitter:description" content="<?= isset($pageDescription) ? $pageDescription : 'Création de sites web, design et identité visuelle à Céret.' ?>">
    <meta name="twitter:image" content="https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio.png">

    <!-- Canonical -->
    <?php
    $canonical = "https://olcreativestudio.fr";
    $uri = $_SERVER["REQUEST_URI"];

    // Si la page demandée est /index.php → canonical = /
    if ($uri !== "/index.php") {
        $canonical .= $uri;
    }
    ?>
    <link rel="canonical" href="<?= $canonical ?>">


    <!-- Preload des fonts (amélioration performance SEO) -->
    <link rel="preload" href="/assets/fonts/Manrope-Medium.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Manrope-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Manrope-SemiBold.woff2" as="font" type="font/woff2" crossorigin>

    <link rel="preload" href="/assets/fonts/CormorantGaramond-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/CormorantGaramond-Regular.woff2" as="font" type="font/woff2" crossorigin>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">

    <!-- Global DESKTOP CSS -->
    <link rel="stylesheet" href="/css/fonts.css">
    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/style-responsive.css">
    
    <!-- Page-Specific CSS (chargé uniquement si présent) -->
    <?php
        // Charger automatiquement le CSS spécifique si un fichier existe
        $page = basename($_SERVER['PHP_SELF'], ".php");

        // Chemin serveur (pour file_exists)
        $serverPath = $_SERVER['DOCUMENT_ROOT'] . "/css/";

        // Chemin URL (pour <link>)
        $urlPath = "/css/";

        //CSS principal
        if (file_exists($serverPath . "$page.css")) {
            echo '<link rel="stylesheet" href="' . $urlPath . $page . '.css">';
        }

        //CSS responsive
        if (file_exists($serverPath . "{$page}-responsive.css")) {
            echo '<link rel="stylesheet" href="' . $urlPath . $page . '-responsive.css">';
        }
    ?>

    <!-- Schema LocalBusiness (boost SEO local) -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "LocalBusiness",
        "name": "OL Creative Studio",
        "image": "https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio.png",
        "logo": "https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio.png",
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

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-SVKMC2KRPX"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-SVKMC2KRPX');
    </script>

    <script>
    // Consent default : tout refusé tant que l'utilisateur n'accepte pas
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}

    gtag('consent', 'default', {
        'ad_storage': 'denied',
        'analytics_storage': 'denied'
    });
    </script>

    <script async src="https://www.googletagmanager.com/gtag/js?id=G-SVKMC2KRPX"></script>
    <script>
    gtag('js', new Date());
    </script>

</head>

<body>
    
<header class="navbar">

        <!-- LOGO -->
        <div class="logo">
            <a href="/index.php">
                <img src="/assets/logo/logo_olCreativeStudio.png"
                    alt="OL Creative Studio - Développeur web à Céret">
            </a>
        </div>

        <!-- MENU CENTRÉ (desktop) -->
        <nav class="nav-links">
            <a href="index.php">Accueil</a>
            <span class="sep"></span>
            <a href="services.php">Services</a>
            <span class="sep"></span>
            <a href="portfolio.php">Portfolio</a>
            <span class="sep"></span>
            <a href="contact.php">Contact</a>
        </nav>

        <!-- BOUTON A DROITE (desktop only) -->
        <a href="contact.php" class="btn-primary btn-nav">Me contacter</a>

        <!-- MENU BURGER (mobile only) -->
        <div class="burger" id="burger">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <!-- MENU MOBILE -->
        <div class="mobile-menu" id="mobileMenu">
            <a href="/index.php">Accueil</a>
            <a href="/services.php">Services</a>
            <a href="/portfolio.php">Portfolio</a>
            <a href="/contact.php">Contact</a>
        </div>


</header>


<main>
