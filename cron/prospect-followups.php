<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OL Creative Studio
| Prospection - Follow-up scheduler
|--------------------------------------------------------------------------
|
| Responsabilites :
| - detecter les relances arrivees a echeance ;
| - garantir une seule execution concurrente ;
| - respecter le kill switch ;
| - permettre un DRY-RUN sans mutation ;
| - creer les relances de maniere idempotente.
|
| Ce cron ne realise aucun envoi SMTP.
| L'envoi sera gere par prospect-send.php.
|
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(
        'Ce script doit etre execute en ligne de commande.'
    );
}

require_once __DIR__
    . '/../php/db.php';

require_once __DIR__
    . '/../php/prospection/FollowUpManager.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$prospectionConfig = require __DIR__
    . '/../config/prospection.php';

$followUpConfig =
    $prospectionConfig['follow_up']
    ?? [];

$timezone = new DateTimeZone(
    (string) (
        $prospectionConfig['timezone']
        ?? 'Europe/Paris'
    )
);

$now = new DateTimeImmutable(
    'now',
    $timezone
);

$enabled = (bool) (
    $followUpConfig['enabled']
    ?? false
);

$dryRun = (bool) (
    $followUpConfig['dry_run']
    ?? true
);

$batchLimit = (int) (
    $followUpConfig['batch_limit']
    ?? 100
);

if (
    $batchLimit < 1
    || $batchLimit > 500
) {
    throw new RuntimeException(
        'La limite de traitement des relances est invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| En-tete
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'OL Creative Studio - Follow-ups'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'Date : '
    . $now->format('Y-m-d H:i:s')
    . PHP_EOL;

echo 'Relances : '
    . (
        $enabled
            ? 'ENABLED'
            : 'DISABLED'
    )
    . PHP_EOL;

echo 'Mode : '
    . (
        $dryRun
            ? 'DRY-RUN'
            : 'LIVE'
    )
    . PHP_EOL;

echo 'Limite : '
    . $batchLimit
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Kill switch
|--------------------------------------------------------------------------
*/

if (!$enabled) {
    echo 'Relances desactivees.'
        . PHP_EOL;

    echo 'Orchestrateur termine.'
        . PHP_EOL;

    echo PHP_EOL;

    exit(0);
}

/*
|--------------------------------------------------------------------------
| Verrou global
|--------------------------------------------------------------------------
*/

$lockName =
    'ol_prospection_follow_up_scheduler';

$lockAcquired = false;

try {
    $statement = $pdo->prepare(
        'SELECT GET_LOCK(:lock_name, 0)'
    );

    $statement->execute([
        'lock_name' => $lockName,
    ]);

    $lockAcquired =
        (int) $statement->fetchColumn()
        === 1;

    if (!$lockAcquired) {
        echo 'Une autre execution est deja en cours.'
            . PHP_EOL;

        echo PHP_EOL;

        exit(0);
    }

    echo 'Verrou global : ACQUIS'
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Manager
    |--------------------------------------------------------------------------
    */

    $manager =
        new FollowUpManager(
            $pdo,
            $prospectionConfig
        );

    /*
    |--------------------------------------------------------------------------
    | DRY-RUN
    |--------------------------------------------------------------------------
    */

    if ($dryRun) {
        $dueItems =
            $manager->findDueFollowUps(
                $now,
                $batchLimit
            );

        echo '=== RELANCES ELIGIBLES ==='
            . PHP_EOL;

        echo 'Nombre : '
            . count($dueItems)
            . PHP_EOL;

        foreach ($dueItems as $item) {
            echo PHP_EOL;

            echo 'Prospect #'
                . (int) $item['prospect_id']
                . ' - '
                . (string) $item['company_name']
                . PHP_EOL;

            echo 'Type : '
                . (string) $item[
                    'follow_up_type'
                ]
                . PHP_EOL;

            echo 'Destinataire : '
                . (string) $item[
                    'recipient_email'
                ]
                . PHP_EOL;

            echo 'Initial envoye : '
                . (string) $item[
                    'initial_sent_at'
                ]
                . PHP_EOL;

            echo 'Echeance : '
                . (string) $item[
                    'due_at'
                ]
                . PHP_EOL;
        }

        echo PHP_EOL;

        echo 'DRY-RUN : aucune mutation effectuee.'
            . PHP_EOL;

        echo PHP_EOL;

        exit(0);
    }

    /*
    |--------------------------------------------------------------------------
    | LIVE
    |--------------------------------------------------------------------------
    */

    $result =
        $manager->createDueFollowUps(
            $now,
            $batchLimit
        );

    echo '=== RESULTAT ==='
        . PHP_EOL;

    echo 'Eligibles : '
        . (int) $result['found']
        . PHP_EOL;

    echo 'Creees : '
        . (int) $result['created']
        . PHP_EOL;

    echo 'Ignores : '
        . (int) $result['skipped']
        . PHP_EOL;

    foreach ($result['items'] as $item) {
        echo PHP_EOL;

        echo 'Prospect #'
            . (int) $item['prospect_id']
            . ' - '
            . (string) $item[
                'follow_up_type'
            ]
            . ' : ';

        if (
            (bool) (
                $item['created']
                ?? false
            )
        ) {
            echo 'CREATED'
                . PHP_EOL;

            continue;
        }

        echo 'SKIPPED'
            . PHP_EOL;

        if (
            isset($item['error'])
            && trim(
                (string) $item['error']
            ) !== ''
        ) {
            echo 'Erreur : '
                . (string) $item['error']
                . PHP_EOL;
        }
    }

    echo PHP_EOL;

    echo 'Orchestrateur termine.'
        . PHP_EOL;

    echo PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'ERREUR : '
        . $exception->getMessage()
        . PHP_EOL
    );

    exit(1);
} finally {
    if ($lockAcquired) {
        try {
            $statement = $pdo->prepare(
                'SELECT RELEASE_LOCK(:lock_name)'
            );

            $statement->execute([
                'lock_name' => $lockName,
            ]);
        } catch (Throwable) {
            // Le processus se termine de toute facon.
        }
    }
}
