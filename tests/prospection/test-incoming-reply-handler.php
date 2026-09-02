<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyMatcher.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyProcessor.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyHandler.php';

echo "========================================\n";
echo "TEST INCOMING REPLY HANDLER\n";
echo "========================================\n\n";

$createdProspectIds = [];
$createdEmailIds = [];
$createdReplyIds = [];
$createdEventIds = [];

try {
    $unique = bin2hex(
        random_bytes(4)
    );

    $prospectEmail =
        'prospect-handler-'
        . $unique
        . '@example.test';

    $insertProspect = $pdo->prepare(
        '
        INSERT INTO prospects (
            company_name,
            activity,
            city,
            postal_code,
            email,
            status,
            discovered_at
        ) VALUES (
            :company_name,
            :activity,
            :city,
            :postal_code,
            :email,
            :status,
            NOW()
        )
        '
    );

    $insertProspect->execute([
        'company_name' =>
            'Test Handler ' . $unique,
        'activity' => 'test',
        'city' => 'Céret',
        'postal_code' => '66400',
        'email' => $prospectEmail,
        'status' => 'contacted',
    ]);

    $prospectId = (int) $pdo->lastInsertId();
    $createdProspectIds[] = $prospectId;

    $outboundMessageId =
        '<prospection-handler-'
        . $unique
        . '@olcreativestudio.fr>';

    $insertEmail = $pdo->prepare(
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

    $insertEmail->execute([
        'prospect_id' => $prospectId,
        'email_type' => 'initial',
        'recipient_email' => $prospectEmail,
        'subject' => 'Test handler',
        'body_text' => 'Message test',
        'status' => 'sent',
        'sent_at' => date('Y-m-d H:i:s'),
        'message_id' => $outboundMessageId,
    ]);

    $emailId = (int) $pdo->lastInsertId();
    $createdEmailIds[] = $emailId;

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
     * 1. Message sans correspondance.
     */
    $unknownMessage = [
        'sender_email' =>
            'unknown-' . $unique . '@example.test',
        'subject' => 'Message inconnu',
        'message_id' =>
            '<unknown-'
            . $unique
            . '@example.test>',
        'in_reply_to' => null,
        'references' => [],
        'body_text' => 'Message sans correspondance.',
        'body_html' => null,
        'received_at' =>
            date('Y-m-d H:i:s'),
    ];

    $unknownResult = $handler->handle(
        $unknownMessage
    );

    if (
        ($unknownResult['handled'] ?? true)
        !== false
        || ($unknownResult['matched'] ?? true)
        !== false
        || ($unknownResult['reason'] ?? null)
        !== 'no_match'
    ) {
        throw new RuntimeException(
            'Le message inconnu aurait dû être ignoré.'
        );
    }

    echo "Message inconnu ignoré : OK\n";

    /*
     * 2. Réponse reconnue via In-Reply-To.
     */
    $incomingMessageId =
        '<incoming-handler-'
        . $unique
        . '@example.test>';

    $incomingMessage = [
        'sender_email' => $prospectEmail,
        'subject' => 'Re: Test handler',
        'message_id' => $incomingMessageId,
        'in_reply_to' => $outboundMessageId,
        'references' => [
            $outboundMessageId,
        ],
        'body_text' =>
            'Bonjour, merci pour votre message.',
        'body_html' =>
            '<p>Bonjour, merci pour votre message.</p>',
        'received_at' =>
            date('Y-m-d H:i:s'),
    ];

    $createdResult = $handler->handle(
        $incomingMessage
    );

    if (
        ($createdResult['handled'] ?? false)
        !== true
        || ($createdResult['matched'] ?? false)
        !== true
        || ($createdResult['created'] ?? false)
        !== true
        || ($createdResult['duplicate'] ?? true)
        !== false
        || ($createdResult['match_type'] ?? null)
        !== 'message_id'
    ) {
        throw new RuntimeException(
            'La réponse reconnue n’a pas été traitée correctement.'
        );
    }

    $replyId = (int) (
        $createdResult['reply_id'] ?? 0
    );

    if ($replyId <= 0) {
        throw new RuntimeException(
            'Reply ID invalide.'
        );
    }

    $createdReplyIds[] = $replyId;

    echo "Réponse reconnue et persistée : OK\n";
    echo "Matching par Message-ID : OK\n";

    /*
     * Vérification BDD.
     */
    $checkReply = $pdo->prepare(
        '
        SELECT
            id,
            prospect_id,
            prospect_email_id,
            sender_email,
            message_id
        FROM prospect_replies
        WHERE id = :id
        LIMIT 1
        '
    );

    $checkReply->execute([
        'id' => $replyId,
    ]);

    $reply = $checkReply->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        $reply === false
        || (int) $reply['prospect_id']
            !== $prospectId
        || (int) $reply['prospect_email_id']
            !== $emailId
        || $reply['message_id']
            !== $incomingMessageId
    ) {
        throw new RuntimeException(
            'La réponse persistée est incorrecte.'
        );
    }

    echo "Persistance BDD vérifiée : OK\n";

    /*
     * 3. Même message une seconde fois.
     */
    $duplicateResult = $handler->handle(
        $incomingMessage
    );

    if (
        ($duplicateResult['handled'] ?? false)
        !== true
        || ($duplicateResult['matched'] ?? false)
        !== true
        || ($duplicateResult['created'] ?? true)
        !== false
        || ($duplicateResult['duplicate'] ?? false)
        !== true
    ) {
        throw new RuntimeException(
            'Le doublon n’a pas été détecté.'
        );
    }

    echo "Deuxième traitement détecté comme doublon : OK\n";

    $countReplies = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_replies
        WHERE message_id = :message_id
        '
    );

    $countReplies->execute([
        'message_id' => $incomingMessageId,
    ]);

    if ((int) $countReplies->fetchColumn() !== 1) {
        throw new RuntimeException(
            'Plusieurs réponses ont été persistées.'
        );
    }

    echo "Une seule réponse persistée : OK\n";

    $eventStatement = $pdo->prepare(
        '
        SELECT id
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        '
    );

    $eventStatement->execute([
        'prospect_id' => $prospectId,
        'event_type' =>
            'prospect_reply_received',
    ]);

    $events = $eventStatement->fetchAll(
        PDO::FETCH_COLUMN
    );

    if (count($events) !== 1) {
        throw new RuntimeException(
            'Nombre d’événements incorrect.'
        );
    }

    foreach ($events as $eventId) {
        $createdEventIds[] = (int) $eventId;
    }

    echo "Aucun événement dupliqué : OK\n";

    echo "\n========================================\n";
    echo "TOUS LES TESTS SONT PASSÉS\n";
    echo "========================================\n";
} finally {
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
            WHERE id IN ($placeholders)"
        );

        $statement->execute(
            $createdEventIds
        );
    }

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
            WHERE id IN ($placeholders)"
        );

        $statement->execute(
            $createdReplyIds
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
            WHERE id IN ($placeholders)"
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
            WHERE id IN ($placeholders)"
        );

        $statement->execute(
            $createdProspectIds
        );
    }

    echo "\nFixtures temporaires supprimées.\n";
}
