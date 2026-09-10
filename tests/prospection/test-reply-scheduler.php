<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ReplyScheduler.php';

echo '=== TEST REPLY SCHEDULER ==='
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;

function check(
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

function expectException(
    callable $callback,
    string $label
): void {
    try {
        $callback();

        check(
            false,
            $label
        );
    } catch (Throwable) {
        check(
            true,
            $label
        );
    }
}


try {
    /*
    |--------------------------------------------------------------------------
    | Fixture prospect
    |--------------------------------------------------------------------------
    */

    $unique =
        bin2hex(
            random_bytes(6)
        );

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

    $emailAddress =
        'reply-scheduler-'
        . $unique
        . '@example.test';

    $statement->execute([
        'company_name' =>
            'Reply Scheduler Test',

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
            'reply_scheduler_test',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Email source envoye
    |--------------------------------------------------------------------------
    */

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
            'Message initial de test.',

        'status' =>
            'sent',

        'message_id' =>
            '<reply-scheduler-outbound-'
            . $unique
            . '@example.test>',
    ]);

    $sourceEmailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Reponse entrante positive
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
            'Bonjour, je suis interesse.',

        'message_id' =>
            '<reply-scheduler-inbound-'
            . $unique
            . '@example.test>',

        'in_reply_to' =>
            '<reply-scheduler-outbound-'
            . $unique
            . '@example.test>',

        'classification' =>
            'positive',
    ]);

    $replyId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Brouillon approuve
    |--------------------------------------------------------------------------
    */

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
                NOW(),
                NOW()
            )
            '
        );

    $approvedAt =
        new DateTimeImmutable(
            '2026-09-10 10:00:00',
            new DateTimeZone(
                'Europe/Paris'
            )
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
            'approved',

        'approved_at' =>
            $approvedAt->format(
                'Y-m-d H:i:s'
            ),
    ]);

    $emailId =
        (int) $pdo->lastInsertId();

    $scheduler =
        new ReplyScheduler(
            $pdo
        );

    $scheduledAt =
        new DateTimeImmutable(
            '2026-09-10 11:00:00',
            new DateTimeZone(
                'Europe/Paris'
            )
        );

    /*
    |--------------------------------------------------------------------------
    | Programmation nominale
    |--------------------------------------------------------------------------
    */

    $result =
        $scheduler->schedule(
            $emailId,
            $scheduledAt
        );

    check(
        $result['status']
            === 'scheduled',
        'Statut scheduled retourne'
    );

    check(
        $result['email_id']
            === $emailId,
        'Email ID correct'
    );

    check(
        $result['prospect_id']
            === $prospectId,
        'Prospect ID correct'
    );

    check(
        $result['reply_id']
            === $replyId,
        'Reply ID correct'
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
                approved_at,
                scheduled_at,
                sent_at,
                message_id,
                send_attempts
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

    check(
        $stored['status']
            === 'scheduled',
        'Email passe en scheduled'
    );

    check(
        !empty(
            $stored['approved_at']
        ),
        'approved_at conserve'
    );

    check(
        $stored['scheduled_at']
            === '2026-09-10 11:00:00',
        'scheduled_at renseigne'
    );

    check(
        empty(
            $stored['sent_at']
        ),
        'sent_at reste NULL'
    );

    check(
        empty(
            $stored['message_id']
        ),
        'message_id reste NULL'
    );

    check(
        (int) $stored[
            'send_attempts'
        ] === 0,
        'Aucune tentative d envoi'
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

    check(
        $statement->fetchColumn()
            === 'replied',
        'Prospect reste replied'
    );

    /*
    |--------------------------------------------------------------------------
    | Evenement
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            SELECT
                event_type,
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
            'prospect_reply_scheduled',
    ]);

    $event =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    check(
        $event !== false,
        'Evenement de programmation cree'
    );

    if ($event !== false) {
        $metadata =
            json_decode(
                (string) $event[
                    'metadata'
                ],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        check(
            (int) $metadata[
                'reply_id'
            ] === $replyId,
            'Event reply_id correct'
        );

        check(
            (int) $metadata[
                'prospect_email_id'
            ] === $emailId,
            'Event email_id correct'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Double programmation refusee
    |--------------------------------------------------------------------------
    */

    expectException(
        fn () =>
            $scheduler->schedule(
                $emailId,
                $scheduledAt
            ),
        'Double programmation refusee'
    );

    /*
    |--------------------------------------------------------------------------
    | Date avant approbation refusee
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            UPDATE prospect_emails
            SET
                status = :status,
                scheduled_at = NULL
            WHERE id = :id
            '
        );

    $statement->execute([
        'status' =>
            'approved',

        'id' =>
            $emailId,
    ]);

    $beforeApproval =
        $approvedAt
            ->modify('-1 minute');

    expectException(
        fn () =>
            $scheduler->schedule(
                $emailId,
                $beforeApproval
            ),
        'Programmation avant validation refusee'
    );

        /*
        |--------------------------------------------------------------------------
        | Destinataire different de l expediteur refuse
        |--------------------------------------------------------------------------
        */

        $differentEmail =
            'reply-scheduler-other-'
            . $unique
            . '@example.test';

        $statement =
            $pdo->prepare(
                '
                UPDATE prospect_emails
                SET recipient_email = :recipient_email
                WHERE id = :id
                '
            );

        $statement->execute([
            'recipient_email' =>
                $differentEmail,

            'id' =>
                $emailId,
        ]);

        expectException(
            fn () =>
                $scheduler->schedule(
                    $emailId,
                    $scheduledAt
                ),
            'Destinataire different de la reponse source refuse'
        );

        /*
        | Remise en etat du destinataire.
        */

        $statement->execute([
            'recipient_email' =>
                $emailAddress,

            'id' =>
                $emailId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Adresse desinscrite refusee
        |--------------------------------------------------------------------------
        */

        $statement =
            $pdo->prepare(
                '
                INSERT INTO prospect_unsubscribes
                (
                    prospect_id,
                    email,
                    reason,
                    source,
                    unsubscribed_at,
                    created_at
                )
                VALUES
                (
                    :prospect_id,
                    :email,
                    :reason,
                    :source,
                    NOW(),
                    NOW()
                )
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,

            'email' =>
                $emailAddress,

            'reason' =>
                'Test ReplyScheduler',

            'source' =>
                'reply',
        ]);

        expectException(
            fn () =>
                $scheduler->schedule(
                    $emailId,
                    $scheduledAt
                ),
            'Adresse desinscrite refusee'
        );

        /*
        | Suppression de la desinscription de test
        | pour poursuivre les autres scenarios.
        */

        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospect_unsubscribes
                WHERE email = :email
                '
            );

        $statement->execute([
            'email' =>
                $emailAddress,
        ]);

    /*
    |--------------------------------------------------------------------------
    | Classification refusee
    |--------------------------------------------------------------------------
    */

    $statement =
        $pdo->prepare(
            '
            UPDATE prospect_replies
            SET classification = :classification
            WHERE id = :id
            '
        );

    $statement->execute([
        'classification' =>
            'negative',

        'id' =>
            $replyId,
    ]);

    expectException(
        fn () =>
            $scheduler->schedule(
                $emailId,
                $scheduledAt
            ),
        'Classification negative refusee'
    );

    /*
    |--------------------------------------------------------------------------
    | Prospect plus en replied
    |--------------------------------------------------------------------------
    */

    $statement->execute([
        'classification' =>
            'positive',

        'id' =>
            $replyId,
    ]);

    $statement =
        $pdo->prepare(
            '
            UPDATE prospects
            SET status = :status
            WHERE id = :id
            '
        );

    $statement->execute([
        'status' =>
            'converted',

        'id' =>
            $prospectId,
    ]);

    expectException(
        fn () =>
            $scheduler->schedule(
                $emailId,
                $scheduledAt
            ),
        'Prospect converted refuse'
    );

        /*
        |--------------------------------------------------------------------------
        | Nettoyage
        |--------------------------------------------------------------------------
        */

        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospect_unsubscribes
                WHERE prospect_id = :prospect_id
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospect_events
                WHERE prospect_id = :prospect_id
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        | On casse d abord la FK source_reply_id.
        */

        $statement =
            $pdo->prepare(
                '
                UPDATE prospect_emails
                SET source_reply_id = NULL
                WHERE prospect_id = :prospect_id
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        | Puis suppression des reponses entrantes.
        */

        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospect_replies
                WHERE prospect_id = :prospect_id
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        | Puis des e-mails.
        */

        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospect_emails
                WHERE prospect_id = :prospect_id
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        | Enfin le prospect.
        */

        $statement =
            $pdo->prepare(
                '
                DELETE FROM prospects
                WHERE id = :prospect_id
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);
    } catch (Throwable $exception) {
        /*
        |--------------------------------------------------------------------------
        | Nettoyage de secours
        |--------------------------------------------------------------------------
        |
        | Ne doit jamais masquer l erreur originale du test.
        |
        */

        if (
            isset($prospectId)
            && $prospectId > 0
        ) {
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
                // Ne pas masquer l erreur initiale.
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

echo 'Reply Scheduler : OK'
    . PHP_EOL;
