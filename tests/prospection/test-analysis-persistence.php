<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/WebsiteAnalyzer.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$prospectId = 3;

$websiteUrl =
    'https://sarllesplombiersceretans.site-solocal.com/';

$analyzer =
    new WebsiteAnalyzer();

$repository =
    new ProspectRepository($pdo);

try {
    echo 'Analyse du site...'
        . PHP_EOL;

    $analysis =
        $analyzer->analyze(
            $websiteUrl
        );

    if (!$analysis['website_reachable']) {
        echo '[ATTENTION] Site inaccessible.'
            . PHP_EOL;
    }

    echo 'HTTP : '
        . ($analysis['http_status'] ?? '-')
        . PHP_EOL;

    echo 'HTTPS : '
        . ($analysis['has_ssl']
            ? 'Oui'
            : 'Non')
        . PHP_EOL;

    echo 'Mobile : '
        . ($analysis['is_mobile_friendly']
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

    echo PHP_EOL;

    $analysisId =
        $repository->saveWebsiteAnalysis(
            $prospectId,
            $analysis
        );

    echo '[OK] Analyse #'
        . $analysisId
        . ' enregistrÃ©e pour le prospect #'
        . $prospectId
        . '.'
        . PHP_EOL;

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
