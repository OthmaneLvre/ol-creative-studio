<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/ProspectMailTransport.php';

echo '=== TEST PROSPECT MAIL TRANSPORT THREADING ==='
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;

function checkMailTransport(
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

function expectMailTransportException(
    callable $callback,
    string $label
): void {
    try {
        $callback();

        checkMailTransport(
            false,
            $label
        );
    } catch (InvalidArgumentException) {
        checkMailTransport(
            true,
            $label
        );
    }
}

$transport =
    new ProspectMailTransport([
        'host' => '',
        'username' => '',
        'password' => '',
        'port' => 465,
        'encryption' => 'ssl',
    ]);

$baseMessage = [
    'from_email' =>
        'contact@olcreativestudio.fr',

    'from_name' =>
        'OL Creative Studio',

    'to_email' =>
        'prospect@example.test',

    'subject' =>
        'Re: Votre presence en ligne',

    'body_html' =>
        '<p>Bonjour.</p>',

    'body_text' =>
        'Bonjour.',

    'message_id' =>
        '<prospection-test@olcreativestudio.fr>',
];

/*
|--------------------------------------------------------------------------
| Aucun threading
|--------------------------------------------------------------------------
*/

$result =
    $transport->send(
        $baseMessage
    );

checkMailTransport(
    $result['success'] === false,
    'Message sans threading accepte par la validation'
);

checkMailTransport(
    $result['error']
        === 'Configuration SMTP incomplète.',
    'Validation atteinte avant controle SMTP'
);

/*
|--------------------------------------------------------------------------
| Threading valide
|--------------------------------------------------------------------------
*/

$message =
    $baseMessage;

$message['in_reply_to'] =
    '<incoming@example.test>';

$message['references'] = [
    '<initial@example.test>',
    '<incoming@example.test>',
];

$result =
    $transport->send(
        $message
    );

checkMailTransport(
    $result['success'] === false,
    'Threading valide accepte'
);

checkMailTransport(
    $result['error']
        === 'Configuration SMTP incomplète.',
    'Threading valide atteint le controle SMTP'
);

/*
|--------------------------------------------------------------------------
| In-Reply-To invalide
|--------------------------------------------------------------------------
*/

$message =
    $baseMessage;

$message['in_reply_to'] =
    "invalid\r\nBcc: victim@example.test";

expectMailTransportException(
    fn () =>
        $transport->send(
            $message
        ),
    'In-Reply-To avec injection refuse'
);

/*
|--------------------------------------------------------------------------
| References non tableau
|--------------------------------------------------------------------------
*/

$message =
    $baseMessage;

$message['references'] =
    '<initial@example.test>';

expectMailTransportException(
    fn () =>
        $transport->send(
            $message
        ),
    'References non tableau refuse'
);

/*
|--------------------------------------------------------------------------
| Reference invalide
|--------------------------------------------------------------------------
*/

$message =
    $baseMessage;

$message['references'] = [
    '<initial@example.test>',
    "invalid\r\nBcc: victim@example.test",
];

expectMailTransportException(
    fn () =>
        $transport->send(
            $message
        ),
    'Reference avec injection refuse'
);

/*
|--------------------------------------------------------------------------
| Reference non string
|--------------------------------------------------------------------------
*/

$message =
    $baseMessage;

$message['references'] = [
    '<initial@example.test>',
    123,
];

expectMailTransportException(
    fn () =>
        $transport->send(
            $message
        ),
    'Reference non string refuse'
);

/*
|--------------------------------------------------------------------------
| Message-ID principal invalide
|--------------------------------------------------------------------------
*/

$message =
    $baseMessage;

$message['message_id'] =
    'invalid';

expectMailTransportException(
    fn () =>
        $transport->send(
            $message
        ),
    'Message-ID principal invalide refuse'
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

echo 'Prospect Mail Transport Threading : OK'
    . PHP_EOL;
