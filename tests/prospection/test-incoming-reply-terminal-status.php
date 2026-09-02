<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyProcessor.php';

echo "========================================\n";
echo "TEST INCOMING REPLY TERMINAL STATUS\n";
echo "========================================\n\n";

$prospectId = null;
$emailId = null;
$replyId = null;

try {
    $unique = bin2hex(
        random_bytes(4)
    );

    $prospectEmail =
        'terminal-status-'
        . $unique
        . '@example.test';

    $firstRepliedAt = '2026-08-20 10:15:00';

    /*
     * Prospect déjà converti et ayant déjà répondu.
     */
    $insertProspect = $pdo->prepare(
        '
        INSERT INTO prospects (
            company_name,
            activity,
            city,
            postal_code,
            email,
            status,
            discovered_at,
            replied_at,
            converted_at
        ) VALUES (
            :company_name,
            :activity,
            :city,
            :postal_code,
            :email,
            :status,
            NOW(),
            :replied_at,
            :converted_at
        )
        '
    );

    $insertProspect->execute([
        'company_name' =>
            'Test Terminal Status ' . $unique,
        'activity' => 'test',
        'city' => 'Céret',
        'postal_code' => '66400',
        'email' => $prospectEmail,
        'status' => 'converted',
        'replied_at' => $firstRepliedAt,
        'converted_at' => '2026-08-21 09:00:00',
    ]);

    $prospectId = (int) $pdo->lastInsertId();

    /*
     * Ancien e-mail envoyé au prospect.
     */
    $outboundMessageId =
        '<terminal-outbound-'
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
        'subject' => 'Test terminal status',
        'body_text' => 'Message test',
        'status' => 'sent',
        'sent_at' => '2026-08-19 09:30:00',
        'message_id' => $outboundMessageId,
    ]);

    $emailId = (int) $pdo->lastInsertId();

    /*
     * Nouvelle réponse reçue après conversion.
     */
    $newReceivedAt = '2026-09-02 20:00:00';

    $incomingMessage = [
        'sender_email' => $prospectEmail,
        'subject' => 'Re: Test terminal status',
        'message_id' =>
            '<terminal-incoming-'
            . $unique
            . '@example.test>',
        'in_reply_to' => $outboundMessageId,
        'references' => [
            $outboundMessageId,
        ],
        'body_text' =>
            'Nouvelle réponse après conversion.',
        'body_html' =>
            '<p>Nouvelle réponse après conversion.</p>',
        'received_at' => $newReceivedAt,
    ];

    $matchedEmail = [
        'email_id' => $emailId,
        'prospect_id' => $prospectId,
        'match_type' => 'message_id',
    ];

    $processor = new IncomingReplyProcessor(
        $pdo
    );

    $result = $processor->process(
        $incomingMessage,
        $matchedEmail
    );

    $replyId = (int) (
        $result['reply_id'] ?? 0
    );

    if (
        ($result['created'] ?? false) !== true
        || $replyId <= 0
    ) {
        throw new RuntimeException(
            'La nouvelle réponse n’a pas été créée.'
        );
    }

    echo "Nouvelle réponse persistée : OK\n";

    /*
     * Vérification du statut et de replied_at.
     */
    $checkProspect = $pdo->prepare(
        '
        SELECT
            status,
            replied_at
        FROM prospects
        WHERE id = :id
        LIMIT 1
        '
    );

    $checkProspect->execute([
        'id' => $prospectId,
    ]);

    $prospect = $checkProspect->fetch(
        PDO::FETCH_ASSOC
    );

    if ($prospect === false) {
        throw new RuntimeException(
            'Prospect introuvable après traitement.'
        );
    }

    if ($prospect['status'] !== 'converted') {
        throw new RuntimeException(
            'Le statut converted a été écrasé.'
        );
    }

    echo "Statut converted préservé : OK\n";

    if (
        $prospect['replied_at']
        !== $firstRepliedAt
    ) {
        throw new RuntimeException(
            'La date de première réponse a été écrasée.'
        );
    }

    echo "Première date replied_at préservée : OK\n";

    if (
        ($result['prospect_status'] ?? null)
        !== 'converted'
    ) {
        throw new RuntimeException(
            'Le résultat du processor expose un mauvais statut.'
        );
    }

    echo "Statut retourné par le processor : OK\n";

    /*
     * Vérification de l'événement.
     */
    $checkEvent = $pdo->prepare(
        '
        SELECT
            previous_status,
            new_status
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        ORDER BY id DESC
        LIMIT 1
        '
    );

    $checkEvent->execute([
        'prospect_id' => $prospectId,
        'event_type' =>
            'prospect_reply_received',
    ]);

    $event = $checkEvent->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        $event === false
        || $event['previous_status']
            !== 'converted'
        || $event['new_status']
            !== 'converted'
    ) {
        throw new RuntimeException(
            'Le statut terminal est incorrect dans l’événement.'
        );
    }

    echo "Événement converted -> converted : OK\n";

    echo "\n========================================\n";
    echo "TOUS LES TESTS SONT PASSÉS\n";
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
