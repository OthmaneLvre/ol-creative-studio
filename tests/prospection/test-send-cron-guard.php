<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectSendGuard.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$repository = new ProspectRepository(
    $pdo
);

$guard = new ProspectSendGuard(
    $pdo,
    $config
);

$timezone = new DateTimeZone(
    'Europe/Paris'
);

$now = new DateTimeImmutable(
    '2026-08-30 13:30:00',
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

    $recipientEmail =
        'cron-guard-test@example.test';

    $statement->execute([
        'company_name' =>
            'TEST CRON GUARD',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            $recipientEmail,

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'cron_guard_test',

        'source_external_id' =>
            'cron-guard-'
            . bin2hex(random_bytes(8)),

        'status' =>
            'scheduled',

        'scheduled_at' =>
            '2026-08-30 12:00:00',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | E-mail temporaire dû
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
            $recipientEmail,

        'subject' =>
            'Test cron guard',

        'body_html' =>
            '<p>Test</p>',

        'body_text' =>
            'Test',

        'status' =>
            'scheduled',

        'approved_at' =>
            '2026-08-29 10:00:00',

        'scheduled_at' =>
            '2026-08-30 12:00:00',

        'send_attempts' =>
            0,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Même sélection que le cron
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
            'L’e-mail temporaire dû '
            . 'n’a pas été sélectionné.'
        );
    }

    echo 'E-mail dû détecté : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Kill switch réel
    |--------------------------------------------------------------------------
    */

    $result =
        $guard->evaluate($testEmail);

    if ($result['allowed'] !== false) {
        throw new RuntimeException(
            'Le kill switch réel aurait dû '
            . 'bloquer cet e-mail.'
        );
    }

    if (
        !in_array(
            'Envoi désactivé par configuration.',
            $result['reasons'],
            true
        )
    ) {
        throw new RuntimeException(
            'La raison du blocage '
            . 'par kill switch est absente.'
        );
    }

    echo 'Kill switch : BLOCKED OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Simulation avec envoi activé
    |--------------------------------------------------------------------------
    */

    $enabledConfig = $config;

    $enabledConfig['sending']['enabled'] =
        true;

    $enabledGuard =
        new ProspectSendGuard(
            $pdo,
            $enabledConfig
        );

    $result =
        $enabledGuard->evaluate(
            $testEmail
        );

    if ($result['allowed'] !== true) {
        throw new RuntimeException(
            'L’e-mail aurait dû être '
            . 'envoyable avec le kill switch activé : '
            . implode(
                ', ',
                $result['reasons']
            )
        );
    }

    echo 'Configuration activée : SENDABLE OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Intégration sélection + SendGuard OK.'
        . PHP_EOL;

} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
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
            'prospect_id' => $prospectId,
        ]);

        $statement = $pdo->prepare(
            '
            DELETE FROM prospect_emails
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' => $prospectId,
        ]);

        $statement = $pdo->prepare(
            '
            DELETE FROM prospects
            WHERE id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' => $prospectId,
        ]);

        echo 'Données temporaires supprimées.'
            . PHP_EOL;
    }
}