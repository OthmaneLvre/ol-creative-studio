<?php

declare(strict_types=1);

require __DIR__ . '/../../php/db.php';
require __DIR__ . '/../../php/prospection/UnsubscribeManager.php';

$manager = new UnsubscribeManager($pdo);

$prospectId = null;
$initialEmailId = null;
$followUpId = null;
$replyId = null;

$testEmail = sprintf(
    'unsubscribe-test-%s@example.test',
    bin2hex(random_bytes(6))
);

$now = (new DateTimeImmutable())
    ->format('Y-m-d H:i:s');

echo "========================================\n";
echo "TEST UNSUBSCRIBE - PERSISTENCE\n";
echo "========================================\n\n";

try {
    /*
     * 1. Prospect déjà contacté ayant répondu.
     */
    $statement = $pdo->prepare(
        '
        INSERT INTO prospects (
            company_name,
            email,
            email_source_url,
            email_type,
            source,
            status,
            contacted_at,
            replied_at
        ) VALUES (
            :company_name,
            :email,
            :email_source_url,
            :email_type,
            :source,
            :status,
            :contacted_at,
            :replied_at
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'TEST UNSUBSCRIBE OL CREATIVE STUDIO',
        'email' => $testEmail,
        'email_source_url' =>
            'https://example.test/contact',
        'email_type' => 'generic',
        'source' => 'unsubscribe_test',
        'status' => 'replied',
        'contacted_at' => $now,
        'replied_at' => $now,
    ]);

    $prospectId = (int) $pdo->lastInsertId();

    /*
     * 2. Premier e-mail déjà envoyé.
     */
    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            email_type,
            recipient_email,
            subject,
            body_text,
            status,
            sent_at,
            message_id
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_text,
            :status,
            :sent_at,
            :message_id
        )
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'email_type' => 'initial',
        'recipient_email' => $testEmail,
        'subject' => 'Test désinscription',
        'body_text' => 'Message test',
        'status' => 'sent',
        'sent_at' => $now,
        'message_id' =>
            '<unsubscribe-initial-'
            . bin2hex(random_bytes(8))
            . '@example.test>',
    ]);

    $initialEmailId = (int) $pdo->lastInsertId();

    /*
     * 3. Relance future qui devra être annulée.
     */
    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            email_type,
            recipient_email,
            subject,
            body_text,
            status,
            scheduled_at
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_text,
            :status,
            :scheduled_at
        )
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'email_type' => 'follow_up_1',
        'recipient_email' => $testEmail,
        'subject' => 'Relance test',
        'body_text' => 'Relance test',
        'status' => 'scheduled',
        'scheduled_at' =>
            (new DateTimeImmutable('+4 days'))
                ->format('Y-m-d H:i:s'),
    ]);

    $followUpId = (int) $pdo->lastInsertId();

    /*
     * 4. Réponse contenant la demande STOP.
     */
    $replyMessageId =
        '<unsubscribe-reply-'
        . bin2hex(random_bytes(8))
        . '@example.test>';

    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_replies (
            prospect_id,
            prospect_email_id,
            sender_email,
            subject,
            body_text,
            message_id,
            in_reply_to,
            classification,
            received_at
        ) VALUES (
            :prospect_id,
            :prospect_email_id,
            :sender_email,
            :subject,
            :body_text,
            :message_id,
            :in_reply_to,
            :classification,
            :received_at
        )
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'prospect_email_id' => $initialEmailId,
        'sender_email' => $testEmail,
        'subject' => 'Re: Test désinscription',
        'body_text' => 'STOP',
        'message_id' => $replyMessageId,
        'in_reply_to' => null,
        'classification' => 'unknown',
        'received_at' => $now,
    ]);

    $replyId = (int) $pdo->lastInsertId();

    echo "Prospect : #{$prospectId}\n";
    echo "Initial : #{$initialEmailId}\n";
    echo "Follow-up : #{$followUpId}\n";
    echo "Reply : #{$replyId}\n\n";

    /*
     * 5. Première désinscription.
     */
    $first = $manager->unsubscribe(
        $prospectId,
        $replyId,
        $testEmail,
        'STOP',
        $now
    );

    echo "Première exécution :\n";
    print_r($first);

    /*
     * Vérifications BDD.
     */
    $statement = $pdo->prepare(
        '
        SELECT status
        FROM prospects
        WHERE id = :id
        '
    );

    $statement->execute(['id' => $prospectId]);

    $prospectStatus = $statement->fetchColumn();

    $statement = $pdo->prepare(
        '
        SELECT classification
        FROM prospect_replies
        WHERE id = :id
        '
    );

    $statement->execute(['id' => $replyId]);

    $classification = $statement->fetchColumn();

    $statement = $pdo->prepare(
        '
        SELECT status
        FROM prospect_emails
        WHERE id = :id
        '
    );

    $statement->execute(['id' => $followUpId]);

    $followUpStatus = $statement->fetchColumn();

    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_unsubscribes
        WHERE LOWER(email) = LOWER(:email)
        '
    );

    $statement->execute(['email' => $testEmail]);

    $unsubscribeCount =
        (int) $statement->fetchColumn();

    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'event_type' => 'prospect_unsubscribed',
    ]);

    $eventCount =
        (int) $statement->fetchColumn();

    $checks = [
        'Prospect unsubscribed' =>
            $prospectStatus === 'unsubscribed',

        'Reply classified unsubscribed' =>
            $classification === 'unsubscribed',

        'Follow-up cancelled' =>
            $followUpStatus === 'cancelled',

        'Blacklist créée une seule fois' =>
            $unsubscribeCount === 1,

        'Événement créé une seule fois' =>
            $eventCount === 1,

        'Manager indique created=true' =>
            ($first['created'] ?? null) === true,

        'Manager indique 1 email annulé' =>
            ($first['cancelled_emails'] ?? null) === 1,
    ];

    foreach ($checks as $label => $passed) {
        echo sprintf(
            "[%s] %s\n",
            $passed ? 'OK' : 'FAIL',
            $label
        );

        if (!$passed) {
            throw new RuntimeException(
                'Échec vérification : ' . $label
            );
        }
    }

    /*
     * 6. Deuxième exécution :
     * aucune nouvelle blacklist,
     * aucun nouvel événement.
     */
    echo "\nDeuxième exécution (idempotence) :\n";

    $second = $manager->unsubscribe(
        $prospectId,
        $replyId,
        $testEmail,
        'STOP',
        $now
    );

    print_r($second);

    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_unsubscribes
        WHERE LOWER(email) = LOWER(:email)
        '
    );

    $statement->execute(['email' => $testEmail]);

    $unsubscribeCountAfter =
        (int) $statement->fetchColumn();

    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'event_type' => 'prospect_unsubscribed',
    ]);

    $eventCountAfter =
        (int) $statement->fetchColumn();

    $idempotenceChecks = [
        'created=false au second passage' =>
            ($second['created'] ?? null) === false,

        'Blacklist toujours unique' =>
            $unsubscribeCountAfter === 1,

        'Événement toujours unique' =>
            $eventCountAfter === 1,

        'Aucun nouvel email annulé' =>
            ($second['cancelled_emails'] ?? null) === 0,
    ];

    foreach ($idempotenceChecks as $label => $passed) {
        echo sprintf(
            "[%s] %s\n",
            $passed ? 'OK' : 'FAIL',
            $label
        );

        if (!$passed) {
            throw new RuntimeException(
                'Échec idempotence : ' . $label
            );
        }
    }

    echo "\n========================================\n";
    echo "TEST UNSUBSCRIBE : OK\n";
    echo "========================================\n";
} finally {
    if ($prospectId !== null) {
        try {
            $pdo->beginTransaction();

            $statement = $pdo->prepare(
                '
                DELETE FROM prospect_unsubscribes
                WHERE prospect_id = :prospect_id
                '
            );

            $statement->execute([
                'prospect_id' => $prospectId,
            ]);

            $statement = $pdo->prepare(
                '
                DELETE FROM prospect_events
                WHERE prospect_id = :prospect_id
                '
            );

            $statement->execute([
                'prospect_id' => $prospectId,
            ]);

            $statement = $pdo->prepare(
                '
                DELETE FROM prospect_replies
                WHERE prospect_id = :prospect_id
                '
            );

            $statement->execute([
                'prospect_id' => $prospectId,
            ]);

            $statement = $pdo->prepare(
                '
                DELETE FROM prospect_emails
                WHERE prospect_id = :prospect_id
                '
            );

            $statement->execute([
                'prospect_id' => $prospectId,
            ]);

            $statement = $pdo->prepare(
                '
                DELETE FROM prospects
                WHERE id = :prospect_id
                '
            );

            $statement->execute([
                'prospect_id' => $prospectId,
            ]);

            $pdo->commit();

            echo "\nCleanup : OK\n";
        } catch (Throwable $cleanupException) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            fwrite(
                STDERR,
                "\nERREUR CLEANUP : "
                . $cleanupException->getMessage()
                . "\n"
            );
        }
    }
}
