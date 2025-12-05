<?php

function regenerateSitemap() {
    $sitemapURL = "https://olcreativestudio.fr/sitemap.php";
    $sitemapPath = $_SERVER['DOCUMENT_ROOT'] . "/sitemap.xml";

    // Récupère le contenu XML généré
    $xmlContent = file_get_contents($sitemapURL);

    if ($xmlContent) {
        file_put_contents($sitemapPath, $xmlContent);
    }
}
