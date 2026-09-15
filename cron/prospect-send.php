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
    . '/../php/prospection/ProspectReplySendGuard.php';

require_once __DIR__
    . '/../php/prospection/ProspectFollowUpSendGuard.php';

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

$testEmailId = null;

$rawTestEmailId =
    trim(
        (string) (
            $_ENV['PROSPECTION_TEST_EMAIL_ID']
            ?? getenv('PROSPECTION_TEST_EMAIL_ID')
            ?: ''
        )
    );

if ($rawTestEmailId !== '') {
    if (
        filter_var(
            $rawTestEmailId,
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        ) === false
    ) {
        throw new RuntimeException(
            'PROSPECTION_TEST_EMAIL_ID invalide.'
        );
    }

    $testEmailId = (int) $rawTestEmailId;
}

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

$replySendGuard =
    new ProspectReplySendGuard(
        $pdo,
        $config
    );

$followUpSendGuard =
    new ProspectFollowUpSendGuard(
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

if ($testEmailId !== null) {
    echo 'Mode test ciblé : email #'
        . $testEmailId
        . PHP_EOL;
}

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
    | Détection des envois stale
    |--------------------------------------------------------------------------
    |
    | Un e-mail resté "sending" trop longtemps peut correspondre
    | à un processus interrompu après acceptation SMTP.
    |
    | Dans ce cas, aucun retry automatique :
    | il passe en reconciliation_required.
    |
    */

    $staleSendingMinutes = (int) (
        $config['sending']['stale_sending_minutes']
        ?? 15
    );

    if ($staleSendingMinutes < 1) {
        throw new RuntimeException(
            'Le délai de détection des envois stale est invalide.'
        );
    }

    $staleEmails =
        $repository
            ->findStaleSendingInitialEmails(
                $now,
                $staleSendingMinutes
            );

    echo 'Envois sending stale : '
        . count($staleEmails)
        . PHP_EOL;

    $reconciliationCount = 0;

    foreach ($staleEmails as $staleEmail) {
        $staleEmailId =
            (int) $staleEmail['email_id'];

        if (
            $testEmailId !== null
            && $staleEmailId !== $testEmailId
        ) {
            continue;
        }

        try {
            $result =
                $repository
                    ->markStaleInitialEmailForReconciliation(
                        $staleEmailId,
                        $now,
                        $staleSendingMinutes
                    );

            $reconciliationCount++;

            echo 'Réconciliation requise : email #'
                . $staleEmailId
                . ' / prospect #'
                . (int) $result['prospect_id']
                . PHP_EOL;
        } catch (Throwable $exception) {
            /*
            * Ce cas peut arriver si l'état a changé
            * entre la détection et le verrou FOR UPDATE.
            *
            * On ne bloque pas tout le cron pour autant.
            */

            echo 'Stale ignoré : email #'
                . $staleEmailId
                . ' - '
                . $exception->getMessage()
                . PHP_EOL;
        }
    }

    echo 'Placés en réconciliation : '
        . $reconciliationCount
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Detection des relances stale
    |--------------------------------------------------------------------------
    |
    | Une relance restee "sending" trop longtemps peut avoir ete
    | acceptee par SMTP avant une interruption locale.
    |
    | Aucun retry automatique dans ce cas :
    | elle passe en reconciliation_required.
    |
    */

    $staleFollowUpEmails =
        $repository
            ->findStaleSendingFollowUpEmails(
                $now,
                $staleSendingMinutes
            );

    echo 'Relances sending stale : '
        . count($staleFollowUpEmails)
        . PHP_EOL;

    $followUpReconciliationCount = 0;

    foreach (
        $staleFollowUpEmails
        as $staleFollowUpEmail
    ) {
        $staleFollowUpEmailId =
            (int) $staleFollowUpEmail[
                'email_id'
            ];

        /*
        |--------------------------------------------------------------------------
        | Filtre de test cible
        |--------------------------------------------------------------------------
        |
        | Contrairement a l'ancien comportement INITIAL / REPLY,
        | le mode cible ne doit jamais reconcilier un autre e-mail.
        |
        */

        if (
            $testEmailId !== null
            && $staleFollowUpEmailId
                !== $testEmailId
        ) {
            continue;
        }

        try {
            $result =
                $repository
                    ->markStaleFollowUpEmailForReconciliation(
                        $staleFollowUpEmailId,
                        $now,
                        $staleSendingMinutes
                    );

            $followUpReconciliationCount++;

            echo 'Reconciliation relance requise : email #'
                . $staleFollowUpEmailId
                . ' / prospect #'
                . (int) $result[
                    'prospect_id'
                ]
                . PHP_EOL;

            if (
                (int) (
                    $result[
                        'cancelled_follow_up_2'
                    ]
                    ?? 0
                ) > 0
            ) {
                echo 'Relance 2 annulee : '
                    . (int) $result[
                        'cancelled_follow_up_2'
                    ]
                    . PHP_EOL;
            }
        } catch (Throwable $exception) {
            /*
            * L'etat a pu changer entre la detection
            * et le verrou FOR UPDATE.
            */

            echo 'Relance stale ignoree : email #'
                . $staleFollowUpEmailId
                . ' - '
                . $exception->getMessage()
                . PHP_EOL;
        }
    }

    echo 'Relances placees en reconciliation : '
        . $followUpReconciliationCount
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Détection des réponses stale
    |--------------------------------------------------------------------------
    |
    | Une réponse restée "sending" trop longtemps peut avoir été
    | acceptée par SMTP avant une interruption locale.
    |
    | Aucun retry automatique dans ce cas :
    | elle passe en reconciliation_required.
    |
    */

    $staleReplyEmails =
        $repository
            ->findStaleSendingReplyEmails(
                $now,
                $staleSendingMinutes
            );

    echo 'Réponses sending stale : '
        . count($staleReplyEmails)
        . PHP_EOL;

    $replyReconciliationCount = 0;

    foreach ($staleReplyEmails as $staleReplyEmail) {
        $staleReplyEmailId =
            (int) $staleReplyEmail['email_id'];

        if (
            $testEmailId !== null
            && $staleReplyEmailId
                !== $testEmailId
        ) {
            continue;
        }

        try {
            $result =
                $repository
                    ->markStaleReplyEmailForReconciliation(
                        $staleReplyEmailId,
                        $now,
                        $staleSendingMinutes
                    );

            $replyReconciliationCount++;

            echo 'Réconciliation réponse requise : email #'
                . $staleReplyEmailId
                . ' / prospect #'
                . (int) $result['prospect_id']
                . PHP_EOL;
        } catch (Throwable $exception) {
            /*
            * L'état a pu changer entre la détection
            * et le verrou FOR UPDATE.
            */

            echo 'Réponse stale ignorée : email #'
                . $staleReplyEmailId
                . ' - '
                . $exception->getMessage()
                . PHP_EOL;
        }
    }

    echo 'Réponses placées en réconciliation : '
        . $replyReconciliationCount
        . PHP_EOL;

    echo PHP_EOL;

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
        echo 'Limite quotidienne des premiers contacts atteinte.'
            . PHP_EOL;

        echo 'Les relances et reponses programmees restent eligibles.'
            . PHP_EOL;

        echo PHP_EOL;
    }

    /*
    |--------------------------------------------------------------------------
    | E-mails arrivés à échéance
    |--------------------------------------------------------------------------
    */

    $emails = [];

    if ($remaining > 0) {
        $emails =
            $repository
                ->findDueScheduledInitialEmails(
                    $now,
                    $remaining
                );
    }

    echo 'E-mails arrivés à échéance : '
        . count($emails)
        . PHP_EOL;

    echo PHP_EOL;

    if ($emails === []) {
        echo 'Aucun premier contact a envoyer.'
            . PHP_EOL;

        echo PHP_EOL;
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
        | Filtre de test ciblé
        |--------------------------------------------------------------------------
        */

        if (
            $testEmailId !== null
            && (int) $email['email_id'] !== $testEmailId
        ) {
            continue;
        }

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
        | Relances programmees
        |--------------------------------------------------------------------------
        |
        | Les relances J+5 / J+12 sont independantes du quota quotidien
        | des premiers contacts.
        |
        */

        echo '========================================'
            . PHP_EOL;

        echo 'Relances programmees'
            . PHP_EOL;

        echo '========================================'
            . PHP_EOL;

        $followUpEmails =
            $repository
                ->findDueScheduledFollowUpEmails(
                    $now,
                    50
                );

        echo 'Relances arrivees a echeance : '
            . count($followUpEmails)
            . PHP_EOL;

        echo PHP_EOL;

        $followUpSentCount = 0;
        $followUpFailedCount = 0;
        $followUpRetryCount = 0;
        $followUpBlockedCount = 0;

        if ($followUpEmails === []) {
            echo 'Aucune relance a envoyer.'
                . PHP_EOL;

            echo PHP_EOL;
        }

        foreach ($followUpEmails as $email) {
            /*
            |--------------------------------------------------------------------------
            | Filtre de test cible
            |--------------------------------------------------------------------------
            */

            if (
                $testEmailId !== null
                && (int) $email['email_id']
                    !== $testEmailId
            ) {
                continue;
            }

            $emailId =
                (int) $email['email_id'];

            $prospectId =
                (int) $email['prospect_id'];

            echo '----------------------------------------'
                . PHP_EOL;

            echo 'Email relance ID : '
                . $emailId
                . PHP_EOL;

            echo 'Type : '
                . (string) $email['email_type']
                . PHP_EOL;

            echo 'Prospect ID : '
                . $prospectId
                . PHP_EOL;

            echo 'Entreprise : '
                . (string) $email['company_name']
                . PHP_EOL;

            /*
            |--------------------------------------------------------------------------
            | Premier controle
            |--------------------------------------------------------------------------
            */

            $guardResult =
                $followUpSendGuard->evaluate(
                    $email
                );

            if (!$guardResult['allowed']) {
                $followUpBlockedCount++;

                echo 'Resultat : BLOCKED'
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

            echo 'Guard relance : OK'
                . PHP_EOL;

            echo 'Destinataire reel : '
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
            | Preparation + threading
            |--------------------------------------------------------------------------
            */

            try {
                $message =
                    $mailer->prepare(
                        $email,
                        $guardResult
                    );
            } catch (Throwable $exception) {
                $followUpBlockedCount++;

                echo 'Preparation relance : ERREUR'
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

            echo 'In-Reply-To : '
                . (
                    $message['in_reply_to']
                        ?? 'aucun'
                )
                . PHP_EOL;

            $references =
                $message['references']
                ?? [];

            echo 'References : '
                . (
                    is_array($references)
                    && $references !== []
                        ? implode(
                            ' ',
                            $references
                        )
                        : 'aucune'
                )
                . PHP_EOL;

            /*
            |--------------------------------------------------------------------------
            | Claim atomique
            |--------------------------------------------------------------------------
            */

            try {
                $repository
                    ->claimScheduledFollowUpEmail(
                        $emailId,
                        $messageId,
                        $now,
                        $maxAttempts
                    );
            } catch (Throwable $exception) {
                $followUpBlockedCount++;

                echo 'Claim relance : REFUSE'
                    . PHP_EOL;

                echo $exception->getMessage()
                    . PHP_EOL;

                echo PHP_EOL;

                continue;
            }

            echo 'Claim relance : OK'
                . PHP_EOL;

            /*
            |--------------------------------------------------------------------------
            | Derniere barriere avant SMTP
            |--------------------------------------------------------------------------
            */

            $claimedEmail = $email;

            $claimedEmail['email_status'] =
                'sending';

            $claimedEmail['status'] =
                'sending';

            $claimedEmail['send_attempts'] =
                (int) $email['send_attempts']
                + 1;

            $finalGuard =
                $followUpSendGuard->evaluate(
                    $claimedEmail
                );

            if (!$finalGuard['allowed']) {
                $errorMessage =
                    'Envoi relance bloque apres claim : '
                    . implode(
                        ' | ',
                        $finalGuard['reasons']
                    );

                $failure =
                    $repository
                        ->markFollowUpEmailFailed(
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
                    $followUpRetryCount++;

                    echo 'Resultat : RETRY'
                        . PHP_EOL;

                    echo 'Nouvelle tentative : '
                        . (string) $failure[
                            'retry_at'
                        ]
                        . PHP_EOL;
                } else {
                    $followUpFailedCount++;

                    echo 'Resultat : FAILED'
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
            | Echec SMTP avant acceptation
            |--------------------------------------------------------------------------
            */

            if (
                ($transportResult['success'] ?? false)
                !== true
            ) {
                $errorMessage = trim(
                    (string) (
                        $transportResult['error']
                        ?? 'Echec SMTP inconnu.'
                    )
                );

                if ($errorMessage === '') {
                    $errorMessage =
                        'Echec SMTP inconnu.';
                }

                $failure =
                    $repository
                        ->markFollowUpEmailFailed(
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
                    $followUpRetryCount++;

                    echo 'SMTP relance : ECHEC'
                        . PHP_EOL;

                    echo 'Resultat : RETRY'
                        . PHP_EOL;

                    echo 'Nouvelle tentative : '
                        . (string) $failure[
                            'retry_at'
                        ]
                        . PHP_EOL;
                } else {
                    $followUpFailedCount++;

                    echo 'SMTP relance : ECHEC'
                        . PHP_EOL;

                    echo 'Resultat : FAILED'
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
            | Verification du Message-ID SMTP
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

            if (
                $smtpMessageId === ''
                || $smtpMessageId !== $messageId
            ) {
                /*
                * SMTP peut deja avoir accepte le message.
                *
                * Aucun retry automatique.
                * L'e-mail reste sending et sera traite
                * par la reconciliation stale.
                */

                echo 'SMTP relance : ACCEPTE'
                    . PHP_EOL;

                echo 'Etat local : A RECONCILIER'
                    . PHP_EOL;

                echo 'Erreur : Message-ID SMTP different '
                    . 'du Message-ID reserve.'
                    . PHP_EOL;

                echo PHP_EOL;

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Persistance du succes
            |--------------------------------------------------------------------------
            */

            try {
                $repository
                    ->markFollowUpEmailSent(
                        $emailId,
                        $messageId
                    );
            } catch (Throwable $exception) {
                /*
                * SMTP a deja accepte le message.
                * Aucun retry automatique.
                */

                echo 'SMTP relance : ACCEPTE'
                    . PHP_EOL;

                echo 'Persistance locale : ERREUR'
                    . PHP_EOL;

                echo 'Etat : A RECONCILIER'
                    . PHP_EOL;

                echo 'Erreur : '
                    . $exception->getMessage()
                    . PHP_EOL;

                echo PHP_EOL;

                continue;
            }

            $followUpSentCount++;

            echo 'SMTP relance : OK'
                . PHP_EOL;

            echo 'Resultat : SENT'
                . PHP_EOL;

            echo PHP_EOL;
        }

        /*
        |--------------------------------------------------------------------------
        | Réponses programmées
        |--------------------------------------------------------------------------
        |
        | Les réponses validées manuellement utilisent le même verrou global
        | que les premiers contacts, mais ne consomment pas leur quota quotidien.
        |
        */

        echo '========================================'
            . PHP_EOL;

        echo 'Réponses programmées'
            . PHP_EOL;

        echo '========================================'
            . PHP_EOL;

        $replyEmails =
            $repository
                ->findDueScheduledReplyEmails(
                    $now,
                    50
                );

        echo 'Réponses arrivées à échéance : '
            . count($replyEmails)
            . PHP_EOL;

        echo PHP_EOL;

        $replySentCount = 0;
        $replyFailedCount = 0;
        $replyRetryCount = 0;
        $replyBlockedCount = 0;

        if ($replyEmails === []) {
            echo 'Aucune réponse à envoyer.'
                . PHP_EOL;

            echo PHP_EOL;
        }

        foreach ($replyEmails as $email) {
            /*
            |--------------------------------------------------------------------------
            | Filtre de test ciblé
            |--------------------------------------------------------------------------
            */

            if (
                $testEmailId !== null
                && (int) $email['email_id'] !== $testEmailId
            ) {
                continue;
            }

            $emailId =
                (int) $email['email_id'];

            $prospectId =
                (int) $email['prospect_id'];

            echo '----------------------------------------'
                . PHP_EOL;

            echo 'Email réponse ID : '
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
            | Premier contrôle
            |--------------------------------------------------------------------------
            */

            $guardResult =
                $replySendGuard->evaluate(
                    $email
                );

            if (!$guardResult['allowed']) {
                $replyBlockedCount++;

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

            echo 'Guard réponse : OK'
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
            | Préparation du message
            |--------------------------------------------------------------------------
            |
            | ProspectMailer génère ici :
            | - le nouveau Message-ID ;
            | - In-Reply-To ;
            | - References.
            |
            */

            try {
                $message =
                    $mailer->prepare(
                        $email,
                        $guardResult
                    );
            } catch (Throwable $exception) {
                $replyBlockedCount++;

                echo 'Préparation réponse : ERREUR'
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

            echo 'In-Reply-To : '
                . (
                    $message['in_reply_to']
                        ?? 'aucun'
                )
                . PHP_EOL;

            $references =
                $message['references']
                ?? [];

            echo 'References : '
                . (
                    is_array($references)
                    && $references !== []
                        ? implode(
                            ' ',
                            $references
                        )
                        : 'aucune'
                )
                . PHP_EOL;

            /*
            |--------------------------------------------------------------------------
            | Claim atomique
            |--------------------------------------------------------------------------
            */

            try {
                $repository
                    ->claimScheduledReplyEmail(
                        $emailId,
                        $messageId,
                        $now,
                        $maxAttempts
                    );
            } catch (Throwable $exception) {
                $replyBlockedCount++;

                echo 'Claim réponse : REFUSÉ'
                    . PHP_EOL;

                echo $exception->getMessage()
                    . PHP_EOL;

                echo PHP_EOL;

                continue;
            }

            echo 'Claim réponse : OK'
                . PHP_EOL;

            /*
            |--------------------------------------------------------------------------
            | Dernière barrière avant SMTP
            |--------------------------------------------------------------------------
            */

            $claimedEmail = $email;

            $claimedEmail['email_status'] =
                'sending';

            $claimedEmail['status'] =
                'sending';

            $claimedEmail['send_attempts'] =
                (int) $email['send_attempts']
                + 1;

            $finalGuard =
                $replySendGuard->evaluate(
                    $claimedEmail
                );

            if (!$finalGuard['allowed']) {
                $errorMessage =
                    'Envoi réponse bloqué après claim : '
                    . implode(
                        ' | ',
                        $finalGuard['reasons']
                    );

                $failure =
                    $repository
                        ->markReplyEmailFailed(
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
                    $replyRetryCount++;

                    echo 'Résultat : RETRY'
                        . PHP_EOL;

                    echo 'Nouvelle tentative : '
                        . (string) $failure[
                            'retry_at'
                        ]
                        . PHP_EOL;
                } else {
                    $replyFailedCount++;

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
                        ->markReplyEmailFailed(
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
                    $replyRetryCount++;

                    echo 'SMTP réponse : ÉCHEC'
                        . PHP_EOL;

                    echo 'Résultat : RETRY'
                        . PHP_EOL;

                    echo 'Nouvelle tentative : '
                        . (string) $failure[
                            'retry_at'
                        ]
                        . PHP_EOL;
                } else {
                    $replyFailedCount++;

                    echo 'SMTP réponse : ÉCHEC'
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
            | Vérification du Message-ID SMTP
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

            if (
                $smtpMessageId === ''
                || $smtpMessageId !== $messageId
            ) {
                /*
                * Le serveur SMTP peut déjà avoir accepté
                * le message.
                *
                * Aucun retry automatique.
                * L'e-mail reste en sending et sera placé
                * en reconciliation_required par le contrôle stale.
                */

                echo 'SMTP réponse : ACCEPTÉ'
                    . PHP_EOL;

                echo 'État local : À RÉCONCILIER'
                    . PHP_EOL;

                echo 'Erreur : Message-ID SMTP différent '
                    . 'du Message-ID réservé.'
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
                    ->markReplyEmailSent(
                        $emailId,
                        $messageId
                    );
            } catch (Throwable $exception) {
                /*
                * SMTP a déjà accepté le message.
                * Aucun retry automatique ici.
                */

                echo 'SMTP réponse : ACCEPTÉ'
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

            $replySentCount++;

            echo 'SMTP réponse : OK'
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

    echo 'Réconciliations premiers contacts : '
        . $reconciliationCount
        . PHP_EOL;

    echo 'Relances envoyees : '
        . $followUpSentCount
        . PHP_EOL;

    echo 'Relances en retry : '
        . $followUpRetryCount
        . PHP_EOL;

    echo 'Relances echouees : '
        . $followUpFailedCount
        . PHP_EOL;

    echo 'Relances bloquees : '
        . $followUpBlockedCount
        . PHP_EOL;

    echo 'Réconciliations relances : '
        . $followUpReconciliationCount
        . PHP_EOL;

    echo 'Réconciliations réponses : '
        . $replyReconciliationCount
        . PHP_EOL;

    echo 'Premiers contacts envoyés : '
        . $sentCount
        . PHP_EOL;

    echo 'Premiers contacts en retry : '
        . $retryCount
        . PHP_EOL;

    echo 'Premiers contacts échoués : '
        . $failedCount
        . PHP_EOL;

    echo 'Premiers contacts bloqués : '
        . $blockedCount
        . PHP_EOL;

    echo 'Réponses envoyées : '
        . $replySentCount
        . PHP_EOL;

    echo 'Réponses en retry : '
        . $replyRetryCount
        . PHP_EOL;

    echo 'Réponses échouées : '
        . $replyFailedCount
        . PHP_EOL;

    echo 'Réponses bloquées : '
        . $replyBlockedCount
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
