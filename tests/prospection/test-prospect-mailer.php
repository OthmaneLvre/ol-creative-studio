<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/ProspectMailer.php';

$config = [
    'sending' => [
        'sender_email' =>
            'contact@olcreativestudio.fr',

        'sender_name' =>
            'OL Creative Studio',
    ],
];

$mailer = new ProspectMailer(
    $config
);

$email = [
    'email_id' =>
        123,

    'prospect_id' =>
        456,

    'subject' =>
        'Quelques pistes pour votre présence en ligne',

    'body_text' =>
        "Bonjour,\n\n"
        . "Je me permets de vous contacter "
        . "au sujet de votre présence en ligne.",

    'body_html' =>
        '<p>Bonjour,</p>'
        . '<p>'
        . 'Je me permets de vous contacter '
        . 'au sujet de votre présence en ligne.'
        . '</p>',
];

$guardResult = [
    'allowed' =>
        true,

    'recipient_email' =>
        'artisan@example.test',

    'effective_recipient_email' =>
        'local-test@example.test',

    'redirected' =>
        true,
];

echo '=== TEST 1 : préparation ==='
    . PHP_EOL;

$message = $mailer->prepare(
    $email,
    $guardResult
);

if (
    $message['to_email']
    !== 'local-test@example.test'
) {
    throw new RuntimeException(
        'Le destinataire effectif '
        . 'n’a pas été utilisé.'
    );
}

if (
    $message['original_recipient_email']
    !== 'artisan@example.test'
) {
    throw new RuntimeException(
        'Le destinataire original '
        . 'n’a pas été conservé.'
    );
}

if ($message['redirected'] !== true) {
    throw new RuntimeException(
        'La redirection aurait dû '
        . 'être conservée.'
    );
}

if (
    $message['transport']
    !== 'preview'
) {
    throw new RuntimeException(
        'Le transport doit rester '
        . 'en mode preview.'
    );
}

echo 'Préparation : OK'
    . PHP_EOL;

echo PHP_EOL;

echo '=== TEST 2 : conformité texte ==='
    . PHP_EOL;

if (
    stripos(
        $message['body_text'],
        'STOP'
    ) === false
) {
    throw new RuntimeException(
        'Instruction de désinscription '
        . 'absente du texte.'
    );
}

if (
    stripos(
        $message['body_text'],
        'OL Creative Studio'
    ) === false
) {
    throw new RuntimeException(
        'Identification OL Creative Studio '
        . 'absente du texte.'
    );
}

echo 'Footer texte : OK'
    . PHP_EOL;

echo PHP_EOL;

echo '=== TEST 3 : conformité HTML ==='
    . PHP_EOL;

if (
    stripos(
        $message['body_html'],
        'STOP'
    ) === false
) {
    throw new RuntimeException(
        'Instruction de désinscription '
        . 'absente du HTML.'
    );
}

if (
    stripos(
        $message['body_html'],
        'OL Creative Studio'
    ) === false
) {
    throw new RuntimeException(
        'Identification OL Creative Studio '
        . 'absente du HTML.'
    );
}

echo 'Footer HTML : OK'
    . PHP_EOL;

echo PHP_EOL;

echo '=== TEST 4 : Message-ID ==='
    . PHP_EOL;

if (
    preg_match(
        '/^<prospection-[^@]+@olcreativestudio\.fr>$/',
        $message['message_id']
    ) !== 1
) {
    throw new RuntimeException(
        'Message-ID invalide : '
        . $message['message_id']
    );
}

echo 'Message-ID : OK'
    . PHP_EOL;

echo PHP_EOL;

echo '=== TEST 5 : SendGuard refusé ==='
    . PHP_EOL;

$blockedGuard = $guardResult;

$blockedGuard['allowed'] = false;

try {
    $mailer->prepare(
        $email,
        $blockedGuard
    );

    throw new RuntimeException(
        'Un message bloqué par SendGuard '
        . 'a été préparé.'
    );
} catch (RuntimeException $exception) {
    if (
        !str_contains(
            $exception->getMessage(),
            'SendGuard'
        )
    ) {
        throw $exception;
    }
}

echo 'Blocage : OK'
    . PHP_EOL;

echo PHP_EOL;

echo 'Tous les tests du ProspectMailer sont OK.'
    . PHP_EOL;
    