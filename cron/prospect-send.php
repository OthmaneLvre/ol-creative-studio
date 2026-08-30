<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OL Creative Studio
| Prospection - Orchestrateur d'envoi
|--------------------------------------------------------------------------
|
| DRY-RUN uniquement.
|
| Le script :
| - détecte les e-mails arrivés à échéance ;
| - applique les contrôles de sécurité ;
| - indique s'ils seraient envoyables ;
| - n'effectue aucun envoi SMTP.
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
    . '/../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../php/prospection/ProspectSendGuard.php';

$config = require __DIR__
    . '/../config/prospection.php';

/*
|--------------------------------------------------------------------------
| Fuseau horaire
|--------------------------------------------------------------------------
*/

$timezone = new DateTimeZone(
    (string) (
        $config['timezone']
        ?? 'Europe/Paris'
    )
);

$now = new DateTimeImmutable(
    'now',
    $timezone
);

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$maxDailySends = (int) (
    $config['sending']['max_daily_sends']
    ?? 5
);

if ($maxDailySends < 1) {
    throw new RuntimeException(
        'La limite quotidienne d’envoi est invalide.'
    );
}

$sendingEnabled = (bool) (
    $config['sending']['enabled']
    ?? false
);

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

$repository = new ProspectRepository(
    $pdo
);

$sendGuard = new ProspectSendGuard(
    $pdo,
    $config
);

/*
|--------------------------------------------------------------------------
| Recherche des e-mails arrivés à échéance
|--------------------------------------------------------------------------
*/

$emails =
    $repository->findDueScheduledInitialEmails(
        $now,
        $maxDailySends
    );

/*
|--------------------------------------------------------------------------
| En-tête
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'OL Creative Studio - Prospection'
    . PHP_EOL;

echo 'DRY-RUN ENVOIS'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'Date : '
    . $now->format('Y-m-d H:i:s')
    . PHP_EOL;

echo 'Limite : '
    . $maxDailySends
    . PHP_EOL;

echo 'Kill switch : '
    . (
        $sendingEnabled
            ? 'ENABLED'
            : 'DISABLED'
    )
    . PHP_EOL;

echo 'E-mails arrivés à échéance : '
    . count($emails)
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Aucun e-mail dû
|--------------------------------------------------------------------------
*/

if ($emails === []) {
    echo 'Aucun e-mail à envoyer.'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Aucun e-mail SMTP envoyé.'
        . PHP_EOL;

    exit(0);
}

/*
|--------------------------------------------------------------------------
| Contrôle des e-mails
|--------------------------------------------------------------------------
*/

$sendableCount = 0;
$blockedCount = 0;

foreach ($emails as $email) {
    $guardResult =
        $sendGuard->evaluate($email);

    echo '----------------------------------------'
        . PHP_EOL;

    echo 'Email ID : '
        . (int) $email['email_id']
        . PHP_EOL;

    echo 'Prospect ID : '
        . (int) $email['prospect_id']
        . PHP_EOL;

    echo 'Entreprise : '
        . (string) $email['company_name']
        . PHP_EOL;

    echo 'Destinataire : '
        . (string) $email['recipient_email']
        . PHP_EOL;

    echo 'Objet : '
        . (string) $email['subject']
        . PHP_EOL;

    echo 'Programmé : '
        . (string) $email['scheduled_at']
        . PHP_EOL;

    echo 'Tentatives : '
        . (int) $email['send_attempts']
        . '/'
        . (int) $guardResult['max_attempts']
        . PHP_EOL;

    echo 'Source e-mail : '
        . (
            $email['email_source_url']
            ?: 'non renseignée'
        )
        . PHP_EOL;

    echo 'Type e-mail : '
        . (
            $email['email_type']
            ?: 'unknown'
        )
        . PHP_EOL;

    echo 'Confiance : '
        . (
            $email['email_confidence'] !== null
                ? $email['email_confidence'] . '%'
                : 'non renseignée'
        )
        . PHP_EOL;

    echo PHP_EOL;

    if ($guardResult['allowed']) {
        $sendableCount++;

        echo 'Résultat : SENDABLE'
            . PHP_EOL;

        echo '[DRY-RUN] Cet e-mail serait autorisé.'
            . PHP_EOL;
    } else {
        $blockedCount++;

        echo 'Résultat : BLOCKED'
            . PHP_EOL;

        foreach (
            $guardResult['reasons']
            as $reason
        ) {
            echo '- '
                . $reason
                . PHP_EOL;
        }
    }

    echo PHP_EOL;

    echo '[DRY-RUN] Aucun envoi effectué.'
        . PHP_EOL;
}

/*
|--------------------------------------------------------------------------
| Résumé
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'Résumé'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'E-mails dus : '
    . count($emails)
    . PHP_EOL;

echo 'Envoyables : '
    . $sendableCount
    . PHP_EOL;

echo 'Bloqués : '
    . $blockedCount
    . PHP_EOL;

echo PHP_EOL;

echo 'Dry-run terminé.'
    . PHP_EOL;

echo 'Aucun e-mail SMTP envoyé.'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo PHP_EOL;
