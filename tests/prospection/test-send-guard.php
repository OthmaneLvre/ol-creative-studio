<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectSendGuard.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$guard = new ProspectSendGuard(
    $pdo,
    $config
);

$baseEmail = [
    'recipient_email' =>
        'contact@example.test',

    'send_attempts' =>
        0,

    'email_source_url' =>
        'https://example.test/contact',

    'prospect_status' =>
        'scheduled',
];

echo '=== TEST 1 : kill switch ==='
    . PHP_EOL;

$result = $guard->evaluate(
    $baseEmail
);

if ($result['allowed'] !== false) {
    throw new RuntimeException(
        'Le kill switch aurait dû bloquer l’envoi.'
    );
}

echo 'Bloqué : OK'
    . PHP_EOL;

foreach ($result['reasons'] as $reason) {
    echo '- ' . $reason . PHP_EOL;
}

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Configuration temporaire activée
|--------------------------------------------------------------------------
|
| On active seulement la copie locale du tableau.
| Le fichier config reste à false.
|
*/

$enabledConfig = $config;

$enabledConfig['sending']['enabled'] = true;

$enabledConfig[
    'sending'
]['local_redirect_enabled'] = false;

$enabledGuard = new ProspectSendGuard(
    $pdo,
    $enabledConfig
);

/*
|--------------------------------------------------------------------------
| TEST 2 : e-mail autorisé
|--------------------------------------------------------------------------
*/

echo '=== TEST 2 : email autorisé ==='
    . PHP_EOL;

$result = $enabledGuard->evaluate(
    $baseEmail
);

if ($result['allowed'] !== true) {
    throw new RuntimeException(
        'L’e-mail valide aurait dû être autorisé : '
        . implode(
            ', ',
            $result['reasons']
        )
    );
}

echo 'Autorisé : OK'
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| TEST 3 : source publique absente
|--------------------------------------------------------------------------
*/

echo '=== TEST 3 : source absente ==='
    . PHP_EOL;

$withoutSource = $baseEmail;

$withoutSource['email_source_url'] = null;

$result = $enabledGuard->evaluate(
    $withoutSource
);

if ($result['allowed'] !== false) {
    throw new RuntimeException(
        'L’absence de source aurait dû bloquer l’envoi.'
    );
}

echo 'Bloqué : OK'
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| TEST 4 : nombre maximal de tentatives
|--------------------------------------------------------------------------
*/

echo '=== TEST 4 : max tentatives ==='
    . PHP_EOL;

$maxAttempts = $baseEmail;

$maxAttempts['send_attempts'] =
    (int) (
        $enabledConfig[
            'sending'
        ]['max_attempts']
        ?? 3
    );

$result = $enabledGuard->evaluate(
    $maxAttempts
);

if ($result['allowed'] !== false) {
    throw new RuntimeException(
        'Le nombre maximal de tentatives '
        . 'aurait dû bloquer l’envoi.'
    );
}

echo 'Bloqué : OK'
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| TEST 5 : prospect non programmé
|--------------------------------------------------------------------------
*/

echo '=== TEST 5 : mauvais statut ==='
    . PHP_EOL;

$wrongStatus = $baseEmail;

$wrongStatus['prospect_status'] =
    'approved';

$result = $enabledGuard->evaluate(
    $wrongStatus
);

if ($result['allowed'] !== false) {
    throw new RuntimeException(
        'Un prospect non programmé '
        . 'aurait dû être bloqué.'
    );
}

echo 'Bloqué : OK'
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| TEST 6 : désinscription
|--------------------------------------------------------------------------
*/

echo '=== TEST 6 : désinscription ==='
    . PHP_EOL;

$testEmail =
    'send-guard-unsubscribe@example.test';

$insert = $pdo->prepare(
    '
    INSERT INTO prospect_unsubscribes (
        email,
        reason,
        source
    ) VALUES (
        :email,
        :reason,
        :source
    )
    '
);

try {
    $insert->execute([
        'email' => $testEmail,
        'reason' => 'Test automatique',
        'source' => 'manual',
    ]);

    $unsubscribedEmail = $baseEmail;

    $unsubscribedEmail[
        'recipient_email'
    ] = $testEmail;

    $result = $enabledGuard->evaluate(
        $unsubscribedEmail
    );

    if ($result['allowed'] !== false) {
        throw new RuntimeException(
            'Une adresse désinscrite '
            . 'aurait dû être bloquée.'
        );
    }

    echo 'Bloqué : OK'
        . PHP_EOL;
} finally {
    $delete = $pdo->prepare(
        '
        DELETE FROM prospect_unsubscribes
        WHERE email = :email
        '
    );

    $delete->execute([
        'email' => $testEmail,
    ]);

    echo 'Désinscription temporaire supprimée.'
        . PHP_EOL;
}

echo PHP_EOL;

echo '=== TEST 7 : redirection locale ==='
    . PHP_EOL;

$redirectConfig = $config;

$redirectConfig['sending']['enabled'] =
    true;

$redirectConfig[
    'sending'
]['local_redirect_enabled'] =
    true;

$redirectConfig[
    'sending'
]['local_redirect_email'] =
    'local-test@example.test';

$redirectGuard =
    new ProspectSendGuard(
        $pdo,
        $redirectConfig
    );

$result = $redirectGuard->evaluate(
    $baseEmail
);

if ($result['allowed'] !== true) {
    throw new RuntimeException(
        'La redirection locale valide '
        . 'aurait dû être autorisée.'
    );
}

if (
    $result[
        'effective_recipient_email'
    ] !== 'local-test@example.test'
) {
    throw new RuntimeException(
        'Le destinataire effectif '
        . 'n’est pas la boîte de test.'
    );
}

if ($result['redirected'] !== true) {
    throw new RuntimeException(
        'La redirection locale '
        . 'aurait dû être détectée.'
    );
}

echo 'Redirection : OK'
    . PHP_EOL;

echo PHP_EOL;

echo '=== TEST 8 : redirection locale invalide ==='
    . PHP_EOL;

$invalidRedirectConfig =
    $redirectConfig;

$invalidRedirectConfig[
    'sending'
]['local_redirect_email'] =
    null;

$invalidRedirectGuard =
    new ProspectSendGuard(
        $pdo,
        $invalidRedirectConfig
    );

$result =
    $invalidRedirectGuard->evaluate(
        $baseEmail
    );

if ($result['allowed'] !== false) {
    throw new RuntimeException(
        'Une redirection locale absente '
        . 'aurait dû bloquer l’envoi.'
    );
}

echo 'Blocage sans adresse de test : OK'
    . PHP_EOL;

echo PHP_EOL;

echo 'Tous les tests du SendGuard sont OK.'
    . PHP_EOL;