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

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
|
| config/mail.php charge également le .env.
|
*/

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
| Sécurité absolue du test
|--------------------------------------------------------------------------
|
| On n'utilise PAS PROSPECTION_SENDING_ENABLED.
|
| La copie locale est forcée à true uniquement dans ce processus.
| La redirection est également forcée.
|
*/

$config['sending']['enabled'] = true;

$config['sending']['local_redirect_enabled'] =
    true;

$config['sending']['local_redirect_email'] =
    $testEmail;

$config['sending']['max_attempts'] =
    3;

/*
|--------------------------------------------------------------------------
| Vérification SMTP
|--------------------------------------------------------------------------
*/

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

echo '========================================'
    . PHP_EOL;

echo 'TEST SMTP RÉEL - PROSPECTION'
    . PHP_EOL;

echo '========================================'
    . PHP_EOL;

echo 'Serveur SMTP : '
    . (string) $mailConfig['host']
    . ':'
    . (int) $mailConfig['port']
    . PHP_EOL;

echo 'Destinataire de test : '
    . $testEmail
    . PHP_EOL;

echo 'Redirection forcée : OUI'
    . PHP_EOL;

echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Services
|--------------------------------------------------------------------------
*/

$repository =
    new ProspectRepository(
        $pdo
    );

$sendGuard =
    new ProspectSendGuard(
        $pdo,
        $config
    );

$mailer =
    new ProspectMailer(
        $config
    );

$transport =
    new ProspectMailTransport(
        $mailConfig
    );

/*
|--------------------------------------------------------------------------
| Fixture
|--------------------------------------------------------------------------
*/

$prospectId = null;
$emailId = null;

$fakeRecipient =
    'do-not-send@example.com';

