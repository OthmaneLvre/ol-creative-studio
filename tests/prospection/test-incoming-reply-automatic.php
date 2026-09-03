<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyProcessor.php';

echo "========================================\n";
echo "TEST INCOMING REPLY AUTOMATIC\n";
echo "========================================\n\n";

$prospectId = null;
$initialEmailId = null;
$followUpId = null;

try {
    $unique = bin2hex(
        random_bytes(4)
    );

    $prospectEmail =
        'automatic-reply-'
        . $unique
        . '@example.test';

    /*
     * Prospect contacté.
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
            'Test Automatic Reply ' . $unique,
        'activity' => 'test',
        'city' => 'Céret',
        'postal_code' => '66400',
        'email' => $prospectEmail,
        'status' => 'contacted',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
     * Email initial déjà envoyé.
     */
    $outboundMessageId =
        '<automatic-outbound-'
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
        'recipient_email' => $prospectEmail,
        'subject' => 'Test automatic reply',
        'body_text' => 'Message initial',
        'status' => 'sent',
        'message_id' => $outboundMessageId,
    ]);

    $initialEmailId =
        (int) $pdo->lastInsertId();

    /*
     * Relance future.
     * Elle doit rester programmée.
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
        'recipient_email' => $prospectEmail,
        'subject' => 'Relance test',
        'body_text' => 'Relance test',
        'status' => 'scheduled',
    ]);

    $followUpId =
        (int) $pdo->lastInsertId();

    /*
     * Réponse automatique entrante.
     */
    $incomingMessageId =
        '<automatic-incoming-'
        . $unique
        . '@example.test>';

    $incomingMessage = [
        'sender_email' => $prospectEmail,
        'subject' =>
            'Réponse automatique : absence du bureau',
        'message_id' => $incomingMessageId,
        'in_reply_to' => $outboundMessageId,
        'references' => [
            $outboundMessageId,
        ],
        'body_text' =>
            'Bonjour, je suis actuellement absent du bureau.',
        'body_html' => null,
        'received_at' =>
            date('Y-m-d H:i:s'),
    ];

    $matchedEmail = [
        'prospect_id' => $prospectId,
        'email_id' => $initialEmailId,
    ];

    $processor =
        new IncomingReplyProcessor($pdo);

    $result =
        $processor->process(
            $incomingMessage,
            $matchedEmail,
            'automatic'
        );

    print_r($result);

    /*
     * Vérification retour processor.
     */
    if (
        ($result['created'] ?? false)
            !== true
        || ($result['classification'] ?? null)
            !== 'automatic'
        || ($result['previous_status'] ?? null)
            !== 'contacted'
        || ($result['prospect_status'] ?? null)
            !== 'contacted'
        || (int) (
            $result['cancelled_follow_ups']
                ?? -1
        ) !== 0
    ) {
        throw new RuntimeException(
            'Résultat processor automatic incorrect.'
        );
    }

    echo "[OK] Processor retourne automatic\n";

    /*
     * Vérification reply.
     */
    $statement = $pdo->prepare(
        '
        SELECT
            id,
            classification
        FROM prospect_replies
        WHERE message_id = :message_id
        LIMIT 1
        '
    );

    $statement->execute([
        'message_id' => $incomingMessageId,
    ]);

    $reply =
        $statement->fetch(PDO::FETCH_ASSOC);

    if (
        $reply === false
        || $reply['classification']
            !== 'automatic'
    ) {
        throw new RuntimeException(
            'Classification automatic non persistée.'
        );
    }

    echo "[OK] Reply classifiée automatic\n";

    /*
     * Vérification prospect :
     * - toujours contacted
     * - replied_at toujours NULL
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
        $statement->fetch(PDO::FETCH_ASSOC);

    if (
        $prospect === false
        || $prospect['status']
            !== 'contacted'
        || $prospect['replied_at']
            !== null
    ) {
        throw new RuntimeException(
            'Le prospect a été modifié par la réponse automatique.'
        );
    }

    echo "[OK] Prospect reste contacted\n";
    echo "[OK] replied_at reste NULL\n";

    /*
     * La relance ne doit PAS être annulée.
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

    $followUpStatus =
        $statement->fetchColumn();

    if ($followUpStatus !== 'scheduled') {
        throw new RuntimeException(
            'La relance a été annulée à tort.'
        );
    }

    echo "[OK] Relance reste scheduled\n";

    /*
     * Événement dédié.
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
            'Événement automatic incorrect.'
        );
    }

    echo "[OK] Événement automatic journalisé\n";

    /*
     * Aucun événement humain normal.
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
            'prospect_reply_received',
    ]);

    if (
        (int) $statement->fetchColumn()
        !== 0
    ) {
        throw new RuntimeException(
            'Un événement humain a été créé à tort.'
        );
    }

    echo "[OK] Aucun événement humain créé\n";

    echo "\n========================================\n";
    echo "TEST AUTOMATIC : OK\n";
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

    echo "\nCleanup : OK\n";
}
