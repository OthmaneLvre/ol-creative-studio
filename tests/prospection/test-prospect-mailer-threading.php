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

/*
|--------------------------------------------------------------------------
| Follow-up 1 : thread sur le premier contact
|--------------------------------------------------------------------------
*/

$followUp1InitialMessageId =
    '<follow-up-initial@example.test>';

$followUp1Email = [
    'email_id' => 6,
    'prospect_id' => 1,
    'email_type' => 'follow_up_1',

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, je me permets de revenir vers vous.',

    'initial_message_id' =>
        $followUp1InitialMessageId,
];

$followUp1Message =
    $mailer->prepare(
        $followUp1Email,
        $guardResult
    );

checkMailerThreading(
    $followUp1Message['in_reply_to']
        === $followUp1InitialMessageId,
    'FU1 utilise le Message-ID initial en In-Reply-To'
);

checkMailerThreading(
    $followUp1Message['references']
        === [
            $followUp1InitialMessageId,
        ],
    'FU1 reference le premier contact'
);

/*
|--------------------------------------------------------------------------
| Follow-up 2 : thread sur FU1
|--------------------------------------------------------------------------
*/

$followUp2InitialMessageId =
    '<follow-up-2-initial@example.test>';

$followUp1MessageId =
    '<follow-up-1@example.test>';

$followUp2Email = [
    'email_id' => 7,
    'prospect_id' => 1,
    'email_type' => 'follow_up_2',

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, dernier message de ma part.',

    'initial_message_id' =>
        $followUp2InitialMessageId,

    'follow_up_1_message_id' =>
        $followUp1MessageId,
];

$followUp2Message =
    $mailer->prepare(
        $followUp2Email,
        $guardResult
    );

checkMailerThreading(
    $followUp2Message['in_reply_to']
        === $followUp1MessageId,
    'FU2 utilise le Message-ID FU1 en In-Reply-To'
);

checkMailerThreading(
    $followUp2Message['references']
        === [
            $followUp2InitialMessageId,
            $followUp1MessageId,
        ],
    'FU2 reference le premier contact puis FU1'
);

/*
|--------------------------------------------------------------------------
| Follow-up : parents obligatoires
|--------------------------------------------------------------------------
*/

$followUp1WithoutInitial = [
    'email_id' => 8,
    'prospect_id' => 1,
    'email_type' => 'follow_up_1',

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, test FU1 sans parent.',
];

$followUp1WithoutInitialRejected = false;

try {
    $mailer->prepare(
        $followUp1WithoutInitial,
        $guardResult
    );
} catch (RuntimeException) {
    $followUp1WithoutInitialRejected = true;
}

checkMailerThreading(
    $followUp1WithoutInitialRejected,
    'FU1 sans Message-ID initial refusee'
);

$followUp2WithoutInitial = [
    'email_id' => 9,
    'prospect_id' => 1,
    'email_type' => 'follow_up_2',

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, test FU2 sans initial.',

    'follow_up_1_message_id' =>
        '<follow-up-1-parent@example.test>',
];

$followUp2WithoutInitialRejected = false;

try {
    $mailer->prepare(
        $followUp2WithoutInitial,
        $guardResult
    );
} catch (RuntimeException) {
    $followUp2WithoutInitialRejected = true;
}

checkMailerThreading(
    $followUp2WithoutInitialRejected,
    'FU2 sans Message-ID initial refusee'
);

$followUp2WithoutFu1 = [
    'email_id' => 10,
    'prospect_id' => 1,
    'email_type' => 'follow_up_2',

    'subject' =>
        'Re: Votre presence en ligne',

    'body_text' =>
        'Bonjour, test FU2 sans FU1.',

    'initial_message_id' =>
        '<follow-up-2-parent@example.test>',
];

$followUp2WithoutFu1Rejected = false;

try {
    $mailer->prepare(
        $followUp2WithoutFu1,
        $guardResult
    );
} catch (RuntimeException) {
    $followUp2WithoutFu1Rejected = true;
}

checkMailerThreading(
    $followUp2WithoutFu1Rejected,
    'FU2 sans Message-ID FU1 refusee'
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
