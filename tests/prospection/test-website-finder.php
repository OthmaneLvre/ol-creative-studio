<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__ . '/../../php/prospection/WebsiteFinder.php';

$apiKey =
    $_ENV['GOOGLE_PLACES_API_KEY']
    ?? null;

if (
    !is_string($apiKey)
    || trim($apiKey) === ''
) {
    echo '[ERREUR] GOOGLE_PLACES_API_KEY absente.'
        . PHP_EOL;

    exit(1);
}

$finder = new WebsiteFinder($apiKey);

try {
    $result = $finder->find(
        'SARL LES PLOMBIERS CERETANS',
        'CÃ©ret',
        '66400'
    );

    if ($result === null) {
        echo 'Aucun Ã©tablissement correspondant.'
            . PHP_EOL;

        exit;
    }

    echo 'Ã‰tablissement trouvÃ© :' . PHP_EOL;
    echo 'Nom : '
        . ($result['name'] ?? '-')
        . PHP_EOL;

    echo 'Adresse : '
        . ($result['address'] ?? '-')
        . PHP_EOL;

    echo 'Site : '
        . ($result['website_url'] ?? '-')
        . PHP_EOL;

    echo 'TÃ©lÃ©phone : '
        . ($result['phone'] ?? '-')
        . PHP_EOL;

    echo 'Place ID : '
        . ($result['place_id'] ?? '-')
        . PHP_EOL;
} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;
}
