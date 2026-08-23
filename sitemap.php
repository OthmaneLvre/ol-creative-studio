<?php

declare(strict_types=1);

header('Content-Type: application/xml; charset=UTF-8');

require_once __DIR__ . '/php/db.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$baseUrl = 'https://olcreativestudio.fr';


/*
|--------------------------------------------------------------------------
| Pages statiques
|--------------------------------------------------------------------------
*/

$staticPages = [
    [
        'path' => '/',
        'changefreq' => 'weekly',
        'priority' => '1.0',
    ],
    [
        'path' => '/services.php',
        'changefreq' => 'monthly',
        'priority' => '0.9',
    ],
    [
        'path' => '/portfolio.php',
        'changefreq' => 'monthly',
        'priority' => '0.9',
    ],
    [
        'path' => '/contact.php',
        'changefreq' => 'monthly',
        'priority' => '0.7',
    ],
    [
        'path' => '/docs/mentions-legales.php',
        'changefreq' => 'yearly',
        'priority' => '0.2',
    ],
    [
        'path' => '/docs/politique-confidentialite.php',
        'changefreq' => 'yearly',
        'priority' => '0.2',
    ],
];


/*
|--------------------------------------------------------------------------
| Projets publiés
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        slug,
        date_creation
    FROM portfolio
    WHERE statut = 'published'
    ORDER BY ordre ASC, id DESC
";

$stmt = $pdo->query($sql);

$projects = $stmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| Helper XML
|--------------------------------------------------------------------------
*/

function escapeXml(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| XML
|--------------------------------------------------------------------------
*/

echo '<?xml version="1.0" encoding="UTF-8"?>';

?>

<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">

<?php foreach ($staticPages as $page): ?>

    <url>
        <loc><?= escapeXml($baseUrl . $page['path']) ?></loc>
        <changefreq><?= escapeXml($page['changefreq']) ?></changefreq>
        <priority><?= escapeXml($page['priority']) ?></priority>
    </url>

<?php endforeach; ?>


<?php foreach ($projects as $project): ?>

    <?php

    $slug = trim(
        (string) ($project['slug'] ?? '')
    );

    $projectUrl = $slug !== ''
        ? $baseUrl
            . '/portfolio-details.php?slug='
            . rawurlencode($slug)
        : $baseUrl
            . '/portfolio-details.php?id='
            . (int) $project['id'];

    $lastModified = date(
        'Y-m-d',
        strtotime($project['date_creation'])
    );

    ?>

    <url>
        <loc><?= escapeXml($projectUrl) ?></loc>
        <lastmod><?= escapeXml($lastModified) ?></lastmod>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>

<?php endforeach; ?>

</urlset>
