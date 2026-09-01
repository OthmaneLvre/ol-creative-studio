<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| OL Creative Studio
| Prospection - Orchestrateur SMTP
|--------------------------------------------------------------------------
|
| Responsabilités :
| - verrouiller l'orchestrateur d'envoi ;
| - respecter la limite quotidienne réelle ;
| - récupérer les e-mails arrivés à échéance ;
| - appliquer les contrôles de sécurité ;
| - préparer le message ;
| - réserver la tentative et son Message-ID ;
| - effectuer l'envoi SMTP ;
| - enregistrer le succès ou programmer un retry.
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

require_once __DIR__
    . '/../php/prospection/ProspectMailer.php';

require_once __DIR__
    . '/../php/prospection/ProspectMailTransport.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$config = require __DIR__
    . '/../config/prospection.php';

$mailConfig = require __DIR__
    . '/../config/mail.php';

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

$maxDailySends = (int) (
    $config['sending']['max_daily_sends']
    ?? 5
);

$maxAttempts = (int) (
    $config['sending']['max_attempts']
    ?? 3
);

$retryDelayMinutes = (int) (
    $config['sending']['retry_delay_minutes']
    ?? 30
);

$sendingEnabled = (bool) (
    $config['sending']['enabled']
    ?? false
);

if ($maxDailySends < 1) {
    throw new RuntimeException(
        'La limite quotidienne d’envoi est invalide.'
    );
}

if ($maxAttempts < 1) {
    throw new RuntimeException(
        'Le nombre maximal de tentatives est invalide.'
    );
}

if ($retryDelayMinutes < 1) {
    throw new RuntimeException(
        'Le délai de retry est invalide.'
    );
}

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

$mailer = new ProspectMailer(
    $config
);

$transport = new ProspectMailTransport(
    $mailConfig
);

/*
|--------------------------------------------------------------------------
| En-tête
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'OL Creative Studio - Prospection SMTP'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'Date : '
    . $now->format('Y-m-d H:i:s')
    . PHP_EOL;

echo 'Kill switch : '
    . (
        $sendingEnabled
            ? 'ENABLED'
            : 'DISABLED'
    )
    . PHP_EOL;

echo 'Limite quotidienne : '
    . $maxDailySends
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Verrou global du mailer
|--------------------------------------------------------------------------
|
| Le verrou MySQL empêche deux exécutions concurrentes
| du cron d'envoyer simultanément les mêmes e-mails.
|
*/

$lockAcquired = false;

