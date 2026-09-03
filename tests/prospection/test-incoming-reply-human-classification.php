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

require_once __DIR__
    . '/../../php/prospection/HumanReplyClassifier.php';

echo "========================================\n";
echo "TEST HUMAN REPLY CLASSIFICATION\n";
echo "========================================\n\n";

$prospectIds = [];

$scenarios = [
    [
        'name' => 'POSITIVE',
        'body' =>
            'Bonjour, je suis intéressé pour en discuter.',
        'expected' => 'positive',
    ],
    [
        'name' => 'NEGATIVE',
        'body' =>
            'Bonjour, je ne suis pas intéressé. Merci.',
        'expected' => 'negative',
    ],
    [
        'name' => 'QUESTION',
        'body' =>
            'Bonjour, combien coûte un site vitrine ?',
        'expected' => 'question',
    ],
];

try {
    /*
     * Pipeline partagé entre les scénarios.
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

    $humanReplyClassifier =
        new HumanReplyClassifier();

    $handler =
        new IncomingReplyHandler(
            $matcher,
            $processor,
            $contentExtractor,
            $unsubscribeManager,
            $automaticReplyDetector,
            $humanReplyClassifier
        );

    foreach ($scenarios as $scenario) {
        echo '----------------------------------------'
            . "\n";

        echo 'SCÉNARIO '
            . $scenario['name']
            . "\n";

        echo '----------------------------------------'
            . "\n\n";

        $unique = bin2hex(
            random_bytes(4)
        );

        $email =
            'handler-human-'
            . strtolower($scenario['name'])
            . '-'
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
                'Test Human '
                . $scenario['name']
                . ' '
                . $unique,
            'activity' => 'test',
            'city' => 'Céret',
            'postal_code' => '66400',
            'email' => $email,
            'status' => 'contacted',
        ]);

        $prospectId =
            (int) $pdo->lastInsertId();

        $prospectIds[] =
            $prospectId;

        /*
         * E-mail initial envoyé.
         */
        $outboundMessageId =
            '<handler-human-out-'
            . strtolower($scenario['name'])
            . '-'
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
            'message_id' =>
                $outboundMessageId,
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
         * Réponse humaine entrante.
         */
        $incomingMessageId =
            '<handler-human-in-'
            . strtolower($scenario['name'])
            . '-'
            . $unique
            . '@example.test>';

        $incomingMessage = [
            'sender_email' => $email,
            'subject' => 'Re: Premier contact',
            'message_id' =>
                $incomingMessageId,
            'in_reply_to' =>
                $outboundMessageId,
            'references' => [
                $outboundMessageId,
            ],
            'body_text' =>
                $scenario['body'],
            'body_html' => null,
            'received_at' =>
                date('Y-m-d H:i:s'),
        ];

        $result =
            $handler->handle(
                $incomingMessage
            );

        /*
         * Résultat du handler.
         */
        if (
            ($result['handled'] ?? false)
                !== true
            || ($result['matched'] ?? false)
                !== true
            || ($result['created'] ?? false)
                !== true
            || ($result['automatic'] ?? true)
                !== false
            || ($result['unsubscribed'] ?? true)
                !== false
            || ($result['classification'] ?? null)
                !== $scenario['expected']
        ) {
            print_r($result);

            throw new RuntimeException(
                'Résultat handler incorrect pour '
                . $scenario['name']
                . '.'
            );
        }

        echo '[OK] Classification handler : '
            . $scenario['expected']
            . "\n";

        /*
         * Classification persistée.
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

        $persistedClassification =
            $statement->fetchColumn();

        if (
            $persistedClassification
            !== $scenario['expected']
        ) {
            throw new RuntimeException(
                'Classification BDD incorrecte pour '
                . $scenario['name']
                . '.'
            );
        }

        echo '[OK] Classification BDD : '
            . $scenario['expected']
            . "\n";

        /*
         * Le prospect doit être replied
         * avec replied_at renseigné.
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
                !== 'replied'
            || $prospect['replied_at']
                === null
        ) {
            throw new RuntimeException(
                'État prospect incorrect pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Prospect passé en replied\n";
        echo "[OK] replied_at renseigné\n";

        /*
         * Une réponse humaine doit stopper
         * la relance programmée.
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
            !== 'cancelled'
        ) {
            throw new RuntimeException(
                'Relance non annulée pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Relance annulée\n";

        /*
         * Événement humain normal.
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
            !== 1
        ) {
            throw new RuntimeException(
                'Événement reply absent pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Événement reply créé\n";

        /*
         * Aucun événement automatic.
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
            !== 0
        ) {
            throw new RuntimeException(
                'Événement automatic créé à tort pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Aucun événement automatic\n";

        /*
         * Aucune désinscription.
         */
        $statement = $pdo->prepare(
            '
            SELECT COUNT(*)
            FROM prospect_unsubscribes
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        if (
            (int) $statement->fetchColumn()
            !== 0
        ) {
            throw new RuntimeException(
                'Désinscription créée à tort pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Aucune désinscription\n";

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
                'Doublon non détecté pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Doublon détecté\n";

        /*
         * Toujours une seule réponse.
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
                'Réponse dupliquée pour '
                . $scenario['name']
                . '.'
            );
        }

        echo "[OK] Une seule réponse persistée\n\n";
    }

    echo "========================================\n";
    echo "TEST HUMAN CLASSIFICATION : OK\n";
    echo "========================================\n";
} finally {
    /*
     * Nettoyage de toutes les fixtures,
     * même si un scénario échoue.
     */
    foreach (
        array_reverse($prospectIds)
        as $prospectId
    ) {
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
    }

    echo "\nFixtures temporaires supprimées.\n";
}
