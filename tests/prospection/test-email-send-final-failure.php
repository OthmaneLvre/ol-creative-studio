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
            'TEST FINAL SEND FAILURE',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            'final-failure@example.test',

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'final_failure_test',

        'source_external_id' =>
            'final-failure-'
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
    | On simule deux tentatives déjà consommées
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
            2
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            'final-failure@example.test',

        'subject' =>
            'Test final failure',

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
    | Troisième tentative
    |--------------------------------------------------------------------------
    */

    $claimed =
        $repository->claimScheduledInitialEmail(
            $emailId,
            '<test-' . bin2hex(random_bytes(8)) . '@olcreativestudio.fr>',
            3
        );

    if (
        (int) $claimed['send_attempts']
        !== 3
    ) {
        throw new RuntimeException(
            'La troisième tentative devrait valoir 3.'
        );
    }

    echo 'Troisième tentative : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Échec définitif
    |--------------------------------------------------------------------------
    */

    $result =
        $repository->markInitialEmailFailed(
            $emailId,
            'SMTP final failure',
            3,
            30
        );

    if (
        $result['retry_scheduled']
        !== false
    ) {
        throw new RuntimeException(
            'Aucun retry ne doit être programmé.'
        );
    }

    if (
        $result['status']
        !== 'failed'
    ) {
        throw new RuntimeException(
            'Le statut final devrait être failed.'
        );
    }

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.error_message,
            e.send_attempts,
            e.sent_at,
            e.message_id,
            p.status AS prospect_status
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
        !== 'failed'
    ) {
        throw new RuntimeException(
            'L’e-mail doit rester failed.'
        );
    }

    if (
        (int) $state['send_attempts']
        !== 3
    ) {
        throw new RuntimeException(
            'send_attempts devrait valoir 3.'
        );
    }

    if (
        empty($state['message_id'])
    ) {
        throw new RuntimeException(
            'Le Message-ID de la dernière tentative doit être conservé.'
        );
    }

    echo 'sending -> failed : OK'
        . PHP_EOL;

    echo 'Aucun retry : OK'
        . PHP_EOL;

    echo 'Tentatives max respectées : OK'
        . PHP_EOL;

    echo 'Message-ID final conservé : OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Échec SMTP définitif : OK'
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
