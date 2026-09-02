<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OL Creative Studio
| Prospection - Surveillance de la boîte de réception
|--------------------------------------------------------------------------
|
| Responsabilités :
| - empêcher deux traitements concurrents de la boîte ;
| - récupérer un nombre limité de messages récents ;
| - rechercher une correspondance avec les e-mails de prospection ;
| - ignorer les messages sans correspondance certaine ;
| - persister les réponses reconnues ;
| - isoler les erreurs afin qu'un message invalide ne bloque pas le batch.
|
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);

    exit(
        'Ce script doit être exécuté en ligne de commande.'
    );
}

require_once __DIR__
    . '/../php/db.php';

require_once __DIR__
    . '/../php/prospection/InboxMonitor.php';

require_once __DIR__
    . '/../php/prospection/IncomingReplyMatcher.php';

require_once __DIR__
    . '/../php/prospection/IncomingReplyProcessor.php';

require_once __DIR__
    . '/../php/prospection/IncomingReplyHandler.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$prospectionConfig = require __DIR__
    . '/../config/prospection.php';

$inboxConfig = require __DIR__
    . '/../config/inbox.php';

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

$inboxEnabled = (bool) (
    $inboxConfig['enabled']
    ?? false
);

$batchLimit = (int) (
    $inboxConfig['batch_limit']
    ?? 50
);

$dryRun = (bool) (
    $inboxConfig['dry_run']
    ?? true
);

