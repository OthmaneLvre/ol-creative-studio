<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(
    dirname(__DIR__)
);

$dotenv->safeLoad();

return [
    'enabled' => filter_var(
        $_ENV['PROSPECTION_INBOX_ENABLED']
            ?? 'false',
        FILTER_VALIDATE_BOOLEAN
    ),

    'host' => $_ENV['IMAP_HOST'] ?? 'ssl0.ovh.net',
    'port' => (int) ($_ENV['IMAP_PORT'] ?? 993),
    'encryption' => $_ENV['IMAP_ENCRYPTION'] ?? 'ssl',

    'username' => $_ENV['IMAP_USERNAME'] ?? '',
    'password' => $_ENV['IMAP_PASSWORD'] ?? '',

    'mailbox' => $_ENV['IMAP_MAILBOX'] ?? 'INBOX',

    'batch_limit' => (int) (
        $_ENV['PROSPECTION_INBOX_BATCH_LIMIT']
        ?? 50
    ),

    'dry_run' => filter_var(
        $_ENV['PROSPECTION_INBOX_DRY_RUN']
            ?? 'true',
        FILTER_VALIDATE_BOOLEAN
    ),

    /*
     * V1 : aucune suppression et aucun déplacement automatique.
     * La surveillance doit rester non destructive.
     */
    'read_only' => true,
];
