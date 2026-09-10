<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/ProspectMailer.php';

echo '=== TEST PROSPECT MAILER THREADING ==='
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;

function checkMailerThreading(
    bool $condition,
    string $label
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;

        echo '[OK]   '
            . $label
            . PHP_EOL;

        return;
    }

    $failed++;

    echo '[FAIL] '
        . $label
        . PHP_EOL;
}

$config = [
    'sending' => [
        'sender_email' =>
            'contact@olcreativestudio.fr',

        'sender_name' =>
            'OL Creative Studio',
    ],
];

$mailer =
    new ProspectMailer(
        $config
    );

$guardResult = [
    'allowed' => true,

    'recipient_email' =>
        'prospect@example.test',

    'effective_recipient_email' =>
        'prospect@example.test',

    'redirected' => false,
];

/*
|--------------------------------------------------------------------------
| Premier contact : aucun threading
|--------------------------------------------------------------------------
*/

$initialEmail = [
    'email_id' => 1,
    'prospect_id' => 1,

    'subject' =>
        'Votre presence en ligne',

    'body_text' =>
        'Bonjour, voici mon message.',
];

$initialMessage =
    $mailer->prepare(
        $initialEmail,
        $guardResult
    );

checkMailerThreading(
    array_key_exists(
        'in_reply_to',
        $initialMessage
    ),
    'Champ In-Reply-To present'
);

checkMailerThreading(
    $initialMessage['in_reply_to']
        === null,
    'Premier contact sans In-Reply-To'
);

checkMailerThreading(
    array_key_exists(
        'references',
        $initialMessage
    ),
    'Champ References present'
);

checkMailerThreading(
    $initialMessage['references']
        === [],
    'Premier contact sans References'
);

/*
|--------------------------------------------------------------------------
| Reponse : threading complet
|--------------------------------------------------------------------------
*/

$sourceMessageId =
    '<initial@example.test>';

$replyMessageId =
    '<incoming@example.test>';

$replyEmail = [
    'email_id' => 2,
    'prospect_id' => 1,

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, merci pour votre retour.',

    'source_message_id' =>
        $sourceMessageId,

    'reply_message_id' =>
        $replyMessageId,

    'reply_in_reply_to' =>
        $sourceMessageId,
];

$replyMessage =
    $mailer->prepare(
        $replyEmail,
        $guardResult
    );

checkMailerThreading(
    $replyMessage['in_reply_to']
        === $replyMessageId,
    'In-Reply-To utilise le Message-ID entrant'
);

checkMailerThreading(
    $replyMessage['references']
        === [
            $sourceMessageId,
            $replyMessageId,
        ],
    'References contient source puis reponse entrante'
);

/*
|--------------------------------------------------------------------------
| Fallback reply_in_reply_to
|--------------------------------------------------------------------------
*/

$fallbackEmail = [
    'email_id' => 3,
    'prospect_id' => 1,

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, voici ma reponse.',

    'reply_message_id' =>
        '<incoming-fallback@example.test>',

    'reply_in_reply_to' =>
        '<original-fallback@example.test>',
];

$fallbackMessage =
    $mailer->prepare(
        $fallbackEmail,
        $guardResult
    );

checkMailerThreading(
    $fallbackMessage['references']
        === [
            '<original-fallback@example.test>',
            '<incoming-fallback@example.test>',
        ],
    'Fallback threading utilise reply_in_reply_to'
);

/*
|--------------------------------------------------------------------------
| Message-ID de threading invalide
|--------------------------------------------------------------------------
*/

$invalidEmail = [
    'email_id' => 4,
    'prospect_id' => 1,

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, test.',

    'source_message_id' =>
        "invalid\r\nBcc: victim@example.test",

    'reply_message_id' =>
        "invalid\r\nBcc: victim@example.test",

    'reply_in_reply_to' =>
        'invalid',
];

$invalidMessage =
    $mailer->prepare(
        $invalidEmail,
        $guardResult
    );

checkMailerThreading(
    $invalidMessage['in_reply_to']
        === null,
    'In-Reply-To invalide ignore'
);

checkMailerThreading(
    $invalidMessage['references']
        === [],
    'References invalides ignorees'
);

/*
|--------------------------------------------------------------------------
| Source invalide mais Message-ID entrant valide
|--------------------------------------------------------------------------
*/

$partialEmail = [
    'email_id' => 5,
    'prospect_id' => 1,

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, test partiel.',

    'source_message_id' =>
        'invalid',

    'reply_message_id' =>
        '<incoming-valid@example.test>',

    'reply_in_reply_to' =>
        'invalid',
];

$partialMessage =
    $mailer->prepare(
        $partialEmail,
        $guardResult
    );

checkMailerThreading(
    $partialMessage['in_reply_to']
        === '<incoming-valid@example.test>',
    'Message-ID entrant valide conserve'
);

checkMailerThreading(
    $partialMessage['references']
        === [
            '<incoming-valid@example.test>',
        ],
    'References limitees au Message-ID valide'
);

echo PHP_EOL;

echo 'Tests reussis : '
    . $passed
    . PHP_EOL;

echo 'Tests echoues : '
    . $failed
    . PHP_EOL;

echo PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo 'Prospect Mailer Threading : OK'
    . PHP_EOL;
    