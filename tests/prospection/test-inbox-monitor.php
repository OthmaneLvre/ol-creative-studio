<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/InboxMonitor.php';

$config = require __DIR__
    . '/../../config/inbox.php';

echo "========================================\n";
echo "TEST INBOX MONITOR\n";
echo "========================================\n\n";

$monitor = new InboxMonitor($config);

$messages = $monitor->fetchRecentMessages(5);

echo "Messages récupérés : "
    . count($messages)
    . "\n\n";

foreach ($messages as $message) {
    echo "----------------------------------------\n";
    echo "UID : "
        . ($message['uid'] ?? 'inconnu')
        . "\n";

    echo "Expéditeur : "
        . ($message['sender_email'] ?? 'inconnu')
        . "\n";

    echo "Sujet : "
        . ($message['subject'] ?? '(sans sujet)')
        . "\n";

    echo "Message-ID : "
        . ($message['message_id'] ?? 'absent')
        . "\n";

    echo "In-Reply-To : "
        . ($message['in_reply_to'] ?? 'absent')
        . "\n";

    echo "References : "
        . implode(
            ', ',
            $message['references'] ?? []
        )
        . "\n";

    echo "Reçu le : "
        . ($message['received_at'] ?? 'inconnu')
        . "\n";
}

echo "\nLecture seule : OK\n";
