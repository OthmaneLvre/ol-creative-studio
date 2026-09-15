<?php

declare(strict_types=1);

require __DIR__ . '/../../php/db.php';
require __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$repository =
    new ProspectRepository($pdo);

$passed = 0;
$failed = 0;

$prospectIds = [];
$unsubscribeEmails = [];

function checkFollowUpRepository(
    bool $condition,
    string $message
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;

        echo '[OK]   '
            . $message
            . PHP_EOL;

        return;
    }

    $failed++;

    echo '[FAIL] '
        . $message
        . PHP_EOL;
}

function expectFollowUpRepositoryException(
    callable $callback,
    string $message
): void {
    try {
        $callback();

        checkFollowUpRepository(
            false,
            $message
        );
    } catch (Throwable) {
        checkFollowUpRepository(
            true,
            $message
        );
    }
}

function followUpTestEmail(
    string $suffix
): string {
    return
        'follow-up-repository-'
        . $suffix
        . '-'
        . bin2hex(random_bytes(4))
        . '@example.test';
}

function createFollowUpRepositoryProspect(
    PDO $pdo,
    string $email,
    string $status = 'follow_up'
): int {
    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospects (
                company_name,
                activity,
                city,
                postal_code,
                email,
                email_source_url,
                email_type,
                status
            ) VALUES (
                :company_name,
                :activity,
                :city,
                :postal_code,
                :email,
                :email_source_url,
                :email_type,
                :status
            )
            '
        );

    $statement->execute([
        'company_name' =>
            'Test Follow Up Repository',

        'activity' =>
            'Plomberie',

        'city' =>
            'Ceret',

        'postal_code' =>
            '66400',

        'email' =>
            $email,

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'status' =>
            $status,
    ]);

    return (int) $pdo->lastInsertId();
}

function createFollowUpRepositoryInitial(
    PDO $pdo,
    int $prospectId,
    string $email,
    string $unique
): int {
    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails (
                prospect_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                scheduled_at,
                sent_at,
                message_id,
                created_at,
                updated_at
            ) VALUES (
                :prospect_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :scheduled_at,
                :sent_at,
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
            $email,

        'subject' =>
            'Votre presence en ligne',

        'body_text' =>
            'Premier message de test.',

        'status' =>
            'sent',

        'scheduled_at' =>
            '2026-09-01 09:30:00',

        'sent_at' =>
            '2026-09-01 09:30:00',

        'message_id' =>
            '<initial-'
            . $unique
            . '@example.test>',
    ]);

    return (int) $pdo->lastInsertId();
}

function createFollowUpRepositoryEmail(
    PDO $pdo,
    int $prospectId,
    string $email,
    string $type,
    string $status,
    string $scheduledAt,
    int $sendAttempts = 0,
    ?string $sentAt = null,
    ?string $messageId = null
): int {
    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails (
                prospect_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                scheduled_at,
                sent_at,
                message_id,
                send_attempts,
                created_at,
                updated_at
            ) VALUES (
                :prospect_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :scheduled_at,
                :sent_at,
                :message_id,
                :send_attempts,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            $type,

        'recipient_email' =>
            $email,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Relance repository test.',

        'status' =>
            $status,

        'scheduled_at' =>
            $scheduledAt,

        'sent_at' =>
            $sentAt,

        'message_id' =>
            $messageId,

        'send_attempts' =>
            $sendAttempts,
    ]);

    return (int) $pdo->lastInsertId();
}

function cleanupFollowUpRepository(
    PDO $pdo,
    array $prospectIds,
    array $unsubscribeEmails
): void {
    foreach (
        array_unique($unsubscribeEmails)
        as $email
    ) {
        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospect_unsubscribes
                WHERE LOWER(email) =
                    LOWER(:email)
                '
            );

        $statement->execute([
            'email' => $email,
        ]);
    }

    foreach (
        array_reverse(
            array_unique($prospectIds)
        )
        as $prospectId
    ) {
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
    }
}

$now =
    new DateTimeImmutable(
        '2026-09-15 12:00:00'
    );

echo '=== TEST FOLLOW UP EMAIL REPOSITORY ==='
    . PHP_EOL
    . PHP_EOL;

