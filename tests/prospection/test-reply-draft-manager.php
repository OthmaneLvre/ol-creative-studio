<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ReplyDraftManager.php';


echo "=== TEST REPLY DRAFT MANAGER ==="
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;


/*
|--------------------------------------------------------------------------
| Assertions
|--------------------------------------------------------------------------
*/

function assertTrue(
    string $name,
    bool $condition
): void {
    global $passed, $failed;

    if ($condition) {
        echo "[OK]   {$name}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "[FAIL] {$name}" . PHP_EOL;
    $failed++;
}


function assertSameValue(
    string $name,
    mixed $expected,
    mixed $actual
): void {
    global $passed, $failed;

    if ($expected === $actual) {
        echo "[OK]   {$name}" . PHP_EOL;
        $passed++;
        return;
    }

    echo "[FAIL] {$name}" . PHP_EOL;
    echo "       attendu : "
        . var_export($expected, true)
        . PHP_EOL;
    echo "       obtenu  : "
        . var_export($actual, true)
        . PHP_EOL;

    $failed++;
}


function assertThrows(
    string $name,
    callable $callback
): void {
    global $passed, $failed;

    try {
        $callback();

        echo "[FAIL] {$name} - aucune exception"
            . PHP_EOL;

        $failed++;
    } catch (Throwable) {
        echo "[OK]   {$name}"
            . PHP_EOL;

        $passed++;
    }
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function createFixture(
    PDO $pdo
): array {
    $token =
        bin2hex(
            random_bytes(6)
        );

    $email =
        "reply-draft-{$token}@example.test";

    $stmt =
        $pdo->prepare(
            "
            INSERT INTO prospects (
                company_name,
                activity,
                city,
                email,
                source,
                source_external_id,
                status
            ) VALUES (
                'Entreprise Test Reply Draft',
                'Plomberie',
                'Céret',
                :email,
                'reply_draft_manager_test',
                :external_id,
                'replied'
            )
            "
        );

    $stmt->execute([
        'email' =>
            $email,

        'external_id' =>
            'reply-draft-' . $token,
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();


    $stmt =
        $pdo->prepare(
            "
            INSERT INTO prospect_emails (
                prospect_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                sent_at
            ) VALUES (
                :prospect_id,
                'initial',
                :email,
                'Message initial',
                'Bonjour, message initial.',
                'sent',
                NOW()
            )
            "
        );

    $stmt->execute([
        'prospect_id' =>
            $prospectId,

        'email' =>
            $email,
    ]);

    $sentEmailId =
        (int) $pdo->lastInsertId();


    $stmt =
        $pdo->prepare(
            "
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
                :email,
                'Re: Message initial',
                'Bonjour, je suis intéressé.',
                :message_id,
                '<reply-draft-outbound@example.test>',
                'positive',
                NOW()
            )
            "
        );

    $messageId =
        "<reply-draft-{$token}@example.test>";

    $stmt->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $sentEmailId,

        'email' =>
            $email,

        'message_id' =>
            $messageId,
    ]);

    $replyId =
        (int) $pdo->lastInsertId();


    $stmt =
        $pdo->prepare(
            "
            INSERT INTO prospect_emails (
                prospect_id,
                source_reply_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status
            ) VALUES (
                :prospect_id,
                :source_reply_id,
                'manual',
                :email,
                'Re: Message initial',
                'Brouillon initial.',
                'draft'
            )
            "
        );

    $stmt->execute([
        'prospect_id' =>
            $prospectId,

        'source_reply_id' =>
            $replyId,

        'email' =>
            $email,
    ]);

    $draftEmailId =
        (int) $pdo->lastInsertId();


    return [
        'prospect_id' =>
            $prospectId,

        'reply_id' =>
            $replyId,

        'draft_email_id' =>
            $draftEmailId,
    ];
}


function cleanupFixture(
    PDO $pdo,
    int $prospectId
): void {
    $stmt =
        $pdo->prepare(
            "
            DELETE FROM prospects
            WHERE id = :id
            "
        );

    $stmt->execute([
        'id' =>
            $prospectId,
    ]);
}


/*
|--------------------------------------------------------------------------
| Tests
|--------------------------------------------------------------------------
*/

$fixture =
    createFixture(
        $pdo
    );

$prospectId =
    (int) $fixture[
        'prospect_id'
    ];

$replyId =
    (int) $fixture[
        'reply_id'
    ];

$draftEmailId =
    (int) $fixture[
        'draft_email_id'
    ];

$manager =
    new ReplyDraftManager(
        $pdo
    );

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Modification
    |--------------------------------------------------------------------------
    */

    $manager->updateDraft(
        $draftEmailId,
        'Re: Sujet modifié',
        'Message modifié.'
    );

    $stmt =
        $pdo->prepare(
            "
            SELECT
                subject,
                body_text,
                status,
                approved_at
            FROM prospect_emails
            WHERE id = :id
            "
        );

    $stmt->execute([
        'id' =>
            $draftEmailId,
    ]);

    $email =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    assertSameValue(
        'Objet modifié',
        'Re: Sujet modifié',
        $email['subject']
    );

    assertSameValue(
        'Message modifié',
        'Message modifié.',
        $email['body_text']
    );

    assertSameValue(
        'Statut reste draft',
        'draft',
        $email['status']
    );

    assertSameValue(
        'approved_at reste NULL',
        null,
        $email['approved_at']
    );


    /*
    |--------------------------------------------------------------------------
    | 2. Event modification
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare(
            "
            SELECT COUNT(*)
            FROM prospect_events
            WHERE
                prospect_id = :prospect_id
                AND event_type =
                    'prospect_reply_draft_updated'
            "
        );

    $stmt->execute([
        'prospect_id' =>
            $prospectId,
    ]);

    assertSameValue(
        'Événement modification créé',
        1,
        (int) $stmt->fetchColumn()
    );


    /*
    |--------------------------------------------------------------------------
    | 3. Approbation
    |--------------------------------------------------------------------------
    */

    $manager->approveDraft(
        $draftEmailId,
        'Re: Sujet final',
        'Message final validé.'
    );

    $stmt =
        $pdo->prepare(
            "
            SELECT
                subject,
                body_text,
                status,
                approved_at,
                sent_at,
                scheduled_at
            FROM prospect_emails
            WHERE id = :id
            "
        );

    $stmt->execute([
        'id' =>
            $draftEmailId,
    ]);

    $email =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    assertSameValue(
        'Objet final sauvegardé',
        'Re: Sujet final',
        $email['subject']
    );

    assertSameValue(
        'Message final sauvegardé',
        'Message final validé.',
        $email['body_text']
    );

    assertSameValue(
        'Statut approved',
        'approved',
        $email['status']
    );

    assertTrue(
        'approved_at renseigné',
        !empty(
            $email['approved_at']
        )
    );

    assertSameValue(
        'scheduled_at reste NULL',
        null,
        $email['scheduled_at']
    );

    assertSameValue(
        'sent_at reste NULL',
        null,
        $email['sent_at']
    );


    /*
    |--------------------------------------------------------------------------
    | 4. Event approbation
    |--------------------------------------------------------------------------
    */

    $stmt =
        $pdo->prepare(
            "
            SELECT
                metadata
            FROM prospect_events
            WHERE
                prospect_id = :prospect_id
                AND event_type =
                    'prospect_reply_draft_approved'
            ORDER BY id DESC
            LIMIT 1
            "
        );

    $stmt->execute([
        'prospect_id' =>
            $prospectId,
    ]);

    $event =
        $stmt->fetch(
            PDO::FETCH_ASSOC
        );

    assertTrue(
        'Événement approbation créé',
        $event !== false
    );

    if ($event !== false) {
        $metadata =
            json_decode(
                $event['metadata'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        assertSameValue(
            'Event reply_id correct',
            $replyId,
            (int) $metadata[
                'reply_id'
            ]
        );

        assertSameValue(
            'Event prospect_email_id correct',
            $draftEmailId,
            (int) $metadata[
                'prospect_email_id'
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | 5. Modification après approbation interdite
    |--------------------------------------------------------------------------
    */

    assertThrows(
        'Modification après approbation refusée',
        static function () use (
            $manager,
            $draftEmailId
        ): void {
            $manager->updateDraft(
                $draftEmailId,
                'Autre sujet',
                'Autre message.'
            );
        }
    );


    /*
    |--------------------------------------------------------------------------
    | 6. Nouvelle approbation interdite
    |--------------------------------------------------------------------------
    */

    assertThrows(
        'Double approbation refusée',
        static function () use (
            $manager,
            $draftEmailId
        ): void {
            $manager->approveDraft(
                $draftEmailId,
                'Autre sujet',
                'Autre message.'
            );
        }
    );

} finally {
    cleanupFixture(
        $pdo,
        $prospectId
    );
}


/*
|--------------------------------------------------------------------------
| Résultat
|--------------------------------------------------------------------------
*/

echo PHP_EOL;

echo "Tests réussis : {$passed}"
    . PHP_EOL;

echo "Tests échoués : {$failed}"
    . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo PHP_EOL
    . "Reply Draft Manager : OK"
    . PHP_EOL;

exit(0);