try {
    $scheduledAt =
        (new DateTimeImmutable('-5 minutes'))
            ->format('Y-m-d H:i:s');

    /*
    |--------------------------------------------------------------------------
    | Prospect temporaire
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
            'TEST SMTP OL CREATIVE STUDIO',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            $fakeRecipient,

        'email_source_url' =>
            'https://example.com/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'smtp_test',

        'source_external_id' =>
            'smtp-test-'
            . bin2hex(
                random_bytes(12)
            ),

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $scheduledAt,
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | E-mail temporaire
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
            $fakeRecipient,

        'subject' =>
            '[TEST] Agent de prospection OL Creative Studio',

        'body_html' =>
            '<p>Bonjour,</p>'
            . '<p>Ceci est un test SMTP contrôlé '
            . 'de l’agent de prospection OL Creative Studio.</p>'
            . '<p>Aucun prospect réel n’est destinataire '
            . 'de ce message.</p>',

        'body_text' =>
            "Bonjour,\n\n"
            . "Ceci est un test SMTP contrôlé "
            . "de l'agent de prospection OL Creative Studio.\n\n"
            . "Aucun prospect réel n'est destinataire "
            . "de ce message.",

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

    echo 'Fixture créée : OK'
        . PHP_EOL;

    echo 'Prospect temporaire : #'
        . $prospectId
        . PHP_EOL;

    echo 'Email temporaire : #'
        . $emailId
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Recherche de la fixture
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
            'La fixture SMTP n’a pas été retrouvée.'
        );
    }

    echo 'Fixture détectée parmi les e-mails dus : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | SendGuard
    |--------------------------------------------------------------------------
    */

    $guardResult =
        $sendGuard->evaluate(
            $email
        );

    if (!$guardResult['allowed']) {
        throw new RuntimeException(
            'SendGuard : '
            . implode(
                ' | ',
                $guardResult['reasons']
            )
        );
    }

    echo 'SendGuard : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Vérifications anti-envoi accidentel
    |--------------------------------------------------------------------------
    */

    if (
        $guardResult[
            'recipient_email'
        ] !== $fakeRecipient
    ) {
        throw new RuntimeException(
            'Destinataire réel inattendu.'
        );
    }

    if (
        $guardResult[
            'effective_recipient_email'
        ] !== $testEmail
    ) {
        throw new RuntimeException(
            'La redirection SMTP n’est pas active.'
        );
    }

    if (
        $guardResult[
            'effective_recipient_email'
        ] === $guardResult[
            'recipient_email'
        ]
    ) {
        throw new RuntimeException(
            'Le test refuse tout envoi direct.'
        );
    }

    if (
        !$guardResult['redirected']
    ) {
        throw new RuntimeException(
            'Le SendGuard ne signale pas la redirection.'
        );
    }

    echo 'Destinataire réel : '
        . $guardResult[
            'recipient_email'
        ]
        . PHP_EOL;

    echo 'Destinataire SMTP : '
        . $guardResult[
            'effective_recipient_email'
        ]
        . PHP_EOL;

    echo 'Protection redirection : OK'
        . PHP_EOL;

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

    if (
        $message['to_email']
        !== $testEmail
    ) {
        throw new RuntimeException(
            'ProspectMailer n’utilise pas '
            . 'le destinataire de test.'
        );
    }

    if (
        $message['original_recipient_email']
        !== $fakeRecipient
    ) {
        throw new RuntimeException(
            'Destinataire original incorrect.'
        );
    }

    echo 'Message préparé : OK'
        . PHP_EOL;

    echo 'Message-ID : '
        . $message['message_id']
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Claim avant SMTP
    |--------------------------------------------------------------------------
    */

    $repository
        ->claimScheduledInitialEmail(
            $emailId,
            (string) $message[
                'message_id'
            ],
            3
        );

    echo 'Claim scheduled -> sending : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | ENVOI SMTP RÉEL
    |--------------------------------------------------------------------------
    |
    | C'est le seul endroit où le réseau SMTP est appelé.
    |
    */

    echo PHP_EOL;

    echo 'Envoi SMTP en cours...'
        . PHP_EOL;

    $transportResult =
        $transport->send(
            $message
        );

    /*
    |--------------------------------------------------------------------------
    | Échec SMTP
    |--------------------------------------------------------------------------
    */

    if (
        ($transportResult['success'] ?? false)
        !== true
    ) {
        $errorMessage = trim(
            (string) (
                $transportResult['error']
                ?? 'Échec SMTP inconnu.'
            )
        );

        $repository
            ->markInitialEmailFailed(
                $emailId,
                $errorMessage,
                3,
                30
            );

        throw new RuntimeException(
            'SMTP : '
            . $errorMessage
        );
    }

    echo 'SMTP accepté : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Vérification Message-ID
    |--------------------------------------------------------------------------
    */

    $smtpMessageId = trim(
        (string) (
            $transportResult[
                'message_id'
            ]
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
            . 'du Message-ID réservé.'
        );
    }

    echo 'Message-ID SMTP : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Persistance sent/contacted
    |--------------------------------------------------------------------------
    */

    $repository
        ->markInitialEmailSent(
            $emailId,
            (string) $message[
                'message_id'
            ]
        );

    echo 'État sent/contacted : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Vérification finale BDD
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.sent_at,
            e.message_id,
            e.send_attempts,
            p.status AS prospect_status,
            p.contacted_at
        FROM prospect_emails e
        INNER JOIN prospects p
            ON p.id = e.prospect_id
        WHERE e.id = :email_id
        LIMIT 1
        '
    );

    $statement->execute([
        'email_id' =>
            $emailId,
    ]);

    $state =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if ($state === false) {
        throw new RuntimeException(
            'État final introuvable.'
        );
    }

    if (
        $state['email_status']
        !== 'sent'
    ) {
        throw new RuntimeException(
            'E-mail non marqué sent.'
        );
    }

    if (
        $state['prospect_status']
        !== 'contacted'
    ) {
        throw new RuntimeException(
            'Prospect non marqué contacted.'
        );
    }

    if (
        (int) $state['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'Le compteur de tentatives '
            . 'devrait être égal à 1.'
        );
    }

    if (
        empty($state['sent_at'])
        || empty($state['contacted_at'])
    ) {
        throw new RuntimeException(
            'Dates finales absentes.'
        );
    }

    if (
        $state['message_id']
        !== $message['message_id']
    ) {
        throw new RuntimeException(
            'Message-ID final incorrect.'
        );
    }

    echo PHP_EOL;

    echo '========================================'
        . PHP_EOL;

    echo 'TEST SMTP RÉEL : OK'
        . PHP_EOL;

    echo '========================================'
        . PHP_EOL;

    echo 'Le message doit avoir été reçu sur :'
        . PHP_EOL;

    echo $testEmail
        . PHP_EOL;

    echo PHP_EOL;

} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage de la fixture
    |--------------------------------------------------------------------------
    */

    if ($prospectId !== null) {
        $statement = $pdo->prepare(
            '
            DELETE FROM prospect_events
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        $statement = $pdo->prepare(
            '
            DELETE FROM prospect_emails
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        $statement = $pdo->prepare(
            '
            DELETE FROM prospects
            WHERE id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        echo 'Fixture temporaire supprimée : OK'
            . PHP_EOL;
    }
}
