<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ProspectReplySendGuard.php';

echo '=== TEST PROSPECT REPLY SEND GUARD ==='
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;

function check(
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
        'enabled' => true,
        'max_attempts' => 3,
        'local_redirect_enabled' => false,
        'local_redirect_email' => '',
    ],

    'compliance' => [
        'check_unsubscribe_list' => true,
    ],
];

$guard = new ProspectReplySendGuard(
    $pdo,
    $config
);

$uniqueEmail =
    'reply-guard-'
    . bin2hex(random_bytes(6))
    . '@example.test';

$baseEmail = [
    'recipient_email' =>
        $uniqueEmail,

    'reply_sender_email' =>
        $uniqueEmail,

    'email_type' =>
        'manual',

    'source_reply_id' =>
        123,

    'reply_classification' =>
        'positive',

    'status' =>
        'scheduled',

    'prospect_status' =>
        'replied',

    'send_attempts' =>
        0,
];

/*
|--------------------------------------------------------------------------
| Cas nominal
|--------------------------------------------------------------------------
*/

$result =
    $guard->evaluate(
        $baseEmail
    );

check(
    $result['allowed'] === true,
    'Reponse valide autorisee'
);

check(
    $result['reasons'] === [],
    'Aucune raison de blocage'
);

check(
    $result['recipient_email']
        === $uniqueEmail,
    'Destinataire original conserve'
);

check(
    $result['effective_recipient_email']
        === $uniqueEmail,
    'Destinataire effectif correct'
);

check(
    $result['redirected'] === false,
    'Pas de redirection locale'
);

/*
|--------------------------------------------------------------------------
| Etat sending autorise apres claim
|--------------------------------------------------------------------------
*/

$sendingEmail =
    $baseEmail;

$sendingEmail['status'] =
    'sending';

$result =
    $guard->evaluate(
        $sendingEmail
    );

check(
    $result['allowed'] === true,
    'Etat sending autorise'
);

/*
|--------------------------------------------------------------------------
| Mauvais type
|--------------------------------------------------------------------------
*/

$email =
    $baseEmail;

$email['email_type'] =
    'initial';

$result =
    $guard->evaluate(
        $email
    );

check(
    $result['allowed'] === false,
    'Email initial refuse'
);

/*
|--------------------------------------------------------------------------
| Reponse source absente
|--------------------------------------------------------------------------
*/

$email =
    $baseEmail;

$email['source_reply_id'] =
    null;

$result =
    $guard->evaluate(
        $email
    );

check(
    $result['allowed'] === false,
    'source_reply_id obligatoire'
);

/*
|--------------------------------------------------------------------------
| Mauvaise classification
|--------------------------------------------------------------------------
*/

foreach (
    [
        'negative',
        'automatic',
        'unsubscribed',
        'unknown',
    ]
    as $classification
) {
    $email =
        $baseEmail;

    $email['reply_classification'] =
        $classification;

    $result =
        $guard->evaluate(
            $email
        );

    check(
        $result['allowed'] === false,
        'Classification '
        . $classification
        . ' refusee'
    );
}

/*
|--------------------------------------------------------------------------
| Destinataire different de l'expediteur de la reponse
|--------------------------------------------------------------------------
*/

$email =
    $baseEmail;

$email['recipient_email'] =
    'other-' . $uniqueEmail;

$result =
    $guard->evaluate(
        $email
    );

check(
    $result['allowed'] === false,
    'Destinataire different de la reponse source refuse'
);

/*
|--------------------------------------------------------------------------
| Etat email invalide
|--------------------------------------------------------------------------
*/

$email =
    $baseEmail;

$email['status'] =
    'approved';

$result =
    $guard->evaluate(
        $email
    );

check(
    $result['allowed'] === false,
    'Etat approved refuse avant programmation'
);

/*
|--------------------------------------------------------------------------
| Prospect plus en replied
|--------------------------------------------------------------------------
*/

$email =
    $baseEmail;

$email['prospect_status'] =
    'converted';

$result =
    $guard->evaluate(
        $email
    );

check(
    $result['allowed'] === false,
    'Prospect converted refuse'
);

/*
|--------------------------------------------------------------------------
| Trop de tentatives
|--------------------------------------------------------------------------
*/

$email =
    $baseEmail;

$email['send_attempts'] =
    3;

$result =
    $guard->evaluate(
        $email
    );

check(
    $result['allowed'] === false,
    'Nombre maximal de tentatives respecte'
);

/*
|--------------------------------------------------------------------------
| Kill switch
|--------------------------------------------------------------------------
*/

$disabledConfig =
    $config;

$disabledConfig['sending']['enabled'] =
    false;

$disabledGuard =
    new ProspectReplySendGuard(
        $pdo,
        $disabledConfig
    );

$result =
    $disabledGuard->evaluate(
        $baseEmail
    );

check(
    $result['allowed'] === false,
    'Kill switch bloque l envoi'
);

/*
|--------------------------------------------------------------------------
| Redirection locale
|--------------------------------------------------------------------------
*/

$redirectConfig =
    $config;

$redirectConfig[
    'sending'
]['local_redirect_enabled'] =
    true;

$redirectConfig[
    'sending'
]['local_redirect_email'] =
    'local-test@example.test';

$redirectGuard =
    new ProspectReplySendGuard(
        $pdo,
        $redirectConfig
    );

$result =
    $redirectGuard->evaluate(
        $baseEmail
    );

check(
    $result['allowed'] === true,
    'Redirection locale autorisee'
);

check(
    $result['effective_recipient_email']
        === 'local-test@example.test',
    'Adresse de redirection appliquee'
);

check(
    $result['redirected'] === true,
    'Flag redirected actif'
);

/*
|--------------------------------------------------------------------------
| Desinscription
|--------------------------------------------------------------------------
*/

$pdo->beginTransaction();

try {
    $statement =
        $pdo->prepare(
            '
            INSERT INTO prospect_unsubscribes
            (
                email,
                reason,
                source
            )
            VALUES
            (
                :email,
                :reason,
                :source
            )
            '
        );

    $statement->execute([
        'email' =>
            $uniqueEmail,

        'reason' =>
            'reply guard test',

        'source' =>
            'manual',
    ]);

    $result =
        $guard->evaluate(
            $baseEmail
        );

    check(
        $result['allowed'] === false,
        'Adresse desinscrite refusee'
    );

    $pdo->rollBack();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

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

echo 'Prospect Reply Send Guard : OK'
    . PHP_EOL;
