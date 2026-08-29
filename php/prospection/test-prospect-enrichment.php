<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__ . '/WebsiteFinder.php';
require_once __DIR__ . '/ProspectRepository.php';

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

$prospectId = 3;

$finder = new WebsiteFinder($apiKey);
$repository = new ProspectRepository($pdo);

try {
    $result = $finder->find(
        'SARL LES PLOMBIERS CERETANS',
        'Céret',
        '66400'
    );

    if ($result === null) {
        echo '[ERREUR] Aucun établissement correspondant.'
            . PHP_EOL;

        exit(1);
    }

    echo 'Établissement trouvé :' . PHP_EOL;
    echo 'Nom : '
        . ($result['name'] ?? '-')
        . PHP_EOL;

    echo 'Site : '
        . ($result['website_url'] ?? '-')
        . PHP_EOL;

    echo 'Téléphone : '
        . ($result['phone'] ?? '-')
        . PHP_EOL;

    $repository->updateEnrichment(
        $prospectId,
        $result['website_url'] ?? null,
        $result['phone'] ?? null,
        $result['google_maps_url'] ?? null,
        $result['place_id'] ?? null,
        $result['address'] ?? null
    );

    echo PHP_EOL;
    echo '[OK] Prospect #'
        . $prospectId
        . ' enrichi avec succès.'
        . PHP_EOL;

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
