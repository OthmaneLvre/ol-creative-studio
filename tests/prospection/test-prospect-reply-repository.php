<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

echo '=== TEST PROSPECT REPLY REPOSITORY ==='
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;

function checkReplyRepository(
    bool $condition,
    string $label
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;

        echo '[OK]   '
            . $label
            . PHP_EOL;

        return;
    }

    $failed++;

    echo '[FAIL] '
        . $label
        . PHP_EOL;
}

function expectReplyRepositoryException(
    callable $callback,
    string $label
): void {
    try {
        $callback();

        checkReplyRepository(
            false,
            $label
        );
    } catch (Throwable) {
        checkReplyRepository(
            true,
            $label
        );
    }
}

$prospectId = 0;

try {
    $unique =
        bin2hex(
            random_bytes(6)
        );

    $emailAddress =
        'reply-repository-'
        . $unique
        . '@example.test';

    /*
    |--------------------------------------------------------------------------
    | Prospect
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospects
            (
                company_name,
                activity,
                city,
                postal_code,
                email,
                status,
                source
            )
            VALUES
            (
                :company_name,
                :activity,
                :city,
                :postal_code,
                :email,
                :status,
                :source
            )
            '
        );

    $statement->execute([
        'company_name' =>
            'Reply Repository Test',

        'activity' =>
            'Plomberie',

        'city' =>
            'Ceret',

        'postal_code' =>
            '66400',

        'email' =>
            $emailAddress,

        'status' =>
            'replied',

        'source' =>
            'reply_repository_test',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Email initial envoye
    |--------------------------------------------------------------------------
    */

    $sourceMessageId =
        '<reply-repository-outbound-'
        . $unique
        . '@example.test>';

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails
            (
                prospect_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                sent_at,
                message_id,
                created_at,
                updated_at
            )
            VALUES
            (
                :prospect_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                NOW(),
                :message_id,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            $emailAddress,

        'subject' =>
            'Votre presence en ligne',

        'body_text' =>
            'Message initial.',

        'status' =>
            'sent',

        'message_id' =>
            $sourceMessageId,
    ]);

    $sourceEmailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Reponse entrante
    |--------------------------------------------------------------------------
    */

    $replyMessageId =
        '<reply-repository-inbound-'
        . $unique
        . '@example.test>';

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_replies
            (
                prospect_id,
                prospect_email_id,
                sender_email,
                subject,
                body_text,
                message_id,
                in_reply_to,
                classification,
                received_at,
                created_at
            )
            VALUES
            (
                :prospect_id,
                :prospect_email_id,
                :sender_email,
                :subject,
                :body_text,
                :message_id,
                :in_reply_to,
                :classification,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $sourceEmailId,

        'sender_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Bonjour, je suis interesse.',

        'message_id' =>
            $replyMessageId,

        'in_reply_to' =>
            $sourceMessageId,

        'classification' =>
            'positive',
    ]);

    $replyId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Reponse programmee
    |--------------------------------------------------------------------------
    */

    $scheduledAt =
        new DateTimeImmutable(
            '2026-09-10 15:00:00',
            new DateTimeZone(
                'Europe/Paris'
            )
        );

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails
            (
                prospect_id,
                source_reply_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                approved_at,
                scheduled_at,
                created_at,
                updated_at
            )
            VALUES
            (
                :prospect_id,
                :source_reply_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :approved_at,
                :scheduled_at,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'source_reply_id' =>
            $replyId,

        'email_type' =>
            'manual',

        'recipient_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Bonjour, merci pour votre retour.',

        'status' =>
            'scheduled',

        'approved_at' =>
            '2026-09-10 14:00:00',

        'scheduled_at' =>
            $scheduledAt->format(
                'Y-m-d H:i:s'
            ),
    ]);

    $emailId =
        (int) $pdo->lastInsertId();

    $repository =
        new ProspectRepository(
            $pdo
        );

    $now =
        new DateTimeImmutable(
            '2026-09-10 16:00:00',
            new DateTimeZone(
                'Europe/Paris'
            )
        );

    /*
    |--------------------------------------------------------------------------
    | Selection des reponses dues
    |--------------------------------------------------------------------------
    */

    $due =
        $repository
            ->findDueScheduledReplyEmails(
                $now,
                50
            );

    $found = null;

    foreach ($due as $row) {
        if (
            (int) $row['email_id']
            === $emailId
        ) {
            $found = $row;
            break;
        }
    }

    checkReplyRepository(
        $found !== null,
        'Reponse programmee retrouvee'
    );

    checkReplyRepository(
        $found !== null
        && (int) $found['source_reply_id']
            === $replyId,
        'Reply ID remonte'
    );

    checkReplyRepository(
        $found !== null
        && $found['reply_message_id']
            === $replyMessageId,
        'Message-ID entrant remonte'
    );

    checkReplyRepository(
        $found !== null
        && $found['source_message_id']
            === $sourceMessageId,
        'Message-ID source remonte'
    );

    checkReplyRepository(
        $found !== null
        && $found['reply_sender_email']
            === $emailAddress,
        'Expediteur de la reponse remonte'
    );

    /*
    |--------------------------------------------------------------------------
    | Claim atomique
    |--------------------------------------------------------------------------
    */

    $reservedMessageId =
        '<prospection-reply-'
        . $unique
        . '@olcreativestudio.fr>';

    $claimed =
        $repository
            ->claimScheduledReplyEmail(
                $emailId,
                $reservedMessageId,
                $now,
                3
            );

    checkReplyRepository(
        $claimed['status']
            === 'sending',
        'Reponse passee en sending'
    );

    checkReplyRepository(
        (int) $claimed['send_attempts']
            === 1,
        'Premiere tentative comptabilisee'
    );

    checkReplyRepository(
        $claimed['message_id']
            === $reservedMessageId,
        'Message-ID reserve'
    );

    checkReplyRepository(
        $claimed['reply_message_id']
            === $replyMessageId,
        'Threading entrant conserve apres claim'
    );

    checkReplyRepository(
        $claimed['source_message_id']
            === $sourceMessageId,
        'Threading source conserve apres claim'
    );

    /*
    |--------------------------------------------------------------------------
    | Etat BDD
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                send_attempts,
                message_id,
                sent_at
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $emailId,
    ]);

    $stored =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $stored['status']
            === 'sending',
        'Etat sending persiste'
    );

    checkReplyRepository(
        (int) $stored['send_attempts']
            === 1,
        'Tentative persistee'
    );

    checkReplyRepository(
        $stored['message_id']
            === $reservedMessageId,
        'Message-ID persiste'
    );

    checkReplyRepository(
        empty(
            $stored['sent_at']
        ),
        'sent_at reste NULL'
    );

    /*
    |--------------------------------------------------------------------------
    | Prospect non modifie
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            SELECT status
            FROM prospects
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $prospectId,
    ]);

    checkReplyRepository(
        $statement->fetchColumn()
            === 'replied',
        'Prospect reste replied'
    );

    /*
    |--------------------------------------------------------------------------
    | Event
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            SELECT
                event_type,
                previous_status,
                new_status,
                metadata
            FROM prospect_events
            WHERE prospect_id = :prospect_id
            AND event_type =
                :event_type
            ORDER BY id DESC
            LIMIT 1
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'event_type' =>
            'prospect_reply_email_sending',
    ]);

    $event =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $event !== false,
        'Evenement sending cree'
    );

    checkReplyRepository(
        $event !== false
        && $event['previous_status']
            === 'replied'
        && $event['new_status']
            === 'replied',
        'Evenement ne modifie pas le statut prospect'
    );

    /*
    |--------------------------------------------------------------------------
    | Double claim refuse
    |--------------------------------------------------------------------------
    */

    expectReplyRepositoryException(
        fn () =>
            $repository
                ->claimScheduledReplyEmail(
                    $emailId,
                    '<second-'
                        . $unique
                        . '@example.test>',
                    $now,
                    3
                ),
        'Double claim refuse'
    );

    /*
    |--------------------------------------------------------------------------
    | Envoi reussi
    |--------------------------------------------------------------------------
    */

    expectReplyRepositoryException(
        fn () =>
            $repository
                ->markReplyEmailSent(
                    $emailId,
                    '<wrong-message-id@example.test>'
                ),
        'Message-ID SMTP incorrect refuse'
    );

    $repository
        ->markReplyEmailSent(
            $emailId,
            $reservedMessageId
        );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                sent_at,
                message_id
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $emailId,
    ]);

    $sentEmail =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $sentEmail !== false
        && $sentEmail['status'] === 'sent',
        'Reponse marquee sent'
    );

    checkReplyRepository(
        $sentEmail !== false
        && !empty($sentEmail['sent_at']),
        'sent_at renseigne'
    );

    checkReplyRepository(
        $sentEmail !== false
        && $sentEmail['message_id']
            === $reservedMessageId,
        'Message-ID conserve apres envoi'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT status
            FROM prospects
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $prospectId,
    ]);

    checkReplyRepository(
        $statement->fetchColumn()
            === 'replied',
        'Prospect reste replied apres envoi'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                previous_status,
                new_status
            FROM prospect_events
            WHERE prospect_id = :prospect_id
            AND event_type =
                :event_type
            ORDER BY id DESC
            LIMIT 1
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'event_type' =>
            'prospect_reply_email_sent',
    ]);

    $sentEvent =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $sentEvent !== false,
        'Evenement reply sent cree'
    );

    checkReplyRepository(
        $sentEvent !== false
        && $sentEvent['previous_status']
            === 'replied'
        && $sentEvent['new_status']
            === 'replied',
        'Envoi ne modifie pas le statut prospect'
    );

    /*
    |--------------------------------------------------------------------------
    | Fixture retry
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_replies
            (
                prospect_id,
                prospect_email_id,
                sender_email,
                subject,
                body_text,
                message_id,
                in_reply_to,
                classification,
                received_at,
                created_at
            )
            VALUES
            (
                :prospect_id,
                :prospect_email_id,
                :sender_email,
                :subject,
                :body_text,
                :message_id,
                :in_reply_to,
                :classification,
                NOW(),
                NOW()
            )
            '
        );

    $retryReplyMessageId =
        '<reply-retry-'
        . $unique
        . '@example.test>';

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $sourceEmailId,

        'sender_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Pouvez-vous me donner plus de details ?',

        'message_id' =>
            $retryReplyMessageId,

        'in_reply_to' =>
            $sourceMessageId,

        'classification' =>
            'question',
    ]);

    $retryReplyId =
        (int) $pdo->lastInsertId();

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails
            (
                prospect_id,
                source_reply_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                approved_at,
                scheduled_at,
                created_at,
                updated_at
            )
            VALUES
            (
                :prospect_id,
                :source_reply_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :approved_at,
                :scheduled_at,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'source_reply_id' =>
            $retryReplyId,

        'email_type' =>
            'manual',

        'recipient_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Voici quelques precisions.',

        'status' =>
            'scheduled',

        'approved_at' =>
            '2026-09-10 14:00:00',

        'scheduled_at' =>
            '2026-09-10 15:00:00',
    ]);

    $retryEmailId =
        (int) $pdo->lastInsertId();

    $retryReservedMessageId =
        '<prospection-reply-retry-'
        . $unique
        . '@olcreativestudio.fr>';

    $repository
        ->claimScheduledReplyEmail(
            $retryEmailId,
            $retryReservedMessageId,
            $now,
            3
        );

    $retryResult =
        $repository
            ->markReplyEmailFailed(
                $retryEmailId,
                'SMTP temporairement indisponible',
                3,
                30
            );

    checkReplyRepository(
        $retryResult['status']
            === 'scheduled'
        && $retryResult['retry_scheduled']
            === true,
        'Echec temporaire programme un retry'
    );

    checkReplyRepository(
        !empty(
            $retryResult['retry_at']
        ),
        'Date de retry renseignee'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                scheduled_at,
                send_attempts,
                error_message
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $retryEmailId,
    ]);

    $retryStored =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $retryStored !== false
        && $retryStored['status']
            === 'scheduled',
        'Retry persiste en scheduled'
    );

    checkReplyRepository(
        $retryStored !== false
        && (int) $retryStored[
            'send_attempts'
        ] === 1,
        'Retry conserve le compteur de tentative'
    );

    checkReplyRepository(
        $retryStored !== false
        && str_contains(
            (string) $retryStored[
                'error_message'
            ],
            'SMTP'
        ),
        'Erreur SMTP persistee'
    );

    /*
    |--------------------------------------------------------------------------
    | Echec definitif
    |--------------------------------------------------------------------------
    */

    $pdo->prepare(
        '
        UPDATE prospect_emails
        SET
            status = :status,
            scheduled_at = :scheduled_at,
            send_attempts = :send_attempts,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
        '
    )->execute([
        'status' =>
            'scheduled',

        'scheduled_at' =>
            '2026-09-10 15:00:00',

        'send_attempts' =>
            2,

        'id' =>
            $retryEmailId,
    ]);

    $repository
        ->claimScheduledReplyEmail(
            $retryEmailId,
            '<prospection-reply-final-'
                . $unique
                . '@olcreativestudio.fr>',
            $now,
            3
        );

    $failedResult =
        $repository
            ->markReplyEmailFailed(
                $retryEmailId,
                'SMTP definitivement indisponible',
                3,
                30
            );

    checkReplyRepository(
        $failedResult['status']
            === 'failed'
        && $failedResult[
            'retry_scheduled'
        ] === false,
        'Troisieme echec devient definitif'
    );

    checkReplyRepository(
        (int) $failedResult[
            'send_attempts'
        ] === 3,
        'Echec definitif conserve trois tentatives'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                error_message
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $retryEmailId,
    ]);

    $failedStored =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $failedStored !== false
        && $failedStored['status']
            === 'failed',
        'Echec definitif persiste'
    );

    /*
    |--------------------------------------------------------------------------
    | Fixture reconciliation
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_replies
            (
                prospect_id,
                prospect_email_id,
                sender_email,
                subject,
                body_text,
                message_id,
                in_reply_to,
                classification,
                received_at,
                created_at
            )
            VALUES
            (
                :prospect_id,
                :prospect_email_id,
                :sender_email,
                :subject,
                :body_text,
                :message_id,
                :in_reply_to,
                :classification,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $sourceEmailId,

        'sender_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Je suis disponible pour en discuter.',

        'message_id' =>
            '<reply-stale-'
            . $unique
            . '@example.test>',

        'in_reply_to' =>
            $sourceMessageId,

        'classification' =>
            'positive',
    ]);

    $staleReplyId =
        (int) $pdo->lastInsertId();

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails
            (
                prospect_id,
                source_reply_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                approved_at,
                scheduled_at,
                message_id,
                send_attempts,
                created_at,
                updated_at
            )
            VALUES
            (
                :prospect_id,
                :source_reply_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :approved_at,
                :scheduled_at,
                :message_id,
                :send_attempts,
                NOW(),
                :updated_at
            )
            '
        );

    $staleMessageId =
        '<prospection-reply-stale-'
        . $unique
        . '@olcreativestudio.fr>';

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'source_reply_id' =>
            $staleReplyId,

        'email_type' =>
            'manual',

        'recipient_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Merci pour votre retour.',

        'status' =>
            'sending',

        'approved_at' =>
            '2026-09-10 14:00:00',

        'scheduled_at' =>
            '2026-09-10 15:00:00',

        'message_id' =>
            $staleMessageId,

        'send_attempts' =>
            1,

        'updated_at' =>
            '2026-09-10 15:00:00',
    ]);

    $staleEmailId =
        (int) $pdo->lastInsertId();

    $stale =
        $repository
            ->findStaleSendingReplyEmails(
                $now,
                15
            );

    $staleFound = false;

    foreach ($stale as $row) {
        if (
            (int) $row['email_id']
            === $staleEmailId
        ) {
            $staleFound = true;
            break;
        }
    }

    checkReplyRepository(
        $staleFound,
        'Sending stale detecte'
    );

    $reconciliation =
        $repository
            ->markStaleReplyEmailForReconciliation(
                $staleEmailId,
                $now,
                15
            );

    checkReplyRepository(
        $reconciliation['status']
            === 'reconciliation_required',
        'Sending stale mis en reconciliation'
    );

    checkReplyRepository(
        $reconciliation[
            'prospect_status'
        ] === 'replied',
        'Reconciliation conserve prospect replied'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                sent_at,
                error_message
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $staleEmailId,
    ]);

    $reconciliationStored =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $reconciliationStored !== false
        && $reconciliationStored['status']
            === 'reconciliation_required',
        'Reconciliation persistee'
    );

    checkReplyRepository(
        $reconciliationStored !== false
        && empty(
            $reconciliationStored[
                'sent_at'
            ]
        ),
        'Reconciliation ne marque pas sent'
    );

    checkReplyRepository(
        $reconciliationStored !== false
        && !empty(
            $reconciliationStored[
                'error_message'
            ]
        ),
        'Motif de reconciliation persiste'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                event_type,
                previous_status,
                new_status
            FROM prospect_events
            WHERE prospect_id = :prospect_id
            AND event_type = :event_type
            ORDER BY id DESC
            LIMIT 1
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'event_type' =>
            'prospect_reply_email_reconciliation_required',
    ]);

    $reconciliationEvent =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkReplyRepository(
        $reconciliationEvent !== false
        && $reconciliationEvent[
            'previous_status'
        ] === 'sending'
        && $reconciliationEvent[
            'new_status'
        ] === 'reconciliation_required',
        'Evenement reconciliation cree'
    );

    /*
    |--------------------------------------------------------------------------
    | Envoi recent : reconciliation interdite
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_replies
            (
                prospect_id,
                prospect_email_id,
                sender_email,
                subject,
                body_text,
                message_id,
                in_reply_to,
                classification,
                received_at,
                created_at
            )
            VALUES
            (
                :prospect_id,
                :prospect_email_id,
                :sender_email,
                :subject,
                :body_text,
                :message_id,
                :in_reply_to,
                :classification,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $sourceEmailId,

        'sender_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Message recent.',

        'message_id' =>
            '<reply-recent-'
            . $unique
            . '@example.test>',

        'in_reply_to' =>
            $sourceMessageId,

        'classification' =>
            'positive',
    ]);

    $recentReplyId =
        (int) $pdo->lastInsertId();

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails
            (
                prospect_id,
                source_reply_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                approved_at,
                scheduled_at,
                message_id,
                send_attempts,
                created_at,
                updated_at
            )
            VALUES
            (
                :prospect_id,
                :source_reply_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :approved_at,
                :scheduled_at,
                :message_id,
                :send_attempts,
                NOW(),
                :updated_at
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'source_reply_id' =>
            $recentReplyId,

        'email_type' =>
            'manual',

        'recipient_email' =>
            $emailAddress,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Reponse recente.',

        'status' =>
            'sending',

        'approved_at' =>
            '2026-09-10 14:00:00',

        'scheduled_at' =>
            '2026-09-10 15:00:00',

        'message_id' =>
            '<prospection-reply-recent-'
            . $unique
            . '@olcreativestudio.fr>',

        'send_attempts' =>
            1,

        'updated_at' =>
            '2026-09-10 15:55:00',
    ]);

    $recentEmailId =
        (int) $pdo->lastInsertId();

    expectReplyRepositoryException(
        fn () =>
            $repository
                ->markStaleReplyEmailForReconciliation(
                    $recentEmailId,
                    $now,
                    15
                ),
        'Sending recent refuse en reconciliation'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT status
            FROM prospects
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $prospectId,
    ]);

    checkReplyRepository(
        $statement->fetchColumn()
            === 'replied',
        'Prospect reste replied apres tous les scenarios'
    );

    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    $pdo->prepare(
        '
        DELETE FROM prospect_events
        WHERE prospect_id = ?
        '
    )->execute([
        $prospectId,
    ]);

    $pdo->prepare(
        '
        UPDATE prospect_emails
        SET source_reply_id = NULL
        WHERE prospect_id = ?
        '
    )->execute([
        $prospectId,
    ]);

    $pdo->prepare(
        '
        DELETE FROM prospect_replies
        WHERE prospect_id = ?
        '
    )->execute([
        $prospectId,
    ]);

    $pdo->prepare(
        '
        DELETE FROM prospect_emails
        WHERE prospect_id = ?
        '
    )->execute([
        $prospectId,
    ]);

    $pdo->prepare(
        '
        DELETE FROM prospects
        WHERE id = ?
        '
    )->execute([
        $prospectId,
    ]);

} catch (Throwable $exception) {
    if ($prospectId > 0) {
        try {
            $pdo->prepare(
                '
                DELETE FROM prospect_unsubscribes
                WHERE prospect_id = ?
                '
            )->execute([
                $prospectId,
            ]);

            $pdo->prepare(
                '
                DELETE FROM prospect_events
                WHERE prospect_id = ?
                '
            )->execute([
                $prospectId,
            ]);

            $pdo->prepare(
                '
                UPDATE prospect_emails
                SET source_reply_id = NULL
                WHERE prospect_id = ?
                '
            )->execute([
                $prospectId,
            ]);

            $pdo->prepare(
                '
                DELETE FROM prospect_replies
                WHERE prospect_id = ?
                '
            )->execute([
                $prospectId,
            ]);

            $pdo->prepare(
                '
                DELETE FROM prospect_emails
                WHERE prospect_id = ?
                '
            )->execute([
                $prospectId,
            ]);

            $pdo->prepare(
                '
                DELETE FROM prospects
                WHERE id = ?
                '
            )->execute([
                $prospectId,
            ]);
        } catch (Throwable) {
            // Ne pas masquer l erreur originale.
        }
    }

    throw $exception;
}

echo PHP_EOL;

echo 'Tests reussis : '
    . $passed
    . PHP_EOL;

echo 'Tests echoues : '
    . $failed
    . PHP_EOL;

echo PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo 'Prospect Reply Repository : OK'
    . PHP_EOL;
