<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
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

    <!-- Canonical -->
    <link rel="canonical" href="https://olcreativestudio.fr<?php echo $_SERVER['REQUEST_URI']; ?>">

    <!-- Preload des fonts (amélioration performance SEO) -->
    <link rel="preload" href="/OLCreativeStudio/assets/fonts/Manrope-Medium.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/OLCreativeStudio/assets/fonts/Manrope-Regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/OLCreativeStudio/assets/fonts/Manrope-SemiBold.woff2" as="font" type="font/woff2" crossorigin>

    <link rel="preload" href="/OLCreativeStudio/assets/fonts/CormorantGaramond-Bold.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/OLCreativeStudio/assets/fonts/CormorantGaramond-Regular.woff2" as="font" type="font/woff2" crossorigin>

    <!-- Google fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/OLCreativeStudio/assets/logo/favicon_olCreativeStudio.png">

    <!-- Global DESKTOP CSS -->
    <link rel="stylesheet" href="/OLCreativeStudio/css/fonts.css">
    <link rel="stylesheet" href="/OLCreativeStudio/css/style.css">
    <link rel="stylesheet" href="/OLCreativeStudio/css/style-responsive.css">
    
    <!-- Page-Specific CSS (chargé uniquement si présent) -->
    <?php
        // Charger automatiquement le CSS spécifique si un fichier existe
        $page = basename($_SERVER['PHP_SELF'], ".php");

        // Chemin serveur (pour file_exists)
        $serverPath = $_SERVER['DOCUMENT_ROOT'] . "/OLCreativeStudio/css/";

        // Chemin URL (pour <link>)
        $urlPath = "/OLCreativeStudio/css/";

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
      "image": "https://olcreativestudio.fr/OLCreativeStudio/assets/logo/logo_olCreativeStudio.png",
      "address": {
        "@type": "PostalAddress",
        "addressLocality": "Céret",
        "addressRegion": "Pyrénées-Orientales",
        "addressCountry": "FR"
      },
      "areaServed": "Pyrénées-Orientales",
      "url": "https://olcreativestudio.fr",
      "telephone": "+33767841013"
    }
    </script>
</head>

<body>
    
<header class="navbar">

        <!-- LOGO -->
        <div class="logo">
            <a href="/OLCreativeStudio/index.php">
                <img src="/OLCreativeStudio/assets/logo/logo_olCreativeStudio.png"
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
            <a href="/OLCreativeStudio/index.php">Accueil</a>
            <a href="/OLCreativeStudio/services.php">Services</a>
            <a href="/OLCreativeStudio/portfolio.php">Portfolio</a>
            <a href="/OLCreativeStudio/contact.php">Contact</a>
        </div>


</header>


<main>
