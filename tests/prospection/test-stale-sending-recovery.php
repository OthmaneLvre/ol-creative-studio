<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$repository =
    new ProspectRepository(
        $pdo
    );

$prospectId = null;

try {
    /*
    |--------------------------------------------------------------------------
    | Fixture
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
            'TEST STALE SENDING',

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            'stale-test@example.com',

        'email_source_url' =>
            'https://example.com/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'stale_sending_test',

        'source_external_id' =>
            'stale-'
            . bin2hex(
                random_bytes(10)
            ),

        'status' =>
            'scheduled',

        'scheduled_at' =>
            (new DateTimeImmutable('-1 hour'))
                ->format('Y-m-d H:i:s'),
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

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
            'stale-test@example.com',

        'subject' =>
            'Test stale sending',

        'body_html' =>
            '<p>Test</p>',

        'body_text' =>
            'Test',

        'status' =>
            'scheduled',

        'approved_at' =>
            (new DateTimeImmutable('-1 day'))
                ->format('Y-m-d H:i:s'),

        'scheduled_at' =>
            (new DateTimeImmutable('-1 hour'))
                ->format('Y-m-d H:i:s'),
    ]);

    $emailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Simulation d'un claim
    |--------------------------------------------------------------------------
    */

    $messageId =
        '<stale-test-'
        . bin2hex(random_bytes(12))
        . '@olcreativestudio.fr>';

    $repository
        ->claimScheduledInitialEmail(
            $emailId,
            $messageId,
            3
        );

    echo 'Claim scheduled -> sending : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Simulation d'un crash ancien
    |--------------------------------------------------------------------------
    |
    | On force updated_at 20 minutes dans le passé.
    |
    */

    $statement = $pdo->prepare(
        '
        UPDATE prospect_emails
        SET updated_at = :updated_at
        WHERE id = :email_id
        '
    );

    $statement->execute([
        'updated_at' =>
            (new DateTimeImmutable('-20 minutes'))
                ->format('Y-m-d H:i:s'),

        'email_id' =>
            $emailId,
    ]);

    /*
    |--------------------------------------------------------------------------
    | Détection
    |--------------------------------------------------------------------------
    */

    $staleEmails =
        $repository
            ->findStaleSendingInitialEmails(
                new DateTimeImmutable(),
                15
            );

    $found = false;

    foreach ($staleEmails as $email) {
        if (
            (int) $email['email_id']
            === $emailId
        ) {
            $found = true;

            break;
        }
    }

    if (!$found) {
        throw new RuntimeException(
            'L’e-mail sending stale n’a pas été détecté.'
        );
    }

    echo 'Sending stale détecté : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Mise en quarantaine
    |--------------------------------------------------------------------------
    */

    $result =
        $repository
            ->markStaleInitialEmailForReconciliation(
                $emailId,
                new DateTimeImmutable(),
                15
            );

    if (
        $result['status']
        !== 'reconciliation_required'
    ) {
        throw new RuntimeException(
            'Le statut de réconciliation est incorrect.'
        );
    }

    echo 'sending -> reconciliation_required : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Vérification BDD
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.sent_at,
            e.scheduled_at,
            e.message_id,
            e.send_attempts,
            e.error_message,
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
        !== 'reconciliation_required'
    ) {
        throw new RuntimeException(
            'L’e-mail n’est pas en réconciliation.'
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

    if ($state['sent_at'] !== null) {
        throw new RuntimeException(
            'Un envoi incertain ne doit pas être marqué sent.'
        );
    }

    if ($state['contacted_at'] !== null) {
        throw new RuntimeException(
            'Le prospect ne doit pas être marqué contacted.'
        );
    }

    if (
        $state['message_id']
        !== $messageId
    ) {
        throw new RuntimeException(
            'Le Message-ID réservé doit être conservé.'
        );
    }

    if (
        (int) $state['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'Le compteur de tentatives ne doit pas changer.'
        );
    }

    if (
        stripos(
            (string) $state['error_message'],
            'Vérification manuelle'
        ) === false
    ) {
        throw new RuntimeException(
            'Le motif de réconciliation est absent.'
        );
    }

    echo 'Prospect reste scheduled : OK'
        . PHP_EOL;

    echo 'Message-ID conservé : OK'
        . PHP_EOL;

    echo 'Aucun retry automatique : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Vérification événement
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            event_type,
            previous_status,
            new_status,
            metadata
        FROM prospect_events
        WHERE prospect_id = :prospect_id
        AND event_type = :event_type
        ORDER BY id DESC
        LIMIT 1
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'event_type' =>
            'prospect_email_reconciliation_required',
    ]);

    $event =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if ($event === false) {
        throw new RuntimeException(
            'Événement de réconciliation absent.'
        );
    }

    if (
        $event['previous_status']
        !== 'sending'
        || $event['new_status']
        !== 'reconciliation_required'
    ) {
        throw new RuntimeException(
            'Transition journalisée incorrecte.'
        );
    }

    echo 'Événement de réconciliation : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Impossible de retraiter automatiquement
    |--------------------------------------------------------------------------
    */

    $blocked = false;

    try {
        $repository
            ->claimScheduledInitialEmail(
                $emailId,
                '<second-'
                . bin2hex(random_bytes(8))
                . '@olcreativestudio.fr>',
                3
            );
    } catch (RuntimeException) {
        $blocked = true;
    }

    if (!$blocked) {
        throw new RuntimeException(
            'Un e-mail en réconciliation '
            . 'ne doit pas pouvoir être réclamé à nouveau.'
        );
    }

    echo 'Nouveau claim bloqué : OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Recovery sending stale : OK'
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
