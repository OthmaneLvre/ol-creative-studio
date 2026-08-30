<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__ . '/../../php/prospection/EmailFinder.php';
require_once __DIR__ . '/../../php/prospection/ProspectRepository.php';

$prospectId = 3;

$websiteUrl =
    'https://sarllesplombiersceretans.site-solocal.com/';

$finder = new EmailFinder();
$repository = new ProspectRepository($pdo);

try {
    $emails = $finder->find($websiteUrl);

    if ($emails === []) {
        echo '[INFO] Aucun e-mail public dÃ©tectÃ©.'
            . PHP_EOL;

        exit;
    }

    $email = $emails[0];

    echo 'E-mail dÃ©tectÃ© : '
        . $email['email']
        . PHP_EOL;

    echo 'Type : '
        . $email['type']
        . PHP_EOL;

    echo 'Source : '
        . $email['source_url']
        . PHP_EOL;

    $repository->updateEmailDiscovery(
        $prospectId,
        $email['email'],
        $email['type'],
        $email['source_url'],
        100
    );

    echo PHP_EOL;

    echo '[OK] E-mail enregistrÃ© pour le prospect #'
        . $prospectId
        . '.'
        . PHP_EOL;

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
