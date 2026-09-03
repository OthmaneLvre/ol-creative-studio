<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyMatcher.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyProcessor.php';

require_once __DIR__
    . '/../../php/prospection/ReplyContentExtractor.php';

require_once __DIR__
    . '/../../php/prospection/UnsubscribeManager.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyHandler.php';

echo "========================================\n";
echo "TEST INCOMING REPLY UNSUBSCRIBE\n";
echo "========================================\n\n";

$createdProspectIds = [];
$createdEmailIds = [];

$matcher = new IncomingReplyMatcher($pdo);
$processor = new IncomingReplyProcessor($pdo);
$contentExtractor = new ReplyContentExtractor();
$unsubscribeManager = new UnsubscribeManager($pdo);

$handler = new IncomingReplyHandler(
    $matcher,
    $processor,
    $contentExtractor,
    $unsubscribeManager
);

function createFixture(
    PDO $pdo,
    string $unique,
    array &$createdProspectIds,
    array &$createdEmailIds
): array {
    $email =
        'unsubscribe-handler-'
        . $unique
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
            NOW(),
            NOW()
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'Test Unsubscribe Handler ' . $unique,
        'activity' => 'test',
        'city' => 'Céret',
        'postal_code' => '66400',
        'email' => $email,
        'status' => 'contacted',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    $createdProspectIds[] =
        $prospectId;

    $messageId =
        '<unsubscribe-handler-'
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
        'subject' => 'Test unsubscribe',
        'body_text' =>
            'Pour ne plus recevoir nos messages, répondez STOP.',
        'status' => 'sent',
        'message_id' => $messageId,
    ]);

    $initialEmailId =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $initialEmailId;

    /*
     * Relance programmée qui devra être annulée
     * uniquement en cas de vraie désinscription.
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
        'subject' => 'Relance test',
        'body_text' => 'Relance test',
        'status' => 'scheduled',
    ]);

    $followUpId =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $followUpId;

    return [
        'prospect_id' => $prospectId,
        'email' => $email,
        'initial_email_id' => $initialEmailId,
        'follow_up_id' => $followUpId,
        'message_id' => $messageId,
    ];
}

function fetchValue(
    PDO $pdo,
    string $sql,
    array $params
): mixed {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchColumn();
}

try {
    /*
     * ============================================================
     * SCÉNARIO 1
     * STOP réellement envoyé par le prospect.
     * ============================================================
     */

    $uniqueStop =
        'stop-' . bin2hex(random_bytes(4));

    $stopFixture = createFixture(
        $pdo,
        $uniqueStop,
        $createdProspectIds,
        $createdEmailIds
    );

    $stopMessage = [
        'sender_email' =>
            $stopFixture['email'],
        'subject' =>
            'Re: Test unsubscribe',
        'message_id' =>
            '<incoming-stop-'
            . $uniqueStop
            . '@example.test>',
        'in_reply_to' =>
            $stopFixture['message_id'],
        'references' => [
            $stopFixture['message_id'],
        ],
        'body_text' =>
            "STOP\n\n"
            . "Le 2 sept. 2026 à 20:20, OL Creative Studio a écrit :\n"
            . "> Bonjour\n"
            . "> Pour ne plus recevoir nos messages, répondez STOP.",
        'body_html' => null,
        'received_at' =>
            date('Y-m-d H:i:s'),
    ];

    $stopResult =
        $handler->handle($stopMessage);

    if (
        ($stopResult['created'] ?? false)
            !== true
        || ($stopResult['unsubscribed'] ?? false)
            !== true
    ) {
        throw new RuntimeException(
            'La demande STOP n’a pas été reconnue.'
        );
    }

    $stopReplyId =
        (int) ($stopResult['reply_id'] ?? 0);

    echo "STOP reconnu par le handler : OK\n";

    $prospectStatus = fetchValue(
        $pdo,
        '
        SELECT status
        FROM prospects
        WHERE id = :id
        ',
        ['id' => $stopFixture['prospect_id']]
    );

    $replyClassification = fetchValue(
        $pdo,
        '
        SELECT classification
        FROM prospect_replies
        WHERE id = :id
        ',
        ['id' => $stopReplyId]
    );

    $followUpStatus = fetchValue(
        $pdo,
        '
        SELECT status
        FROM prospect_emails
        WHERE id = :id
        ',
        ['id' => $stopFixture['follow_up_id']]
    );

    $unsubscribeCount = (int) fetchValue(
        $pdo,
        '
        SELECT COUNT(*)
        FROM prospect_unsubscribes
        WHERE prospect_id = :prospect_id
        ',
        [
            'prospect_id' =>
                $stopFixture['prospect_id'],
        ]
    );

    $unsubscribeEventCount =
        (int) fetchValue(
            $pdo,
            '
            SELECT COUNT(*)
            FROM prospect_events
            WHERE prospect_id = :prospect_id
            AND event_type = :event_type
            ',
            [
                'prospect_id' =>
                    $stopFixture['prospect_id'],
                'event_type' =>
                    'prospect_unsubscribed',
            ]
        );

    $checks = [
        'Prospect passé en unsubscribed' =>
            $prospectStatus === 'unsubscribed',

        'Réponse classée unsubscribed' =>
            $replyClassification === 'unsubscribed',

        'Relance annulée' =>
            $followUpStatus === 'cancelled',

        'Blacklist créée' =>
            $unsubscribeCount === 1,

        'Événement de désinscription créé' =>
            $unsubscribeEventCount === 1,
    ];

    foreach ($checks as $label => $passed) {
        if (!$passed) {
            throw new RuntimeException(
                'Échec : ' . $label
            );
        }

        echo $label . " : OK\n";
    }

    /*
     * Vérification du doublon.
     */
    $duplicateStop =
        $handler->handle($stopMessage);

    if (
        ($duplicateStop['duplicate'] ?? false)
            !== true
        || ($duplicateStop['unsubscribed'] ?? true)
            !== false
    ) {
        throw new RuntimeException(
            'Le second traitement STOP n’est pas idempotent.'
        );
    }

    $unsubscribeCountAfter =
        (int) fetchValue(
            $pdo,
            '
            SELECT COUNT(*)
            FROM prospect_unsubscribes
            WHERE prospect_id = :prospect_id
            ',
            [
                'prospect_id' =>
                    $stopFixture['prospect_id'],
            ]
        );

    $unsubscribeEventCountAfter =
        (int) fetchValue(
            $pdo,
            '
            SELECT COUNT(*)
            FROM prospect_events
            WHERE prospect_id = :prospect_id
            AND event_type = :event_type
            ',
            [
                'prospect_id' =>
                    $stopFixture['prospect_id'],
                'event_type' =>
                    'prospect_unsubscribed',
            ]
        );

    if (
        $unsubscribeCountAfter !== 1
        || $unsubscribeEventCountAfter !== 1
    ) {
        throw new RuntimeException(
            'La désinscription a été dupliquée.'
        );
    }

    echo "Idempotence STOP : OK\n\n";

    /*
     * ============================================================
     * SCÉNARIO 2
     * Le mot STOP existe uniquement dans l’historique cité.
     * ============================================================
     */

    $uniqueQuoted =
        'quoted-' . bin2hex(random_bytes(4));

    $quotedFixture = createFixture(
        $pdo,
        $uniqueQuoted,
        $createdProspectIds,
        $createdEmailIds
    );

    $quotedMessage = [
        'sender_email' =>
            $quotedFixture['email'],
        'subject' =>
            'Re: Test unsubscribe',
        'message_id' =>
            '<incoming-quoted-'
            . $uniqueQuoted
            . '@example.test>',
        'in_reply_to' =>
            $quotedFixture['message_id'],
        'references' => [
            $quotedFixture['message_id'],
        ],
        'body_text' =>
            "Bonjour,\n"
            . "Je suis intéressé pour en discuter.\n\n"
            . "Le 2 sept. 2026 à 20:20, OL Creative Studio a écrit :\n"
            . "> Bonjour\n"
            . "> Pour ne plus recevoir nos messages, répondez STOP.",
        'body_html' => null,
        'received_at' =>
            date('Y-m-d H:i:s'),
    ];

    $quotedResult =
        $handler->handle($quotedMessage);

    if (
        ($quotedResult['created'] ?? false)
            !== true
        || ($quotedResult['unsubscribed'] ?? true)
            !== false
    ) {
        throw new RuntimeException(
            'Faux positif STOP dans l’historique cité.'
        );
    }

    echo "STOP cité ignoré : OK\n";

    $quotedReplyId =
        (int) ($quotedResult['reply_id'] ?? 0);

    $quotedProspectStatus = fetchValue(
        $pdo,
        '
        SELECT status
        FROM prospects
        WHERE id = :id
        ',
        ['id' => $quotedFixture['prospect_id']]
    );

    $quotedClassification = fetchValue(
        $pdo,
        '
        SELECT classification
        FROM prospect_replies
        WHERE id = :id
        ',
        ['id' => $quotedReplyId]
    );

    $quotedFollowUpStatus = fetchValue(
        $pdo,
        '
        SELECT status
        FROM prospect_emails
        WHERE id = :id
        ',
        ['id' => $quotedFixture['follow_up_id']]
    );

    $quotedUnsubscribeCount =
        (int) fetchValue(
            $pdo,
            '
            SELECT COUNT(*)
            FROM prospect_unsubscribes
            WHERE prospect_id = :prospect_id
            ',
            [
                'prospect_id' =>
                    $quotedFixture['prospect_id'],
            ]
        );

    if ($quotedProspectStatus !== 'replied') {
        throw new RuntimeException(
            'Le prospect normal devrait être replied.'
        );
    }

    if ($quotedClassification !== 'unknown') {
        throw new RuntimeException(
            'La réponse normale ne devrait pas être désinscrite.'
        );
    }

    /*
     * IncomingReplyProcessor annule les follow-ups
     * dès qu’une réponse normale est reçue.
     */
    if ($quotedFollowUpStatus !== 'cancelled') {
        throw new RuntimeException(
            'La relance aurait dû être annulée après la réponse.'
        );
    }

    if ($quotedUnsubscribeCount !== 0) {
        throw new RuntimeException(
            'Une blacklist incorrecte a été créée.'
        );
    }

    echo "Prospect conservé en replied : OK\n";
    echo "Aucune blacklist créée : OK\n";
    echo "Relance annulée après réponse : OK\n";

    echo "\n========================================\n";
    echo "TOUS LES TESTS UNSUBSCRIBE SONT PASSÉS\n";
    echo "========================================\n";
} finally {
    if ($createdProspectIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdProspectIds),
                '?'
            )
        );

        /*
         * Respect de l’ordre des dépendances.
         */

        $statement = $pdo->prepare(
            "DELETE FROM prospect_unsubscribes
            WHERE prospect_id IN ($placeholders)"
        );
        $statement->execute($createdProspectIds);

        $statement = $pdo->prepare(
            "DELETE FROM prospect_events
            WHERE prospect_id IN ($placeholders)"
        );
        $statement->execute($createdProspectIds);

        $statement = $pdo->prepare(
            "DELETE FROM prospect_replies
            WHERE prospect_id IN ($placeholders)"
        );
        $statement->execute($createdProspectIds);

        $statement = $pdo->prepare(
            "DELETE FROM prospect_emails
            WHERE prospect_id IN ($placeholders)"
        );
        $statement->execute($createdProspectIds);

        $statement = $pdo->prepare(
            "DELETE FROM prospects
            WHERE id IN ($placeholders)"
        );
        $statement->execute($createdProspectIds);
    }

    echo "\nFixtures temporaires supprimées.\n";
}
