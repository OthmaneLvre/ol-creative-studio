<?php
header("Content-Type: application/xml; charset=utf-8");

require_once "php/db.php";

$baseUrl = "https://olcreativestudio.fr/";

// Pages statiques (sans index.php pour éviter le duplicate)
$staticPages = [
    "",
    "services.php",
    "portfolio.php",
    "contact.php"
];

$sql = "SELECT id, date_creation FROM portfolio ORDER BY id DESC";
$stmt = $pdo->query($sql);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

    <!-- Pages statiques -->
    <?php foreach ($staticPages as $page): ?>
    <url>
        <loc><?= rtrim($baseUrl . $page, "/") ?></loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>

    <!-- Pages portfolio -->
    <?php foreach ($projects as $p): ?>
    <url>
        <loc><?= $baseUrl . "portfolio-details.php?id=" . $p['id'] ?></loc>
        <lastmod><?= $p['date_creation'] ? date("Y-m-d", strtotime($p['date_creation'])) : date("Y-m-d") ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.6</priority>
    </url>
    <?php endforeach; ?>

</urlset>
