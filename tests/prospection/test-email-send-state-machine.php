<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$repository =
    new ProspectRepository($pdo);

$prospectId = null;
$emailId = null;

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
            'TEST SEND STATE MACHINE',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            'state-machine@example.test',

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'state_machine_test',

        'source_external_id' =>
            'state-machine-'
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
            'state-machine@example.test',

        'subject' =>
            'Test state machine',

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
    ]);

    $emailId =
        (int) $pdo->lastInsertId();

    $messageId =
        '<test-'
        . bin2hex(random_bytes(8))
        . '@olcreativestudio.fr>';

    /*
    |--------------------------------------------------------------------------
    | Claim
    |--------------------------------------------------------------------------
    */


    $claimed =
        $repository->claimScheduledInitialEmail(
            $emailId,
            $messageId,
            3
        );

    if (
        $claimed['status']
        !== 'sending'
    ) {
        throw new RuntimeException(
            'Le claim n’a pas positionné sending.'
        );
    }

    if (
        (int) $claimed['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'send_attempts aurait dû valoir 1.'
        );
    }

    if (
        $claimed['message_id']
        !== $messageId
    ) {
        throw new RuntimeException(
            'Le Message-ID n’a pas été persisté au claim.'
        );
    }

    echo 'Claim scheduled -> sending : OK'
        . PHP_EOL;

    echo 'Message-ID persisté avant SMTP : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Double claim interdit
    |--------------------------------------------------------------------------
    */

    try {
        $repository->claimScheduledInitialEmail(
            $emailId,
            '<test-'
            . bin2hex(random_bytes(8))
            . '@olcreativestudio.fr>',
            3
        );

        throw new RuntimeException(
            'Le double claim aurait dû être refusé.'
        );
    } catch (RuntimeException $exception) {
        if (
            str_contains(
                $exception->getMessage(),
                'double claim'
            )
        ) {
            throw $exception;
        }
    }

    echo 'Double claim bloqué : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Simulation succès SMTP
    |--------------------------------------------------------------------------
    */

    $repository->markInitialEmailSent(
        $emailId,
        $messageId
    );

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
        '
    );

    $statement->execute([
        'email_id' => $emailId,
    ]);

    $state = $statement->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        $state['email_status']
        !== 'sent'
    ) {
        throw new RuntimeException(
            'L’e-mail devrait être sent.'
        );
    }

    if (
        $state['prospect_status']
        !== 'contacted'
    ) {
        throw new RuntimeException(
            'Le prospect devrait être contacted.'
        );
    }

    if (
        empty($state['sent_at'])
        || empty($state['contacted_at'])
    ) {
        throw new RuntimeException(
            'Les dates d’envoi/contact sont absentes.'
        );
    }

    if (
        $state['message_id']
        !== $messageId
    ) {
        throw new RuntimeException(
            'Message-ID non persisté.'
        );
    }

    if (
        (int) $state['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'send_attempts incorrect après succès.'
        );
    }

    echo 'sending -> sent : OK'
        . PHP_EOL;

    echo 'Prospect -> contacted : OK'
        . PHP_EOL;

    echo 'Message-ID persisté : OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'State machine envoi : OK'
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