if ($batchLimit < 1 || $batchLimit > 500) {
    throw new RuntimeException(
        'La limite de traitement IMAP est invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

$monitor = new InboxMonitor(
    $inboxConfig
);

$matcher = new IncomingReplyMatcher(
    $pdo
);

$processor = new IncomingReplyProcessor(
    $pdo
);

$handler = new IncomingReplyHandler(
    $matcher,
    $processor
);

/*
|--------------------------------------------------------------------------
| En-tête
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'OL Creative Studio - Prospection Inbox'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'Date : '
    . $now->format('Y-m-d H:i:s')
    . PHP_EOL;

echo 'Surveillance inbox : '
    . (
        $inboxEnabled
            ? 'ENABLED'
            : 'DISABLED'
    )
    . PHP_EOL;

echo 'Limite par exécution : '
    . $batchLimit
    . PHP_EOL;

echo 'Mode : '
    . (
        $dryRun
            ? 'DRY-RUN'
            : 'LIVE'
    )
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Kill switch
|--------------------------------------------------------------------------
*/

if (!$inboxEnabled) {
    echo 'Surveillance de la boîte désactivée.'
        . PHP_EOL;

    echo 'Orchestrateur terminé.'
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
    'ol_prospection_inbox_monitor';

$lockAcquired = false;

try {
    $statement = $pdo->prepare(
        'SELECT GET_LOCK(:lock_name, 0)'
    );

    $statement->execute([
        'lock_name' => $lockName,
    ]);

    $lockAcquired =
        (int) $statement->fetchColumn() === 1;

    if (!$lockAcquired) {
        echo 'Orchestrateur inbox déjà en cours d’exécution.'
            . PHP_EOL;

        exit(0);
    }

    echo 'Verrou inbox : acquis'
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Lecture IMAP
    |--------------------------------------------------------------------------
    */

    try {
        $messages =
            $monitor->fetchRecentMessages(
                $batchLimit
            );
    } catch (Throwable $exception) {
        echo 'Lecture IMAP : ERREUR'
            . PHP_EOL;

        echo $exception->getMessage()
            . PHP_EOL;

        echo PHP_EOL;

        throw $exception;
    }

    echo 'Messages récupérés : '
        . count($messages)
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Compteurs
    |--------------------------------------------------------------------------
    */

    $matchedCount = 0;
    $createdCount = 0;
    $duplicateCount = 0;
    $ignoredCount = 0;
    $errorCount = 0;

    /*
    |--------------------------------------------------------------------------
    | Traitement
    |--------------------------------------------------------------------------
    */

    foreach ($messages as $message) {
        $uid = isset($message['uid'])
            ? (int) $message['uid']
            : null;

        echo '----------------------------------------'
            . PHP_EOL;

        echo 'UID : '
            . (
                $uid !== null
                    ? (string) $uid
                    : 'inconnu'
            )
            . PHP_EOL;

        echo 'Expéditeur : '
            . (
                $message['sender_email']
                ?? 'inconnu'
            )
            . PHP_EOL;

        echo 'Sujet : '
            . (
                $message['subject']
                ?? '(sans sujet)'
            )
            . PHP_EOL;

        try {
            if ($dryRun) {
                $match = $matcher->match(
                    isset($message['in_reply_to'])
                        && is_string($message['in_reply_to'])
                            ? $message['in_reply_to']
                            : null,
                    isset($message['references'])
                        && is_array($message['references'])
                            ? $message['references']
                            : [],
                    isset($message['sender_email'])
                        && is_string($message['sender_email'])
                            ? $message['sender_email']
                            : null
                );

                if ($match === null) {
                    $ignoredCount++;

                    echo 'Résultat : IGNORED'
                        . PHP_EOL;

                    echo 'Raison : no_match'
                        . PHP_EOL;

                    echo PHP_EOL;

                    continue;
                }

                $matchedCount++;

                echo 'Résultat : DRY_RUN_MATCH'
                    . PHP_EOL;

                echo 'Match : '
                    . (
                        $match['match_type']
                        ?? 'inconnu'
                    )
                    . PHP_EOL;

                echo 'Prospect ID : '
                    . (
                        isset($match['prospect_id'])
                            ? (string) $match['prospect_id']
                            : 'inconnu'
                    )
                    . PHP_EOL;

                echo 'Email ID : '
                    . (
                        isset($match['email_id'])
                            ? (string) $match['email_id']
                            : 'inconnu'
                    )
                    . PHP_EOL;

                echo PHP_EOL;

                continue;
            }

            $result =
                $handler->handle(
                    $message
                );
        } catch (Throwable $exception) {
            $errorCount++;

            echo 'Résultat : ERROR'
                . PHP_EOL;

            echo 'Erreur : '
                . $exception->getMessage()
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        if (
            ($result['matched'] ?? false)
            !== true
        ) {
            $ignoredCount++;

            echo 'Résultat : IGNORED'
                . PHP_EOL;

            echo 'Raison : '
                . (
                    $result['reason']
                    ?? 'no_match'
                )
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        $matchedCount++;

        echo 'Match : '
            . (
                $result['match_type']
                ?? 'inconnu'
            )
            . PHP_EOL;

        echo 'Prospect ID : '
            . (
                isset($result['prospect_id'])
                    ? (string) $result['prospect_id']
                    : 'inconnu'
            )
            . PHP_EOL;

        if (
            ($result['duplicate'] ?? false)
            === true
        ) {
            $duplicateCount++;

            echo 'Résultat : DUPLICATE'
                . PHP_EOL;

            echo 'Reply ID : '
                . (
                    isset($result['reply_id'])
                        ? (string) $result['reply_id']
                        : 'inconnu'
                )
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        if (
            ($result['created'] ?? false)
            === true
        ) {
            $createdCount++;

            echo 'Résultat : REPLY_CREATED'
                . PHP_EOL;

            echo 'Reply ID : '
                . (
                    isset($result['reply_id'])
                        ? (string) $result['reply_id']
                        : 'inconnu'
                )
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        /*
         * État théoriquement impossible avec le Handler actuel.
         * On le compte comme erreur pour ne pas le masquer.
         */

        $errorCount++;

        echo 'Résultat : UNEXPECTED'
            . PHP_EOL;

        echo PHP_EOL;
    }

    /*
    |--------------------------------------------------------------------------
    | Résumé
    |--------------------------------------------------------------------------
    */

    echo '========================================'
        . PHP_EOL;

    echo 'Résumé'
        . PHP_EOL;

    echo '========================================'
        . PHP_EOL;

    echo 'Messages analysés : '
        . count($messages)
        . PHP_EOL;

    echo 'Correspondances : '
        . $matchedCount
        . PHP_EOL;

    echo (
        $dryRun
            ? 'Correspondances simulées : '
            : 'Nouvelles réponses : '
    )
        . (
            $dryRun
                ? $matchedCount
                : $createdCount
        )
        . PHP_EOL;

    echo 'Doublons : '
        . $duplicateCount
        . PHP_EOL;

    echo 'Ignorés : '
        . $ignoredCount
        . PHP_EOL;

    echo 'Erreurs : '
        . $errorCount
        . PHP_EOL;

    echo PHP_EOL;
} finally {
    /*
    |--------------------------------------------------------------------------
    | Libération garantie du verrou
    |--------------------------------------------------------------------------
    */

    if ($lockAcquired) {
        try {
            $statement = $pdo->prepare(
                'SELECT RELEASE_LOCK(:lock_name)'
            );

            $statement->execute([
                'lock_name' => $lockName,
            ]);

            echo 'Verrou inbox : libéré'
                . PHP_EOL;
        } catch (Throwable $exception) {
            error_log(
                '[PROSPECTION] Impossible de libérer '
                . 'le verrou inbox : '
                . $exception->getMessage()
            );
        }
    }
}

echo 'Orchestrateur terminé.'
    . PHP_EOL;

echo PHP_EOL;
