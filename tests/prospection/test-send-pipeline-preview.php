<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectSendGuard.php';

require_once __DIR__
    . '/../../php/prospection/ProspectMailer.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$repository = new ProspectRepository(
    $pdo
);

$testConfig = $config;

$testConfig['sending']['enabled'] = true;

$testConfig[
    'sending'
]['local_redirect_enabled'] = true;

$testConfig[
    'sending'
]['local_redirect_email'] =
    'pipeline-preview@example.test';

$guard = new ProspectSendGuard(
    $pdo,
    $testConfig
);

$mailer = new ProspectMailer(
    $testConfig
);

$timezone = new DateTimeZone(
    'Europe/Paris'
);

$now = new DateTimeImmutable(
    '2026-08-30 14:00:00',
    $timezone
);

$prospectId = null;

try {
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
            'TEST SEND PIPELINE',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            'artisan-pipeline@example.test',

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'send_pipeline_test',

        'source_external_id' =>
            'send-pipeline-'
            . bin2hex(random_bytes(8)),

        'status' =>
            'scheduled',

        'scheduled_at' =>
            '2026-08-30 13:00:00',
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
            :send_attempts
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            'artisan-pipeline@example.test',

        'subject' =>
            'Test pipeline preview',

        'body_html' =>
            '<p>Bonjour, ceci est un test.</p>',

        'body_text' =>
            'Bonjour, ceci est un test.',

        'status' =>
            'scheduled',

        'approved_at' =>
            '2026-08-29 10:00:00',

        'scheduled_at' =>
            '2026-08-30 13:00:00',

        'send_attempts' =>
            0,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Sélection
    |--------------------------------------------------------------------------
    */

    $emails =
        $repository->findDueScheduledInitialEmails(
            $now,
            5
        );

    $testEmail = null;

    foreach ($emails as $email) {
        if (
            (int) $email['prospect_id']
            === $prospectId
        ) {
            $testEmail = $email;
            break;
        }
    }

    if ($testEmail === null) {
        throw new RuntimeException(
            'E-mail dû non sélectionné.'
        );
    }

    echo 'Sélection : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | SendGuard
    |--------------------------------------------------------------------------
    */

    $guardResult =
        $guard->evaluate(
            $testEmail
        );

    if ($guardResult['allowed'] !== true) {
        throw new RuntimeException(
            'SendGuard a bloqué le test : '
            . implode(
                ', ',
                $guardResult['reasons']
            )
        );
    }

    echo 'SendGuard : OK'
        . PHP_EOL;

    if (
        $guardResult[
            'effective_recipient_email'
        ] !== 'pipeline-preview@example.test'
    ) {
        throw new RuntimeException(
            'La redirection locale '
            . 'n’a pas été appliquée.'
        );
    }

    echo 'Redirection locale : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Préparation message
    |--------------------------------------------------------------------------
    */

    $message = $mailer->prepare(
        $testEmail,
        $guardResult
    );

    if (
        $message['transport']
        !== 'preview'
    ) {
        throw new RuntimeException(
            'Le transport ne doit pas '
            . 'quitter le mode preview.'
        );
    }

    if (
        $message['to_email']
        !== 'pipeline-preview@example.test'
    ) {
        throw new RuntimeException(
            'Le mailer n’utilise pas '
            . 'le destinataire effectif.'
        );
    }

    if (
        stripos(
            $message['body_text'],
            'STOP'
        ) === false
    ) {
        throw new RuntimeException(
            'Footer de désinscription absent.'
        );
    }

    if (
        stripos(
            $message['body_text'],
            'OL Creative Studio'
        ) === false
    ) {
        throw new RuntimeException(
            'Identification expéditeur absente.'
        );
    }

    echo 'Préparation message : OK'
        . PHP_EOL;

    echo 'Message-ID : '
        . $message['message_id']
        . PHP_EOL;

    echo 'Destinataire effectif : '
        . $message['to_email']
        . PHP_EOL;

    echo 'Transport : '
        . $message['transport']
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Pipeline preview complet : OK'
        . PHP_EOL;

} finally {
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

        echo 'Données temporaires supprimées.'
            . PHP_EOL;
    }
}
