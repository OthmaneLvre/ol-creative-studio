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
    <meta property="og:image" content="https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio_1600.webp">

    <!-- Twitter -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= isset($pageTitle) ? $pageTitle : 'OL Creative Studio' ?>">
    <meta name="twitter:description" content="<?= isset($pageDescription) ? $pageDescription : 'Création de sites web, design et identité visuelle à Céret.' ?>">
    <meta name="twitter:image" content="https://olcreativestudio.fr/assets/logo/logo_olCreativeStudio_1600.webp">

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

    <!-- GLOBAL CSS (minifié et combiné) -->
    <link rel="preload" href="/css/style.css" as="style">
    <link rel="stylesheet" href="/css/style.css">

    <!-- Preload des images (amélioration performance SEO) -->
    <link rel="preload" as="image" href="/assets/images/hero.webp" fetchpriority="high">


    <!-- PAGE-SPECIFIC CSS (AVEC VERSIONING) -->
    <?php
        $page = basename($_SERVER["PHP_SELF"], ".php");
        $file = $_SERVER["DOCUMENT_ROOT"] . "/css/$page.css";

        if (file_exists($file)) {
            echo '<link rel="stylesheet" href="/css/' . $page . '.css?v=' . time() . '">';
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
    <script src="/js/cookies.js" defer></script>

    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){ dataLayer.push(arguments); }
    </script>

</head>

<body>
    
<header class="navbar">

        <!-- LOGO -->
        <div class="logo">
            <a href="/index.php">
                <img src="/assets/logo/logo_olCreativeStudio_1600.webp"
                    alt="OL Creative Studio - Développeur web à Céret"
                    width="150"
                    height="150"
                >
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
