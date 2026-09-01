<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$repository =
    new ProspectRepository($pdo);

/*
|--------------------------------------------------------------------------
| Test compteur
|--------------------------------------------------------------------------
*/

$now = new DateTimeImmutable(
    'now',
    new DateTimeZone('Europe/Paris')
);

$count =
    $repository->countSentInitialEmailsForDate(
        $now
    );

if ($count < 0) {
    throw new RuntimeException(
        'Compteur quotidien invalide.'
    );
}

echo 'Compteur envois réels : OK'
    . PHP_EOL;

echo 'Envoyés aujourd’hui : '
    . $count
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| Test verrou global
|--------------------------------------------------------------------------
*/

$acquired =
    $repository->acquireMailerLock();

if (!$acquired) {
    throw new RuntimeException(
        'Impossible d’obtenir le verrou mailer.'
    );
}

echo 'Verrou mailer acquis : OK'
    . PHP_EOL;

$repository->releaseMailerLock();

echo 'Verrou mailer libéré : OK'
    . PHP_EOL;

echo PHP_EOL;

echo 'Sécurité orchestrateur SMTP : OK'
    . PHP_EOL;
    