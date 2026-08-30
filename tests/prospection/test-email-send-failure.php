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
            'TEST SEND FAILURE',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            'send-failure@example.test',

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'send_failure_test',

        'source_external_id' =>
            'send-failure-'
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
            'send-failure@example.test',

        'subject' =>
            'Test failure',

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

    /*
    |--------------------------------------------------------------------------
    | Claim
    |--------------------------------------------------------------------------
    */

    $claimed =
        $repository->claimScheduledInitialEmail(
            $emailId,
            3
        );

    if ($claimed['status'] !== 'sending') {
        throw new RuntimeException(
            'Le claim aurait dû positionner sending.'
        );
    }

    echo 'Claim : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Simulation échec SMTP
    |--------------------------------------------------------------------------
    */

    $result =
        $repository->markInitialEmailFailed(
            $emailId,
            'SMTP test failure',
            3,
            30
        );

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.error_message,
            e.send_attempts,
            e.scheduled_at,
            e.sent_at,
            e.message_id,
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

    if ($state === false) {
        throw new RuntimeException(
            'État final introuvable.'
        );
    }

    if (
        $state['email_status']
        !== 'scheduled'
    ) {
        throw new RuntimeException(
            'L’e-mail devrait être reprogrammé.'
        );
    }

    if (
        $state['prospect_status']
        !== 'scheduled'
    ) {
        throw new RuntimeException(
            'Le prospect doit rester scheduled.'
        );
    }

    if (
        (int) $state['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'send_attempts devrait valoir 1.'
        );
    }

    if (
        $state['error_message']
        !== 'SMTP test failure'
    ) {
        throw new RuntimeException(
            'Le message d’erreur n’a pas été persisté.'
        );
    }

    if (
        $result['retry_scheduled']
        !== true
    ) {
        throw new RuntimeException(
            'Une nouvelle tentative aurait dû être programmée.'
        );
    }

    if (
        empty($result['retry_at'])
        || empty($state['scheduled_at'])
    ) {
        throw new RuntimeException(
            'La nouvelle échéance est absente.'
        );
    }

    if (
        $result['retry_at']
        !== $state['scheduled_at']
    ) {
        throw new RuntimeException(
            'La date de retry retournée ne correspond pas à la BDD.'
        );
    }

    if (
        $state['sent_at'] !== null
        || $state['message_id'] !== null
        || $state['contacted_at'] !== null
    ) {
        throw new RuntimeException(
            'Un retry ne doit pas être marqué comme envoyé.'
        );
    }

    echo 'sending -> scheduled : OK'
        . PHP_EOL;

    echo 'Erreur persistée : OK'
        . PHP_EOL;

    echo 'Retry programmé : OK'
        . PHP_EOL;

    echo 'Prospect reste scheduled : OK'
        . PHP_EOL;

    echo 'Aucune date d’envoi : OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Chemin retry SMTP : OK'
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