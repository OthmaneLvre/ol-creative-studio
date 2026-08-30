<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/WebsiteAnalyzer.php';

$analyzer =
    new WebsiteAnalyzer();

try {
    $analysis =
        $analyzer->analyze(
            'https://sarllesplombiersceretans.site-solocal.com/'
        );

    echo '=== ANALYSE DU SITE ==='
        . PHP_EOL
        . PHP_EOL;

    echo 'Accessible : '
        . ($analysis['website_reachable']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'HTTP : '
        . ($analysis['http_status'] ?? '-')
        . PHP_EOL;

    echo 'HTTPS : '
        . ($analysis['has_ssl']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'Temps rÃ©ponse : '
        . ($analysis['response_time_ms'] ?? '-')
        . ' ms'
        . PHP_EOL;

    echo 'Poids HTML : '
        . ($analysis['html_size_bytes'] ?? '-')
        . ' octets'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Title : '
        . ($analysis['title'] ?? '-')
        . PHP_EOL;

    echo 'Meta description : '
        . ($analysis['has_meta_description']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'H1 : '
        . ($analysis['h1_count'] ?? 0)
        . PHP_EOL;

    echo 'Viewport : '
        . ($analysis['has_viewport']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'Canonical : '
        . ($analysis['has_canonical']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'Open Graph : '
        . ($analysis['has_open_graph']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'JSON-LD : '
        . ($analysis['has_json_ld']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Page contact : '
        . ($analysis['has_contact_page']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'Formulaire : '
        . ($analysis['has_contact_form']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'E-mail visible : '
        . ($analysis['has_visible_email']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'TÃ©lÃ©phone visible : '
        . ($analysis['has_visible_phone']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'RÃ©seaux sociaux : '
        . ($analysis['has_social_links']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo PHP_EOL;

    echo 'robots.txt : '
        . ($analysis['robots_txt']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'sitemap.xml : '
        . ($analysis['sitemap_xml']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Technologies : '
        . (
            $analysis['technologies'] !== []
                ? implode(
                    ', ',
                    $analysis['technologies']
                )
                : 'Aucune dÃ©tectÃ©e'
        )
        . PHP_EOL;

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
