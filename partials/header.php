<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OL Creative Studio</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700&family=Cormorant+Garamond:wght@400;500;700&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="/OLCreativeStudio/assets/logo/favicon_olCreativeStudio.png">

    <!-- Global DESKTOP CSS -->
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
</head>

<body>
    
<header class="navbar">

        <!-- LOGO -->
        <div class="logo">
            <a href="/OLCreativeStudio/index.php">
                <img src="/OLCreativeStudio/assets/logo/logo_olCreativeStudio.png" alt="OL Creative Studio">
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
