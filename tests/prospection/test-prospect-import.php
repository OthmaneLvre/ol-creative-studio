<?php

declare(strict_types=1);

require_once __DIR__ . '/../../admin/prospection/includes/bootstrap.php';
require_once __DIR__ . '/../../php/prospection/ProspectFinder.php';
require_once __DIR__ . '/../../php/prospection/ProspectRepository.php';

$finder = new ProspectFinder();
$repository = new ProspectRepository($pdo);

try {
    $companies = $finder->search(
        'plombier',
        'CÃ©ret',
        '66400',
        10
    );

    echo 'Entreprises trouvÃ©es : '
        . count($companies)
        . PHP_EOL;

    foreach ($companies as $company) {
        $id = $repository->createFromDiscovery($company);

        if ($id === null) {
            echo '[DOUBLON] '
                . $company['company_name']
                . PHP_EOL;

            continue;
        }

        echo '[AJOUTÃ‰] #'
            . $id
            . ' '
            . $company['company_name']
            . PHP_EOL;
    }
} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;
}
