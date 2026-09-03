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

require_once __DIR__
    . '/../../php/prospection/ReplyContentExtractor.php';

require_once __DIR__
    . '/../../php/prospection/UnsubscribeManager.php';

require_once __DIR__
    . '/../../php/prospection/AutomaticReplyDetector.php';

echo "========================================\n";
echo "TEST HANDLER AUTOMATIC REPLY\n";
echo "========================================\n\n";

$prospectId = null;

try {
    $unique = bin2hex(
        random_bytes(4)
    );

    $email =
        'handler-automatic-'
        . $unique
        . '@example.test';

    /*
     * Prospect déjà contacté.
     */
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
            NOW(),
            NOW()
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'Test Handler Automatic ' . $unique,
        'activity' => 'test',
        'city' => 'Céret',
        'postal_code' => '66400',
        'email' => $email,
        'status' => 'contacted',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
     * Email initial envoyé.
     */
    $outboundMessageId =
        '<handler-auto-out-'
        . $unique
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
            NOW(),
            :message_id
        )
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'email_type' => 'initial',
        'recipient_email' => $email,
        'subject' => 'Premier contact',
        'body_text' => 'Message initial',
        'status' => 'sent',
        'message_id' => $outboundMessageId,
    ]);

    /*
     * Relance prévue.
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
            DATE_ADD(NOW(), INTERVAL 4 DAY)
        )
        '
    );

    $statement->execute([
        'prospect_id' => $prospectId,
        'email_type' => 'follow_up_1',
        'recipient_email' => $email,
        'subject' => 'Relance',
        'body_text' => 'Relance',
        'status' => 'scheduled',
    ]);

    $followUpId =
        (int) $pdo->lastInsertId();

    /*
     * Le handler doit détecter lui-même
     * qu'il s'agit d'une réponse automatique.
     */
    $incomingMessageId =
        '<handler-auto-in-'
        . $unique
        . '@example.test>';

    $incomingMessage = [
        'sender_email' => $email,
        'subject' =>
            'Réponse automatique : absence du bureau',
        'message_id' => $incomingMessageId,
        'in_reply_to' => $outboundMessageId,
        'references' => [
            $outboundMessageId,
        ],
        'body_text' =>
            'Bonjour, je suis actuellement absent du bureau jusqu’au 15 septembre.',
        'body_html' => null,
        'received_at' =>
            date('Y-m-d H:i:s'),
    ];

    /*
     * Pipeline complet.
     */
    $matcher =
        new IncomingReplyMatcher($pdo);

    $processor =
        new IncomingReplyProcessor($pdo);

    $contentExtractor =
        new ReplyContentExtractor();

    $unsubscribeManager =
        new UnsubscribeManager($pdo);

    $automaticReplyDetector =
        new AutomaticReplyDetector();

    $handler =
        new IncomingReplyHandler(
            $matcher,
            $processor,
            $contentExtractor,
            $unsubscribeManager,
            $automaticReplyDetector
        );

    $result =
        $handler->handle(
            $incomingMessage
        );

    print_r($result);

    /*
     * Résultat handler.
     */
    if (
        ($result['handled'] ?? false)
            !== true
        || ($result['matched'] ?? false)
            !== true
        || ($result['created'] ?? false)
            !== true
        || ($result['automatic'] ?? false)
            !== true
        || ($result['classification'] ?? null)
            !== 'automatic'
        || ($result['unsubscribed'] ?? true)
            !== false
    ) {
        throw new RuntimeException(
            'Résultat handler automatic incorrect.'
        );
    }

    echo "[OK] Auto-réponse détectée par le handler\n";

    /*
     * Reply persistée automatic.
     */
    $statement = $pdo->prepare(
        '
        SELECT classification
        FROM prospect_replies
        WHERE message_id = :message_id
        LIMIT 1
        '
    );

    $statement->execute([
        'message_id' =>
            $incomingMessageId,
    ]);

    if (
        $statement->fetchColumn()
        !== 'automatic'
    ) {
        throw new RuntimeException(
            'Classification automatic incorrecte.'
        );
    }

    echo "[OK] Classification automatic persistée\n";

    /*
     * Prospect inchangé.
     */
    $statement = $pdo->prepare(
        '
        SELECT
            status,
            replied_at
        FROM prospects
        WHERE id = :id
        LIMIT 1
        '
    );

    $statement->execute([
        'id' => $prospectId,
    ]);

    $prospect =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if (
        $prospect === false
        || $prospect['status']
            !== 'contacted'
        || $prospect['replied_at']
            !== null
    ) {
        throw new RuntimeException(
            'Le prospect a été modifié à tort.'
        );
    }

    echo "[OK] Prospect reste contacted\n";
    echo "[OK] replied_at reste NULL\n";

    /*
     * Relance conservée.
     */
    $statement = $pdo->prepare(
        '
        SELECT status
        FROM prospect_emails
        WHERE id = :id
        LIMIT 1
        '
    );

    $statement->execute([
        'id' => $followUpId,
    ]);

    if (
        $statement->fetchColumn()
        !== 'scheduled'
    ) {
        throw new RuntimeException(
            'La relance a été annulée à tort.'
        );
    }

    echo "[OK] Relance reste scheduled\n";

    /*
     * Événement automatic unique.
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
        'prospect_id' => $prospectId,
        'event_type' =>
            'prospect_automatic_reply_received',
    ]);

    if (
        (int) $statement->fetchColumn()
        !== 1
    ) {
        throw new RuntimeException(
            'Événement automatic absent.'
        );
    }

    echo "[OK] Événement automatic créé\n";

    /*
     * Idempotence.
     */
    $duplicateResult =
        $handler->handle(
            $incomingMessage
        );

    if (
        ($duplicateResult['duplicate'] ?? false)
        !== true
    ) {
        throw new RuntimeException(
            'Doublon automatic non détecté.'
        );
    }

    echo "[OK] Doublon automatic détecté\n";

    /*
     * Toujours une seule reply.
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

    if (
        (int) $statement->fetchColumn()
        !== 1
    ) {
        throw new RuntimeException(
            'Reply automatic dupliquée.'
        );
    }

    echo "[OK] Une seule reply persistée\n";

    echo "\n========================================\n";
    echo "TEST HANDLER AUTOMATIC : OK\n";
    echo "========================================\n";
} finally {
    if ($prospectId !== null) {
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
    }

    echo "\nFixtures temporaires supprimées.\n";
}
