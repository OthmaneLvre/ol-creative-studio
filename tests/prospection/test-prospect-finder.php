<?php

declare(strict_types=1);

require_once __DIR__ . '/../../php/prospection/ProspectFinder.php';

$finder = new ProspectFinder();

try {
    $companies = $finder->search(
        'plombier',
        'CÃ©ret',
        '66400',
        10
    );

    echo 'RÃ©sultats : ' . count($companies) . PHP_EOL;
    echo str_repeat('-', 50) . PHP_EOL;

    foreach ($companies as $company) {
        echo 'Entreprise : ' . $company['company_name'] . PHP_EOL;
        echo 'ActivitÃ© : ' . ($company['activity'] ?? '-') . PHP_EOL;
        echo 'Commune : ' . ($company['city'] ?? '-') . PHP_EOL;
        echo 'CP : ' . ($company['postal_code'] ?? '-') . PHP_EOL;
        echo 'SIREN : ' . ($company['siren'] ?? '-') . PHP_EOL;
        echo 'SIRET : ' . ($company['siret'] ?? '-') . PHP_EOL;
        echo str_repeat('-', 50) . PHP_EOL;
    }
} catch (Throwable $exception) {
    echo 'ERREUR : ' . $exception->getMessage() . PHP_EOL;
}
