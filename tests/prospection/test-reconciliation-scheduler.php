<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

$config =
    require __DIR__
        . '/../../config/prospection.php';

$repository =
    new ProspectRepository(
        $pdo
    );

$scheduler =
    new ProspectScheduler(
        $config
    );

$prospectId = null;

function assertReconciliationScheduler(
    bool $condition,
    string $success,
    string $failure
): void {
    if (!$condition) {
        throw new RuntimeException(
            $failure
        );
    }

    echo $success . PHP_EOL;
}

try {
    echo PHP_EOL;
    echo '========================================'
        . PHP_EOL;
    echo 'TEST SCHEDULER RÉCONCILIATION'
        . PHP_EOL;
    echo '========================================'
        . PHP_EOL;
    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Prospect temporaire
    |--------------------------------------------------------------------------
    */

    $unique =
        bin2hex(
            random_bytes(8)
        );

    $pastAt =
        (new DateTimeImmutable())
            ->modify('-1 hour')
            ->format('Y-m-d H:i:s');

    $prospectStatement =
        $pdo->prepare(
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

    $recipient =
        'reconciliation-scheduler-'
        . $unique
        . '@example.com';

    $prospectStatement->execute([
        'company_name' =>
            'TEST RECONCILIATION SCHEDULER',

        'activity' =>
            'test',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            $recipient,

        'email_source_url' =>
            'https://example.com/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'test',

        'source_external_id' =>
            'test-reconciliation-scheduler-'
            . $unique,

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $pastAt,
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | E-mail initial
    |--------------------------------------------------------------------------
    */

    $emailStatement =
        $pdo->prepare(
            '
            INSERT INTO prospect_emails (
                prospect_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                approved_at,
                scheduled_at
            ) VALUES (
                :prospect_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_text,
                :status,
                :approved_at,
                :scheduled_at
            )
            '
        );

    $emailStatement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            $recipient,

        'subject' =>
            'Test scheduler réconciliation',

        'body_text' =>
            'Fixture temporaire.',

        'status' =>
            'scheduled',

        'approved_at' =>
            $pastAt,

        'scheduled_at' =>
            $pastAt,
    ]);

    $emailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | scheduled -> sending
    |--------------------------------------------------------------------------
    */

    $messageId =
        '<reconciliation-scheduler-'
        . $unique
        . '@olcreativestudio.fr>';

    $repository
        ->claimScheduledInitialEmail(
            $emailId,
            $messageId,
            3
        );

    /*
    |--------------------------------------------------------------------------
    | On rend le sending stale
    |--------------------------------------------------------------------------
    */

    $staleAt =
        (new DateTimeImmutable())
            ->modify('-20 minutes')
            ->format('Y-m-d H:i:s');

    $staleStatement =
        $pdo->prepare(
            '
            UPDATE prospect_emails
            SET updated_at = :updated_at
            WHERE id = :email_id
            '
        );

    $staleStatement->execute([
        'updated_at' =>
            $staleAt,

        'email_id' =>
            $emailId,
    ]);

    $repository
        ->markStaleInitialEmailForReconciliation(
            $emailId,
            new DateTimeImmutable(),
            15
        );

    echo 'sending -> reconciliation_required : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Reprogrammation sécurisée
    |--------------------------------------------------------------------------
    */

    $timezone =
        new DateTimeZone(
            date_default_timezone_get()
        );

    $result =
        $repository
            ->rescheduleReconciledInitialEmailWithDailyLimit(
                $emailId,
                $scheduler,
                $timezone
            );

    assertReconciliationScheduler(
        $result['status'] === 'scheduled',
        'Réconciliation -> scheduled : OK',
        'L’e-mail n’est pas scheduled.'
    );

    assertReconciliationScheduler(
        $result['prospect_status']
            === 'scheduled',
        'Prospect reste scheduled : OK',
        'Le prospect n’est plus scheduled.'
    );

    /*
    |--------------------------------------------------------------------------
    | Vérification BDD
    |--------------------------------------------------------------------------
    */

    $checkStatement =
        $pdo->prepare(
            '
            SELECT
                e.status AS email_status,
                e.scheduled_at AS email_scheduled_at,
                e.message_id,
                e.send_attempts,
                p.status AS prospect_status,
                p.scheduled_at AS prospect_scheduled_at
            FROM prospect_emails e
            INNER JOIN prospects p
                ON p.id = e.prospect_id
            WHERE e.id = :email_id
            LIMIT 1
            '
        );

    $checkStatement->execute([
        'email_id' =>
            $emailId,
    ]);

    $row =
        $checkStatement->fetch(
            PDO::FETCH_ASSOC
        );

    assertReconciliationScheduler(
        $row !== false,
        'E-mail retrouvé : OK',
        'E-mail temporaire introuvable.'
    );

    assertReconciliationScheduler(
        $row['email_status']
            === 'scheduled',
        'Statut e-mail : OK',
        'Statut e-mail incorrect.'
    );

    assertReconciliationScheduler(
        $row['prospect_status']
            === 'scheduled',
        'Statut prospect : OK',
        'Statut prospect incorrect.'
    );

    assertReconciliationScheduler(
        $row['message_id'] === null,
        'Message-ID réinitialisé : OK',
        'Le Message-ID devrait être NULL.'
    );

    assertReconciliationScheduler(
        (int) $row['send_attempts'] === 1,
        'Tentatives conservées : OK',
        'Le compteur de tentatives a changé.'
    );

    assertReconciliationScheduler(
        $row['email_scheduled_at']
            === $row['prospect_scheduled_at'],
        'Programmation synchronisée : OK',
        'Les dates prospect/e-mail diffèrent.'
    );

    /*
    |--------------------------------------------------------------------------
    | Heure configurée
    |--------------------------------------------------------------------------
    */

    $scheduledAt =
        new DateTimeImmutable(
            (string) $row[
                'email_scheduled_at'
            ],
            $timezone
        );

    $expectedTime =
        (string) (
            $config['sending']['send_time']
            ?? '09:30'
        );

    assertReconciliationScheduler(
        $scheduledAt->format('H:i')
            === $expectedTime,
        'Heure d’envoi respectée : OK',
        'L’heure programmée ne correspond pas à la configuration.'
    );

    /*
    |--------------------------------------------------------------------------
    | Jour ouvré
    |--------------------------------------------------------------------------
    */

    $dayOfWeek =
        (int) $scheduledAt->format('N');

    assertReconciliationScheduler(
        $dayOfWeek >= 1
        && $dayOfWeek <= 5,
        'Jour ouvré respecté : OK',
        'L’e-mail a été programmé un week-end.'
    );

    /*
    |--------------------------------------------------------------------------
    | Pas de programmation le jour même
    |--------------------------------------------------------------------------
    */

    $today =
        new DateTimeImmutable(
            'today',
            $timezone
        );

    assertReconciliationScheduler(
        $scheduledAt->format('Y-m-d')
            > $today->format('Y-m-d'),
        'Programmation future : OK',
        'L’e-mail a été reprogrammé le jour même.'
    );

    echo PHP_EOL;
    echo 'Scheduler de réconciliation : OK'
        . PHP_EOL;

} finally {
    if ($prospectId !== null) {
        $deleteEvents =
            $pdo->prepare(
                '
                DELETE FROM prospect_events
                WHERE prospect_id = :prospect_id
                '
            );

        $deleteEvents->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        $deleteEmails =
            $pdo->prepare(
                '
                DELETE FROM prospect_emails
                WHERE prospect_id = :prospect_id
                '
            );

        $deleteEmails->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        $deleteProspect =
            $pdo->prepare(
                '
                DELETE FROM prospects
                WHERE id = :prospect_id
                '
            );

        $deleteProspect->execute([
            'prospect_id' =>
                $prospectId,
        ]);
    }

    echo 'Données temporaires supprimées.'
        . PHP_EOL;
}