try {
    /*
    |--------------------------------------------------------------------------
    | Cycle nominal FU1
    |--------------------------------------------------------------------------
    */

    $unique =
        bin2hex(random_bytes(6));

    $email =
        followUpTestEmail('nominal');

    $prospectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $email
        );

    $prospectIds[] =
        $prospectId;

    createFollowUpRepositoryInitial(
        $pdo,
        $prospectId,
        $email,
        $unique
    );

    $followUpId =
        createFollowUpRepositoryEmail(
            $pdo,
            $prospectId,
            $email,
            'follow_up_1',
            'scheduled',
            '2026-09-10 09:30:00'
        );

    $due =
        $repository
            ->findDueScheduledFollowUpEmails(
                $now,
                100
            );

    $dueRow = null;

    foreach ($due as $row) {
        if (
            (int) $row['email_id']
            === $followUpId
        ) {
            $dueRow = $row;
            break;
        }
    }

    checkFollowUpRepository(
        $dueRow !== null,
        'FU1 due detectee'
    );

    checkFollowUpRepository(
        $dueRow !== null
        && !empty(
            $dueRow[
                'initial_message_id'
            ]
        ),
        'Message-ID initial disponible'
    );

    $reservedMessageId =
        '<follow-up-claim-'
        . $unique
        . '@olcreativestudio.fr>';

    $claimed =
        $repository
            ->claimScheduledFollowUpEmail(
                $followUpId,
                $reservedMessageId,
                $now,
                3
            );

    checkFollowUpRepository(
        $claimed['status']
            === 'sending',
        'FU1 claim passe en sending'
    );

    checkFollowUpRepository(
        (int) $claimed[
            'send_attempts'
        ] === 1,
        'Claim incremente send_attempts'
    );

    checkFollowUpRepository(
        $claimed['message_id']
            === $reservedMessageId,
        'Claim reserve le Message-ID'
    );

    expectFollowUpRepositoryException(
        fn () =>
            $repository
                ->claimScheduledFollowUpEmail(
                    $followUpId,
                    '<second-claim@example.test>',
                    $now,
                    3
                ),
        'Double claim refuse'
    );

    expectFollowUpRepositoryException(
        fn () =>
            $repository
                ->markFollowUpEmailSent(
                    $followUpId,
                    '<wrong@example.test>'
                ),
        'Message-ID incorrect refuse'
    );

    $repository
        ->markFollowUpEmailSent(
            $followUpId,
            $reservedMessageId
        );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                sent_at,
                message_id,
                send_attempts
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $followUpId,
    ]);

    $sent =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkFollowUpRepository(
        $sent !== false
        && $sent['status'] === 'sent',
        'FU1 marquee sent'
    );

    checkFollowUpRepository(
        $sent !== false
        && !empty($sent['sent_at']),
        'FU1 sent_at renseigne'
    );

    checkFollowUpRepository(
        $sent !== false
        && $sent['message_id']
            === $reservedMessageId,
        'Message-ID FU1 conserve'
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

    checkFollowUpRepository(
        $statement->fetchColumn()
            === 'follow_up',
        'Prospect reste follow_up'
    );

    /*
    |--------------------------------------------------------------------------
    | Retry temporaire
    |--------------------------------------------------------------------------
    */

    $retryEmail =
        followUpTestEmail('retry');

    $retryProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $retryEmail
        );

    $prospectIds[] =
        $retryProspectId;

    $retryUnique =
        bin2hex(random_bytes(6));

    createFollowUpRepositoryInitial(
        $pdo,
        $retryProspectId,
        $retryEmail,
        $retryUnique
    );

    $retryId =
        createFollowUpRepositoryEmail(
            $pdo,
            $retryProspectId,
            $retryEmail,
            'follow_up_1',
            'scheduled',
            '2026-09-10 09:30:00'
        );

    $repository
        ->claimScheduledFollowUpEmail(
            $retryId,
            '<follow-up-retry-'
                . $retryUnique
                . '@olcreativestudio.fr>',
            $now,
            3
        );

    $retry =
        $repository
            ->markFollowUpEmailFailed(
                $retryId,
                'SMTP temporairement indisponible',
                3,
                30
            );

    checkFollowUpRepository(
        $retry['status']
            === 'scheduled'
        && $retry[
            'retry_scheduled'
        ] === true,
        'Echec temporaire programme un retry'
    );

    checkFollowUpRepository(
        !empty($retry['retry_at']),
        'Date de retry renseignee'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                send_attempts,
                error_message
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $retryId,
    ]);

    $retryStored =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkFollowUpRepository(
        $retryStored !== false
        && $retryStored['status']
            === 'scheduled',
        'Retry persiste en scheduled'
    );

    checkFollowUpRepository(
        $retryStored !== false
        && (int) $retryStored[
            'send_attempts'
        ] === 1,
        'Retry conserve une tentative'
    );

    /*
    |--------------------------------------------------------------------------
    | Echec definitif FU1 + annulation FU2
    |--------------------------------------------------------------------------
    */

    $failedEmail =
        followUpTestEmail('failed');

    $failedProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $failedEmail
        );

    $prospectIds[] =
        $failedProspectId;

    $failedUnique =
        bin2hex(random_bytes(6));

    createFollowUpRepositoryInitial(
        $pdo,
        $failedProspectId,
        $failedEmail,
        $failedUnique
    );

    $failedFu1Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $failedProspectId,
            $failedEmail,
            'follow_up_1',
            'scheduled',
            '2026-09-10 09:30:00',
            2
        );

    $failedFu2Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $failedProspectId,
            $failedEmail,
            'follow_up_2',
            'scheduled',
            '2026-09-14 09:30:00'
        );

    $repository
        ->claimScheduledFollowUpEmail(
            $failedFu1Id,
            '<follow-up-final-'
                . $failedUnique
                . '@olcreativestudio.fr>',
            $now,
            3
        );

    $finalFailure =
        $repository
            ->markFollowUpEmailFailed(
                $failedFu1Id,
                'SMTP definitivement indisponible',
                3,
                30
            );

    checkFollowUpRepository(
        $finalFailure['status']
            === 'failed'
        && $finalFailure[
            'retry_scheduled'
        ] === false,
        'Troisieme echec FU1 devient definitif'
    );

    checkFollowUpRepository(
        (int) $finalFailure[
            'send_attempts'
        ] === 3,
        'Echec definitif conserve trois tentatives'
    );

    checkFollowUpRepository(
        (int) $finalFailure[
            'cancelled_follow_up_2'
        ] === 1,
        'Echec definitif FU1 annule FU2'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT status
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' => $failedFu2Id,
    ]);

    checkFollowUpRepository(
        $statement->fetchColumn()
            === 'cancelled',
        'FU2 persistee cancelled'
    );

    /*
    |--------------------------------------------------------------------------
    | FU2 exige FU1 sent
    |--------------------------------------------------------------------------
    */

    $sequenceEmail =
        followUpTestEmail('sequence');

    $sequenceProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $sequenceEmail
        );

    $prospectIds[] =
        $sequenceProspectId;

    $sequenceUnique =
        bin2hex(random_bytes(6));

    createFollowUpRepositoryInitial(
        $pdo,
        $sequenceProspectId,
        $sequenceEmail,
        $sequenceUnique
    );

    createFollowUpRepositoryEmail(
        $pdo,
        $sequenceProspectId,
        $sequenceEmail,
        'follow_up_1',
        'scheduled',
        '2026-09-08 09:30:00'
    );

    $sequenceFu2Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $sequenceProspectId,
            $sequenceEmail,
            'follow_up_2',
            'scheduled',
            '2026-09-12 09:30:00'
        );

    expectFollowUpRepositoryException(
        fn () =>
            $repository
                ->claimScheduledFollowUpEmail(
                    $sequenceFu2Id,
                    '<fu2-sequence-'
                        . $sequenceUnique
                        . '@olcreativestudio.fr>',
                    $now,
                    3
                ),
        'FU2 refusee tant que FU1 non envoyee'
    );

    /*
    |--------------------------------------------------------------------------
    | STOP : reponse humaine
    |--------------------------------------------------------------------------
    */

    $replyEmail =
        followUpTestEmail('reply-stop');

    $replyProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $replyEmail
        );

    $prospectIds[] =
        $replyProspectId;

    $replyUnique =
        bin2hex(random_bytes(6));

    $replyInitialId =
        createFollowUpRepositoryInitial(
            $pdo,
            $replyProspectId,
            $replyEmail,
            $replyUnique
        );

    $replyFu1Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $replyProspectId,
            $replyEmail,
            'follow_up_1',
            'scheduled',
            '2026-09-10 09:30:00'
        );

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_replies (
                prospect_id,
                prospect_email_id,
                sender_email,
                subject,
                body_text,
                message_id,
                classification,
                received_at,
                created_at
            ) VALUES (
                :prospect_id,
                :prospect_email_id,
                :sender_email,
                :subject,
                :body_text,
                :message_id,
                :classification,
                NOW(),
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $replyProspectId,

        'prospect_email_id' =>
            $replyInitialId,

        'sender_email' =>
            $replyEmail,

        'subject' =>
            'Re: Votre presence en ligne',

        'body_text' =>
            'Merci pour votre message.',

        'message_id' =>
            '<reply-stop-'
            . $replyUnique
            . '@example.test>',

        'classification' =>
            'positive',
    ]);

    expectFollowUpRepositoryException(
        fn () =>
            $repository
                ->claimScheduledFollowUpEmail(
                    $replyFu1Id,
                    '<reply-stop-send-'
                        . $replyUnique
                        . '@olcreativestudio.fr>',
                    $now,
                    3
                ),
        'Reponse humaine bloque le claim'
    );

    /*
    |--------------------------------------------------------------------------
    | STOP : desinscription
    |--------------------------------------------------------------------------
    */

    $unsubscribeEmail =
        followUpTestEmail('unsubscribe');

    $unsubscribeProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $unsubscribeEmail
        );

    $prospectIds[] =
        $unsubscribeProspectId;

    $unsubscribeEmails[] =
        $unsubscribeEmail;

    $unsubscribeUnique =
        bin2hex(random_bytes(6));

    createFollowUpRepositoryInitial(
        $pdo,
        $unsubscribeProspectId,
        $unsubscribeEmail,
        $unsubscribeUnique
    );

    $unsubscribeFu1Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $unsubscribeProspectId,
            $unsubscribeEmail,
            'follow_up_1',
            'scheduled',
            '2026-09-10 09:30:00'
        );

    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_unsubscribes (
                prospect_id,
                email,
                reason,
                source,
                unsubscribed_at
            ) VALUES (
                :prospect_id,
                :email,
                :reason,
                :source,
                NOW()
            )
            '
        );

    $statement->execute([
        'prospect_id' =>
            $unsubscribeProspectId,

        'email' =>
            $unsubscribeEmail,

        'reason' =>
            'Repository test',

        'source' =>
            'manual',
    ]);

    expectFollowUpRepositoryException(
        fn () =>
            $repository
                ->claimScheduledFollowUpEmail(
                    $unsubscribeFu1Id,
                    '<unsubscribe-stop-'
                        . $unsubscribeUnique
                        . '@olcreativestudio.fr>',
                    $now,
                    3
                ),
        'Desinscription bloque le claim'
    );

        /*
    |--------------------------------------------------------------------------
    | Reconciliation : FU1 stale
    |--------------------------------------------------------------------------
    */

    $staleEmail =
        followUpTestEmail('stale');

    $staleProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $staleEmail
        );

    $prospectIds[] =
        $staleProspectId;

    $staleUnique =
        bin2hex(random_bytes(6));

    createFollowUpRepositoryInitial(
        $pdo,
        $staleProspectId,
        $staleEmail,
        $staleUnique
    );

    $staleFu1Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $staleProspectId,
            $staleEmail,
            'follow_up_1',
            'sending',
            '2026-09-10 09:30:00',
            1,
            null,
            '<follow-up-stale-'
                . $staleUnique
                . '@olcreativestudio.fr>'
        );

    $staleFu2Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $staleProspectId,
            $staleEmail,
            'follow_up_2',
            'scheduled',
            '2026-09-15 09:30:00'
        );

    /*
     * createFollowUpRepositoryEmail() utilise NOW()
     * pour updated_at.
     *
     * On force donc FU1 dans le passe pour simuler
     * un processus mort apres le claim.
     */

    $pdo->prepare(
        '
        UPDATE prospect_emails
        SET updated_at = :updated_at
        WHERE id = :id
        '
    )->execute([
        'updated_at' =>
            '2026-09-15 11:00:00',

        'id' =>
            $staleFu1Id,
    ]);

    $staleEmails =
        $repository
            ->findStaleSendingFollowUpEmails(
                $now,
                15,
                100
            );

    $staleFound = false;

    foreach ($staleEmails as $row) {
        if (
            (int) $row['email_id']
            === $staleFu1Id
        ) {
            $staleFound = true;
            break;
        }
    }

    checkFollowUpRepository(
        $staleFound,
        'FU1 sending stale detectee'
    );

    $reconciliation =
        $repository
            ->markStaleFollowUpEmailForReconciliation(
                $staleFu1Id,
                $now,
                15
            );

    checkFollowUpRepository(
        $reconciliation['status']
            === 'reconciliation_required',
        'FU1 stale mise en reconciliation'
    );

    checkFollowUpRepository(
        $reconciliation[
            'prospect_status'
        ] === 'follow_up',
        'Reconciliation conserve prospect follow_up'
    );

    checkFollowUpRepository(
        (int) $reconciliation[
            'cancelled_follow_up_2'
        ] === 1,
        'FU1 stale annule FU2'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                status,
                sent_at,
                message_id,
                error_message
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $staleFu1Id,
    ]);

    $staleStored =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkFollowUpRepository(
        $staleStored !== false
        && $staleStored['status']
            === 'reconciliation_required',
        'Reconciliation FU1 persistee'
    );

    checkFollowUpRepository(
        $staleStored !== false
        && empty(
            $staleStored['sent_at']
        ),
        'Reconciliation ne marque pas FU1 sent'
    );

    checkFollowUpRepository(
        $staleStored !== false
        && !empty(
            $staleStored['message_id']
        ),
        'Message-ID FU1 conserve pour reconciliation'
    );

    checkFollowUpRepository(
        $staleStored !== false
        && !empty(
            $staleStored['error_message']
        ),
        'Motif de reconciliation persiste'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT status
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $staleFu2Id,
    ]);

    checkFollowUpRepository(
        $statement->fetchColumn()
            === 'cancelled',
        'FU2 stale persistee cancelled'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT
                event_type,
                previous_status,
                new_status
            FROM prospect_events
            WHERE prospect_id =
                :prospect_id
            AND event_type =
                :event_type
            ORDER BY id DESC
            LIMIT 1
            '
        );

    $statement->execute([
        'prospect_id' =>
            $staleProspectId,

        'event_type' =>
            'prospect_follow_up_email_reconciliation_required',
    ]);

    $staleEvent =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    checkFollowUpRepository(
        $staleEvent !== false,
        'Evenement reconciliation follow-up cree'
    );

    /*
    |--------------------------------------------------------------------------
    | Reconciliation : sending recent refuse
    |--------------------------------------------------------------------------
    */

    $recentEmail =
        followUpTestEmail('recent');

    $recentProspectId =
        createFollowUpRepositoryProspect(
            $pdo,
            $recentEmail
        );

    $prospectIds[] =
        $recentProspectId;

    $recentUnique =
        bin2hex(random_bytes(6));

    createFollowUpRepositoryInitial(
        $pdo,
        $recentProspectId,
        $recentEmail,
        $recentUnique
    );

    $recentFu1Id =
        createFollowUpRepositoryEmail(
            $pdo,
            $recentProspectId,
            $recentEmail,
            'follow_up_1',
            'sending',
            '2026-09-15 11:55:00',
            1,
            null,
            '<follow-up-recent-'
                . $recentUnique
                . '@olcreativestudio.fr>'
        );

    $pdo->prepare(
        '
        UPDATE prospect_emails
        SET updated_at = :updated_at
        WHERE id = :id
        '
    )->execute([
        'updated_at' =>
            '2026-09-15 11:55:00',

        'id' =>
            $recentFu1Id,
    ]);

    $staleEmails =
        $repository
            ->findStaleSendingFollowUpEmails(
                $now,
                15,
                100
            );

    $recentFound = false;

    foreach ($staleEmails as $row) {
        if (
            (int) $row['email_id']
            === $recentFu1Id
        ) {
            $recentFound = true;
            break;
        }
    }

    checkFollowUpRepository(
        $recentFound === false,
        'FU1 sending recente non detectee stale'
    );

    expectFollowUpRepositoryException(
        fn () =>
            $repository
                ->markStaleFollowUpEmailForReconciliation(
                    $recentFu1Id,
                    $now,
                    15
                ),
        'FU1 sending recente refusee en reconciliation'
    );

    $statement =
        $pdo->prepare(
            '
            SELECT status
            FROM prospect_emails
            WHERE id = :id
            '
        );

    $statement->execute([
        'id' =>
            $recentFu1Id,
    ]);

    checkFollowUpRepository(
        $statement->fetchColumn()
            === 'sending',
        'FU1 recente reste sending'
    );

} finally {
    cleanupFollowUpRepository(
        $pdo,
        $prospectIds,
        $unsubscribeEmails
    );
}

echo PHP_EOL
    . '=== RESULTAT ==='
    . PHP_EOL;

echo 'OK   : '
    . $passed
    . PHP_EOL;

echo 'FAIL : '
    . $failed
    . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo PHP_EOL
    . 'Follow Up Email Repository : OK'
    . PHP_EOL;
