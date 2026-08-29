<?php

declare(strict_types=1);

require_once __DIR__ . '/EmailFinder.php';

$finder = new EmailFinder();

try {
    $emails = $finder->find(
        'https://sarllesplombiersceretans.site-solocal.com/'
    );

    echo 'E-mails trouvés : '
        . count($emails)
        . PHP_EOL;

    echo str_repeat('-', 60)
        . PHP_EOL;

    foreach ($emails as $email) {
        echo 'E-mail : '
            . $email['email']
            . PHP_EOL;

        echo 'Type : '
            . $email['type']
            . PHP_EOL;

        echo 'Source : '
            . $email['source_url']
            . PHP_EOL;

        echo str_repeat('-', 60)
            . PHP_EOL;
    }

    if ($emails === []) {
        echo 'Aucun e-mail public détecté.'
            . PHP_EOL;
    }
} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