try {
    $lockAcquired =
        $repository->acquireMailerLock(0);

    if (!$lockAcquired) {
        echo 'Orchestrateur déjà en cours d’exécution.'
            . PHP_EOL;

        exit(0);
    }

    echo 'Verrou mailer : acquis'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Limite quotidienne réelle
    |--------------------------------------------------------------------------
    */

    $sentToday =
        $repository
            ->countSentInitialEmailsForDate(
                $now
            );

    $remaining =
        max(
            0,
            $maxDailySends - $sentToday
        );

    echo 'Envoyés aujourd’hui : '
        . $sentToday
        . PHP_EOL;

    echo 'Places restantes : '
        . $remaining
        . PHP_EOL;

    echo PHP_EOL;

    if ($remaining <= 0) {
        echo 'Limite quotidienne atteinte.'
            . PHP_EOL;

        exit(0);
    }

    /*
    |--------------------------------------------------------------------------
    | E-mails arrivés à échéance
    |--------------------------------------------------------------------------
    */

    $emails =
        $repository
            ->findDueScheduledInitialEmails(
                $now,
                $remaining
            );

    echo 'E-mails arrivés à échéance : '
        . count($emails)
        . PHP_EOL;

    echo PHP_EOL;

    if ($emails === []) {
        echo 'Aucun e-mail à envoyer.'
            . PHP_EOL;

        exit(0);
    }

    /*
    |--------------------------------------------------------------------------
    | Compteurs
    |--------------------------------------------------------------------------
    */

    $sentCount = 0;
    $failedCount = 0;
    $retryCount = 0;
    $blockedCount = 0;

    /*
    |--------------------------------------------------------------------------
    | Traitement
    |--------------------------------------------------------------------------
    */

    foreach ($emails as $email) {
        /*
        |--------------------------------------------------------------------------
        | Protection supplémentaire du quota
        |--------------------------------------------------------------------------
        */

        if ($sentCount >= $remaining) {
            break;
        }

        $emailId =
            (int) $email['email_id'];

        $prospectId =
            (int) $email['prospect_id'];

        echo '----------------------------------------'
            . PHP_EOL;

        echo 'Email ID : '
            . $emailId
            . PHP_EOL;

        echo 'Prospect ID : '
            . $prospectId
            . PHP_EOL;

        echo 'Entreprise : '
            . (string) $email['company_name']
            . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | SendGuard
        |--------------------------------------------------------------------------
        */

        $guardResult =
            $sendGuard->evaluate(
                $email
            );

        if (!$guardResult['allowed']) {
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

            echo PHP_EOL;

            continue;
        }

        echo 'Guard : OK'
            . PHP_EOL;

        echo 'Destinataire réel : '
            . (string) $guardResult[
                'recipient_email'
            ]
            . PHP_EOL;

        echo 'Destinataire effectif : '
            . (string) $guardResult[
                'effective_recipient_email'
            ]
            . PHP_EOL;

        echo 'Mode : '
            . (
                $guardResult['redirected']
                    ? 'REDIRECTED'
                    : 'DIRECT'
            )
            . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | Préparation
        |--------------------------------------------------------------------------
        |
        | Le Message-ID est généré ici AVANT le claim.
        |
        */

        try {
            $message =
                $mailer->prepare(
                    $email,
                    $guardResult
                );
        } catch (Throwable $exception) {
            $blockedCount++;

            echo 'Préparation : ERREUR'
                . PHP_EOL;

            echo $exception->getMessage()
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        $messageId =
            (string) $message['message_id'];

        echo 'Message-ID : '
            . $messageId
            . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | Claim atomique
        |--------------------------------------------------------------------------
        |
        | Après ce point :
        |
        | scheduled -> sending
        | send_attempts + 1
        | Message-ID persisté
        |
        */

        try {
            $repository
                ->claimScheduledInitialEmail(
                    $emailId,
                    $messageId,
                    $maxAttempts
                );
        } catch (Throwable $exception) {
            $blockedCount++;

            echo 'Claim : REFUSÉ'
                . PHP_EOL;

            echo $exception->getMessage()
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        echo 'Claim : OK'
            . PHP_EOL;

        /*
        |--------------------------------------------------------------------------
        | Dernière barrière avant SMTP
        |--------------------------------------------------------------------------
        |
        | On réévalue immédiatement les règles après le claim.
        |
        | Le prospect reste "scheduled", et send_attempts vient
        | d'être incrémenté.
        |
        */

        $claimedEmail = $email;

        $claimedEmail['status'] =
            'sending';

        $claimedEmail['send_attempts'] =
            (int) $email['send_attempts']
            + 1;

        $finalGuard =
            $sendGuard->evaluate(
                $claimedEmail
            );

        if (!$finalGuard['allowed']) {
            $errorMessage =
                'Envoi bloqué après claim : '
                . implode(
                    ' | ',
                    $finalGuard['reasons']
                );

            $failure =
                $repository
                    ->markInitialEmailFailed(
                        $emailId,
                        $errorMessage,
                        $maxAttempts,
                        $retryDelayMinutes
                    );

            if (
                $failure[
                    'retry_scheduled'
                ]
            ) {
                $retryCount++;

                echo 'Résultat : RETRY'
                    . PHP_EOL;

                echo 'Nouvelle tentative : '
                    . (string) $failure[
                        'retry_at'
                    ]
                    . PHP_EOL;
            } else {
                $failedCount++;

                echo 'Résultat : FAILED'
                    . PHP_EOL;
            }

            echo $errorMessage
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Envoi SMTP
        |--------------------------------------------------------------------------
        */

        try {
            $transportResult =
                $transport->send(
                    $message
                );
        } catch (Throwable $exception) {
            $transportResult = [
                'success' => false,
                'message_id' => null,
                'error' =>
                    $exception->getMessage(),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Échec SMTP
        |--------------------------------------------------------------------------
        */

        if (
            ($transportResult['success'] ?? false)
            !== true
        ) {
            $errorMessage = trim(
                (string) (
                    $transportResult['error']
                    ?? 'Échec SMTP inconnu.'
                )
            );

            if ($errorMessage === '') {
                $errorMessage =
                    'Échec SMTP inconnu.';
            }

            $failure =
                $repository
                    ->markInitialEmailFailed(
                        $emailId,
                        $errorMessage,
                        $maxAttempts,
                        $retryDelayMinutes
                    );

            if (
                $failure[
                    'retry_scheduled'
                ]
            ) {
                $retryCount++;

                echo 'SMTP : ÉCHEC'
                    . PHP_EOL;

                echo 'Résultat : RETRY'
                    . PHP_EOL;

                echo 'Nouvelle tentative : '
                    . (string) $failure[
                        'retry_at'
                    ]
                    . PHP_EOL;
            } else {
                $failedCount++;

                echo 'SMTP : ÉCHEC'
                    . PHP_EOL;

                echo 'Résultat : FAILED'
                    . PHP_EOL;
            }

            echo 'Erreur : '
                . $errorMessage
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Succès SMTP
        |--------------------------------------------------------------------------
        */

        $smtpMessageId = trim(
            (string) (
                $transportResult[
                    'message_id'
                ]
                ?? ''
            )
        );

        /*
        * ProspectMailTransport impose normalement
        * le Message-ID préparé à PHPMailer.
        *
        * On refuse néanmoins toute divergence.
        */

        if (
            $smtpMessageId === ''
            || $smtpMessageId !== $messageId
        ) {
            $errorMessage =
                'Le Message-ID retourné par SMTP '
                . 'ne correspond pas au Message-ID réservé.';

            /*
            * ATTENTION :
            * SMTP a potentiellement accepté le message.
            *
            * On ne le marque donc PAS comme failed/retry ici,
            * afin d’éviter un renvoi automatique pouvant créer
            * un doublon.
            */

            echo 'SMTP : ACCEPTÉ'
                . PHP_EOL;

            echo 'État local : À RÉCONCILIER'
                . PHP_EOL;

            echo 'Erreur : '
                . $errorMessage
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Persistance du succès
        |--------------------------------------------------------------------------
        */

        try {
            $repository
                ->markInitialEmailSent(
                    $emailId,
                    $messageId
                );
        } catch (Throwable $exception) {
            /*
            * Même principe :
            *
            * SMTP a déjà accepté le message.
            * Il serait dangereux de programmer
            * automatiquement un retry.
            */

            echo 'SMTP : ACCEPTÉ'
                . PHP_EOL;

            echo 'Persistance locale : ERREUR'
                . PHP_EOL;

            echo 'État : À RÉCONCILIER'
                . PHP_EOL;

            echo 'Erreur : '
                . $exception->getMessage()
                . PHP_EOL;

            echo PHP_EOL;

            continue;
        }

        $sentCount++;

        echo 'SMTP : OK'
            . PHP_EOL;

        echo 'Résultat : SENT'
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

    echo 'Envoyés : '
        . $sentCount
        . PHP_EOL;

    echo 'Retries programmés : '
        . $retryCount
        . PHP_EOL;

    echo 'Échecs définitifs : '
        . $failedCount
        . PHP_EOL;

    echo 'Bloqués : '
        . $blockedCount
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
            $repository
                ->releaseMailerLock();

            echo 'Verrou mailer : libéré'
                . PHP_EOL;
        } catch (Throwable $exception) {
            error_log(
                '[PROSPECTION] Impossible de libérer '
                . 'le verrou mailer : '
                . $exception->getMessage()
            );
        }
    }
}

echo 'Orchestrateur terminé.'
    . PHP_EOL;

echo PHP_EOL;
