<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyProcessor.php';

echo "========================================\n";
echo "TEST INCOMING REPLY PROCESSOR\n";
echo "========================================\n\n";

$processor = new IncomingReplyProcessor($pdo);

$createdProspectIds = [];
$createdEmailIds = [];
$createdReplyIds = [];
$createdEventIds = [];

function assertTest(
    bool $condition,
    string $successMessage,
    string $failureMessage
): void {
    if (!$condition) {
        throw new RuntimeException(
            $failureMessage
        );
    }

    echo $successMessage . PHP_EOL;
}

try {
    /*
    |--------------------------------------------------------------------------
    | Fixture prospect
    |--------------------------------------------------------------------------
    */

    $prospectEmail =
        'reply-processor-'
        . bin2hex(random_bytes(4))
        . '@example.test';

    $statement = $pdo->prepare(
        '
        INSERT INTO prospects (
            company_name,
            activity,
            city,
            postal_code,
            email,
            status,
            discovered_at,
            contacted_at
        ) VALUES (
            :company_name,
            :activity,
            :city,
            :postal_code,
            :email,
            :status,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'TEST REPLY PROCESSOR',

        'activity' =>
            'test',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            $prospectEmail,

        'status' =>
            'contacted',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    $createdProspectIds[] =
        $prospectId;

    /*
    |--------------------------------------------------------------------------
    | E-mail initial déjà envoyé
    |--------------------------------------------------------------------------
    */

    $outboundMessageId =
        '<outbound-'
        . bin2hex(random_bytes(8))
        . '@olcreativestudio.fr>';

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
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            $prospectEmail,

        'subject' =>
            'Premier contact',

        'body_text' =>
            'Message initial.',

        'status' =>
            'sent',

        'sent_at' =>
            (new DateTimeImmutable('-4 days'))
                ->format('Y-m-d H:i:s'),

        'message_id' =>
            $outboundMessageId,
    ]);

    $initialEmailId =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $initialEmailId;

    /*
    |--------------------------------------------------------------------------
    | Relance 1 programmée
    |--------------------------------------------------------------------------
    */

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'follow_up_1',

        'recipient_email' =>
            $prospectEmail,

        'subject' =>
            'Relance 1',

        'body_text' =>
            'Première relance.',

        'status' =>
            'scheduled',

        'sent_at' =>
            null,

        'message_id' =>
            null,
    ]);

    $followUp1Id =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $followUp1Id;

    /*
    |--------------------------------------------------------------------------
    | Relance 2 approuvée
    |--------------------------------------------------------------------------
    */

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'follow_up_2',

        'recipient_email' =>
            $prospectEmail,

        'subject' =>
            'Relance 2',

        'body_text' =>
            'Deuxième relance.',

        'status' =>
            'approved',

        'sent_at' =>
            null,

        'message_id' =>
            null,
    ]);

    $followUp2Id =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $followUp2Id;

    /*
    |--------------------------------------------------------------------------
    | Message entrant
    |--------------------------------------------------------------------------
    */

    $incomingMessageId =
        '<incoming-'
        . bin2hex(random_bytes(8))
        . '@example.test>';

    $receivedAt =
        (new DateTimeImmutable())
            ->format('Y-m-d H:i:s');

    $incomingMessage = [
        'sender_email' =>
            $prospectEmail,

        'subject' =>
            'Re: Premier contact',

        'body_text' =>
            'Bonjour, votre proposition m’intéresse.',

        'body_html' =>
            '<p>Bonjour, votre proposition m’intéresse.</p>',

        'message_id' =>
            $incomingMessageId,

        'in_reply_to' =>
            $outboundMessageId,

        'received_at' =>
            $receivedAt,
    ];

    $matchedEmail = [
        'email_id' =>
            $initialEmailId,

        'prospect_id' =>
            $prospectId,
    ];

    /*
    |--------------------------------------------------------------------------
    | 1. Premier traitement
    |--------------------------------------------------------------------------
    */

    $result = $processor->process(
        $incomingMessage,
        $matchedEmail
    );

    assertTest(
        ($result['created'] ?? false) === true
        && ($result['duplicate'] ?? true) === false,
        'Création de la réponse : OK',
        'La réponse entrante n’a pas été créée.'
    );

    $replyId =
        (int) ($result['reply_id'] ?? 0);

    assertTest(
        $replyId > 0,
        'Reply ID créé : OK',
        'Aucun reply_id valide retourné.'
    );

    $createdReplyIds[] =
        $replyId;

    /*
    |--------------------------------------------------------------------------
    | 2. Vérification prospect -> replied
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            status,
            replied_at
        FROM prospects
        WHERE id = :id
        '
    );

    $statement->execute([
        'id' => $prospectId,
    ]);

    $prospect =
        $statement->fetch(PDO::FETCH_ASSOC);

    assertTest(
        $prospect !== false
        && $prospect['status'] === 'replied'
        && $prospect['replied_at'] === $receivedAt,
        'Prospect -> replied : OK',
        'Le prospect n’a pas été correctement passé à replied.'
    );

    /*
    |--------------------------------------------------------------------------
    | 3. Vérification réponse persistée
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            prospect_id,
            prospect_email_id,
            sender_email,
            message_id,
            classification,
            received_at
        FROM prospect_replies
        WHERE id = :id
        '
    );

    $statement->execute([
        'id' => $replyId,
    ]);

    $reply =
        $statement->fetch(PDO::FETCH_ASSOC);

    assertTest(
        $reply !== false
        && (int) $reply['prospect_id']
            === $prospectId
        && (int) $reply['prospect_email_id']
            === $initialEmailId
        && $reply['sender_email']
            === strtolower($prospectEmail)
        && $reply['message_id']
            === $incomingMessageId
        && $reply['classification']
            === 'unknown'
        && $reply['received_at']
            === $receivedAt,
        'Persistance prospect_replies : OK',
        'Les données de prospect_replies sont incorrectes.'
    );

    /*
    |--------------------------------------------------------------------------
    | 4. Relances annulées
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            id,
            status
        FROM prospect_emails
        WHERE id IN (:follow_up_1, :follow_up_2)
        ORDER BY id
        '
    );

    $statement->execute([
        'follow_up_1' => $followUp1Id,
        'follow_up_2' => $followUp2Id,
    ]);

    $followUps =
        $statement->fetchAll(PDO::FETCH_ASSOC);

    assertTest(
        count($followUps) === 2
        && $followUps[0]['status'] === 'cancelled'
        && $followUps[1]['status'] === 'cancelled',
        'Relances futures annulées : OK',
        'Les relances futures n’ont pas été annulées.'
    );

    assertTest(
        (int) ($result['cancelled_follow_ups'] ?? -1) === 2,
        'Compteur de relances annulées : OK',
        'Le nombre de relances annulées est incorrect.'
    );

    /*
    |--------------------------------------------------------------------------
    | 5. Événement journalisé
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            id,
            event_type,
            previous_status,
            new_status,
            metadata
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
            'prospect_reply_received',
    ]);

    $event =
        $statement->fetch(PDO::FETCH_ASSOC);

    assertTest(
        $event !== false
        && $event['previous_status'] === 'contacted'
        && $event['new_status'] === 'replied',
        'Événement de réponse journalisé : OK',
        'L’événement de réponse est incorrect.'
    );

    if ($event !== false) {
        $createdEventIds[] =
            (int) $event['id'];
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Idempotence
    |--------------------------------------------------------------------------
    */

    $duplicateResult =
        $processor->process(
            $incomingMessage,
            $matchedEmail
        );

    assertTest(
        ($duplicateResult['created'] ?? true) === false
        && ($duplicateResult['duplicate'] ?? false) === true
        && (int) ($duplicateResult['reply_id'] ?? 0)
            === $replyId,
        'Deuxième traitement détecté comme doublon : OK',
        'L’idempotence du traitement a échoué.'
    );

    /*
    |--------------------------------------------------------------------------
    | 7. Une seule réponse en base
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_replies
        WHERE message_id = :message_id
        '
    );

    $statement->execute([
        'message_id' =>
            $incomingMessageId,
    ]);

    $replyCount =
        (int) $statement->fetchColumn();

    assertTest(
        $replyCount === 1,
        'Une seule réponse persistée : OK',
        'Le même Message-ID a créé plusieurs réponses.'
    );

    /*
    |--------------------------------------------------------------------------
    | 8. Un seul événement
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'event_type' =>
            'prospect_reply_received',
    ]);

    $eventCount =
        (int) $statement->fetchColumn();

    assertTest(
        $eventCount === 1,
        'Aucun événement dupliqué : OK',
        'Le second traitement a dupliqué l’événement.'
    );

    echo "\n========================================\n";
    echo "TOUS LES TESTS SONT PASSÉS\n";
    echo "========================================\n";

} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    if ($createdReplyIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdReplyIds),
                '?'
            )
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospect_replies
            WHERE id IN ({$placeholders})"
        );

        $statement->execute(
            $createdReplyIds
        );
    }

    if ($createdEventIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdEventIds),
                '?'
            )
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospect_events
            WHERE id IN ({$placeholders})"
        );

        $statement->execute(
            $createdEventIds
        );
    }

    if ($createdEmailIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdEmailIds),
                '?'
            )
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospect_emails
            WHERE id IN ({$placeholders})"
        );

        $statement->execute(
            $createdEmailIds
        );
    }

    if ($createdProspectIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdProspectIds),
                '?'
            )
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospects
            WHERE id IN ({$placeholders})"
        );

        $statement->execute(
            $createdProspectIds
        );
    }

    echo "\nFixtures temporaires supprimées.\n";
}
