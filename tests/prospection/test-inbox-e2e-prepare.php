<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectSendGuard.php';

require_once __DIR__
    . '/../../php/prospection/ProspectMailer.php';

require_once __DIR__
    . '/../../php/prospection/ProspectMailTransport.php';

$mailConfig = require __DIR__
    . '/../../config/mail.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$testEmail = strtolower(
    trim(
        (string) (
            $_ENV['PROSPECTION_TEST_EMAIL']
            ?? ''
        )
    )
);

if (
    filter_var(
        $testEmail,
        FILTER_VALIDATE_EMAIL
    ) === false
) {
    throw new RuntimeException(
        'PROSPECTION_TEST_EMAIL est absente ou invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| Sécurité du test
|--------------------------------------------------------------------------
*/

$config['sending']['enabled'] = true;

$config['sending']['local_redirect_enabled'] = true;

$config['sending']['local_redirect_email'] =
    $testEmail;

$config['sending']['max_attempts'] = 3;

if (
    empty($mailConfig['host'])
    || empty($mailConfig['username'])
    || empty($mailConfig['password'])
) {
    throw new RuntimeException(
        'Configuration SMTP incomplète.'
    );
}

echo PHP_EOL;
echo '========================================' . PHP_EOL;
echo 'TEST E2E INBOX - PREPARATION' . PHP_EOL;
echo '========================================' . PHP_EOL;
echo 'Destinataire : ' . $testEmail . PHP_EOL;
echo PHP_EOL;

$repository = new ProspectRepository($pdo);

$sendGuard = new ProspectSendGuard(
    $pdo,
    $config
);

$mailer = new ProspectMailer(
    $config
);

$transport = new ProspectMailTransport(
    $mailConfig
);

$scheduledAt =
    (new DateTimeImmutable('-5 minutes'))
        ->format('Y-m-d H:i:s');

/*
|--------------------------------------------------------------------------
| Prospect fictif persistant
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare(
    '
    INSERT INTO prospects (
        company_name,
        activity,
        city,
        postal_code,
        email,
        email_source_url,
        email_type,
        email_confidence,
        source,
        source_external_id,
        status,
        scheduled_at
    ) VALUES (
        :company_name,
        :activity,
        :city,
        :postal_code,
        :email,
        :email_source_url,
        :email_type,
        :email_confidence,
        :source,
        :source_external_id,
        :status,
        :scheduled_at
    )
    '
);

$statement->execute([
    'company_name' =>
        'TEST E2E INBOX OL CREATIVE STUDIO',

    'activity' =>
        'TEST',

    'city' =>
        'Céret',

    'postal_code' =>
        '66400',

    'email' =>
        'do-not-send@example.com',

    'email_source_url' =>
        'https://example.com/contact',

    'email_type' =>
        'generic',

    'email_confidence' =>
        100,

    'source' =>
        'inbox_e2e_test',

    'source_external_id' =>
        'inbox-e2e-'
        . bin2hex(random_bytes(12)),

    'status' =>
        'scheduled',

    'scheduled_at' =>
        $scheduledAt,
]);

$prospectId =
    (int) $pdo->lastInsertId();

/*
|--------------------------------------------------------------------------
| E-mail fictif
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare(
    '
    INSERT INTO prospect_emails (
        prospect_id,
        email_type,
        recipient_email,
        subject,
        body_html,
        body_text,
        status,
        approved_at,
        scheduled_at,
        send_attempts
    ) VALUES (
        :prospect_id,
        :email_type,
        :recipient_email,
        :subject,
        :body_html,
        :body_text,
        :status,
        :approved_at,
        :scheduled_at,
        0
    )
    '
);

$statement->execute([
    'prospect_id' =>
        $prospectId,

    'email_type' =>
        'initial',

    'recipient_email' =>
        'do-not-send@example.com',

    'subject' =>
        '[TEST E2E] Réponse prospection OL Creative Studio',

    'body_html' =>
        '<p>Bonjour,</p>'
        . '<p>Ceci est un test contrôlé '
        . 'du système de réponse de '
        . 'OL Creative Studio.</p>'
        . '<p>Merci de répondre directement '
        . 'à cet e-mail depuis Gmail.</p>',

    'body_text' =>
        "Bonjour,\n\n"
        . "Ceci est un test contrôlé du système "
        . "de réponse de OL Creative Studio.\n\n"
        . "Merci de répondre directement "
        . "à cet e-mail depuis Gmail.",

    'status' =>
        'scheduled',

    'approved_at' =>
        (new DateTimeImmutable('-1 day'))
            ->format('Y-m-d H:i:s'),

    'scheduled_at' =>
        $scheduledAt,
]);

$emailId =
    (int) $pdo->lastInsertId();

/*
|--------------------------------------------------------------------------
| Récupération de l'e-mail
|--------------------------------------------------------------------------
*/

$dueEmails =
    $repository
        ->findDueScheduledInitialEmails(
            new DateTimeImmutable(),
            10
        );

$email = null;

foreach ($dueEmails as $dueEmail) {
    if (
        (int) $dueEmail['email_id']
        === $emailId
    ) {
        $email = $dueEmail;
        break;
    }
}

if ($email === null) {
    throw new RuntimeException(
        'Fixture E2E introuvable.'
    );
}

/*
|--------------------------------------------------------------------------
| SendGuard
|--------------------------------------------------------------------------
*/

$guardResult =
    $sendGuard->evaluate($email);

if (!$guardResult['allowed']) {
    throw new RuntimeException(
        'SendGuard : '
        . implode(
            ' | ',
            $guardResult['reasons']
        )
    );
}

if (
    $guardResult['effective_recipient_email']
    !== $testEmail
) {
    throw new RuntimeException(
        'Redirection vers le destinataire '
        . 'de test inactive.'
    );
}

if (
    !$guardResult['redirected']
) {
    throw new RuntimeException(
        'Le test refuse tout envoi direct.'
    );
}

/*
|--------------------------------------------------------------------------
| Préparation
|--------------------------------------------------------------------------
*/

$message =
    $mailer->prepare(
        $email,
        $guardResult
    );

echo 'Prospect test : #'
    . $prospectId
    . PHP_EOL;

echo 'Email test : #'
    . $emailId
    . PHP_EOL;

echo 'Message-ID : '
    . $message['message_id']
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Claim
|--------------------------------------------------------------------------
*/

$repository
    ->claimScheduledInitialEmail(
        $emailId,
        (string) $message['message_id'],
        3
    );

/*
|--------------------------------------------------------------------------
| SMTP réel
|--------------------------------------------------------------------------
*/

echo 'Envoi SMTP...' . PHP_EOL;

$transportResult =
    $transport->send($message);

if (
    ($transportResult['success'] ?? false)
    !== true
) {
    $error =
        trim(
            (string) (
                $transportResult['error']
                ?? 'Échec SMTP inconnu.'
            )
        );

    $repository
        ->markInitialEmailFailed(
            $emailId,
            $error,
            3,
            30
        );

    throw new RuntimeException(
        'SMTP : ' . $error
    );
}

$smtpMessageId =
    trim(
        (string) (
            $transportResult['message_id']
            ?? ''
        )
    );

if (
    $smtpMessageId === ''
    || $smtpMessageId
        !== $message['message_id']
) {
    throw new RuntimeException(
        'Message-ID SMTP différent '
        . 'du Message-ID préparé.'
    );
}

$repository
    ->markInitialEmailSent(
        $emailId,
        (string) $message['message_id']
    );

echo 'SMTP : OK' . PHP_EOL;
echo 'Message-ID persisté : OK' . PHP_EOL;

echo PHP_EOL;
echo '========================================' . PHP_EOL;
echo 'FIXTURE CONSERVEE EN BDD' . PHP_EOL;
echo '========================================' . PHP_EOL;

echo 'Prospect ID : '
    . $prospectId
    . PHP_EOL;

echo 'Email ID : '
    . $emailId
    . PHP_EOL;

echo 'Message-ID : '
    . $message['message_id']
    . PHP_EOL;

echo PHP_EOL;

echo 'Réponds maintenant à cet e-mail depuis :'
    . PHP_EOL;

echo $testEmail . PHP_EOL;

echo PHP_EOL;

echo 'IMPORTANT : ne supprime pas la fixture '
    . 'avant le test IMAP.'
    . PHP_EOL;
    