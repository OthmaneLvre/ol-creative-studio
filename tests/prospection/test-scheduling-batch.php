<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$timezone = new DateTimeZone('Europe/Paris');

$repository = new ProspectRepository($pdo);
$scheduler = new ProspectScheduler($config);

$approvedProspects =
    $repository->findApprovedProspectsForScheduling();

echo 'Prospects approuvés à programmer : '
    . count($approvedProspects)
    . PHP_EOL;

foreach ($approvedProspects as $prospect) {
    echo sprintf(
        '#%d - %s - approbation : %s%s',
        (int) $prospect['prospect_id'],
        (string) $prospect['company_name'],
        (string) $prospect['approved_at'],
        PHP_EOL
    );
}

echo PHP_EOL;

$results = $repository->scheduleApprovedBatch(
    $scheduler,
    $timezone
);

echo 'Trouvés : '
    . $results['found']
    . PHP_EOL;

echo 'Programmés : '
    . $results['scheduled']
    . PHP_EOL;

echo 'Erreurs : '
    . $results['failed']
    . PHP_EOL;

foreach ($results['items'] as $item) {
    echo PHP_EOL;

    echo '#'
        . $item['prospect_id']
        . ' - '
        . $item['company_name']
        . PHP_EOL;

    echo 'Statut : '
        . $item['status']
        . PHP_EOL;

    if ($item['scheduled_at'] !== null) {
        echo 'Programmé : '
            . $item['scheduled_at']
            . PHP_EOL;
    }

    if ($item['error'] !== null) {
        echo 'Erreur : '
            . $item['error']
            . PHP_EOL;
    }
}

echo PHP_EOL;
echo 'Batch scheduler terminé.' . PHP_EOL;
