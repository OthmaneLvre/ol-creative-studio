<?php

declare(strict_types=1);

$config = require __DIR__ . '/../../config/inbox.php';

echo "========================================\n";
echo "TEST CONNEXION IMAP\n";
echo "========================================\n\n";

if (!extension_loaded('imap')) {
    throw new RuntimeException(
        'Extension IMAP non chargée.'
    );
}

$host = trim((string) ($config['host'] ?? ''));
$port = (int) ($config['port'] ?? 993);
$encryption = trim((string) ($config['encryption'] ?? 'ssl'));

$username = trim((string) ($config['username'] ?? ''));
$password = (string) ($config['password'] ?? '');

$mailboxName = trim((string) ($config['mailbox'] ?? 'INBOX'));

if ($host === '') {
    throw new RuntimeException(
        'Hôte IMAP manquant.'
    );
}

if ($port <= 0 || $port > 65535) {
    throw new RuntimeException(
        'Port IMAP invalide.'
    );
}

if ($username === '') {
    throw new RuntimeException(
        'Nom d’utilisateur IMAP manquant.'
    );
}

if ($password === '') {
    throw new RuntimeException(
        'Mot de passe IMAP manquant.'
    );
}

if ($mailboxName === '') {
    throw new RuntimeException(
        'Nom de boîte IMAP manquant.'
    );
}

$flags = '/imap';

if ($encryption === 'ssl') {
    $flags .= '/ssl';
} elseif ($encryption === 'tls') {
    $flags .= '/tls';
}

$mailbox = sprintf(
    '{%s:%d%s}%s',
    $host,
    $port,
    $flags,
    $mailboxName
);

echo "Extension IMAP : OK\n";
echo "Configuration : OK\n";
echo "Connexion en lecture seule...\n";

imap_errors();
imap_alerts();

$connection = @imap_open(
    $mailbox,
    $username,
    $password,
    OP_READONLY,
    1
);

if ($connection === false) {
    $errors = imap_errors();

    throw new RuntimeException(
        'Connexion IMAP impossible : '
        . implode(' | ', $errors ?: ['erreur inconnue'])
    );
}

try {
    $status = imap_check($connection);

    if ($status === false) {
        throw new RuntimeException(
            'Impossible de récupérer les informations de la boîte.'
        );
    }

    echo "Connexion IMAP : OK\n";
    echo "Boîte : {$mailboxName}\n";
    echo "Messages : " . (int) $status->Nmsgs . "\n";
    echo "Messages récents : " . (int) $status->Recent . "\n";
    echo "Mode lecture seule : OK\n\n";

    echo "Aucun message modifié.\n";
    echo "Connexion IMAP validée.\n";
} finally {
    imap_close($connection);
}
