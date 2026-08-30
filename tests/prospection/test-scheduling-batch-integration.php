<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$timezone = new DateTimeZone('Europe/Paris');

$repository = new ProspectRepository($pdo);
$scheduler = new ProspectScheduler($config);

$prospectIds = [];
$emailIds = [];

$approvedAt = '2026-09-06 12:00:00';
$expectedFirstDay = '2026-09-07';
$expectedSecondDay = '2026-09-08';

try {
    /*
    |--------------------------------------------------------------------------
    | Création de 6 prospects temporaires approuvés
    |--------------------------------------------------------------------------
    */

    for ($i = 1; $i <= 6; $i++) {
        $statement = $pdo->prepare(
            '
            INSERT INTO prospects (
                company_name,
                activity,
                city,
                postal_code,
                email,
                source,
                source_external_id,
                status,
                validated_at
            ) VALUES (
                :company_name,
                :activity,
                :city,
                :postal_code,
                :email,
                :source,
                :source_external_id,
                :status,
                :validated_at
            )
            '
        );

        $statement->execute([
            'company_name' =>
                'TEST SCHEDULER ' . $i,

            'activity' =>
                'TEST',

            'city' =>
                'Céret',

            'postal_code' =>
                '66400',

            'email' =>
                'scheduler-test-'
                . $i
                . '@example.test',

            'source' =>
                'scheduler_test',

            'source_external_id' =>
                'scheduler-test-'
                . bin2hex(random_bytes(8)),

            'status' =>
                'approved',

            'validated_at' =>
                $approvedAt,
        ]);

        $prospectId = (int) $pdo->lastInsertId();

        $prospectIds[] = $prospectId;

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
                approved_at
            ) VALUES (
                :prospect_id,
                :email_type,
                :recipient_email,
                :subject,
                :body_html,
                :body_text,
                :status,
                :approved_at
            )
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,

            'email_type' =>
                'initial',

            'recipient_email' =>
                'scheduler-test-'
                . $i
                . '@example.test',

            'subject' =>
                'Test scheduler',

            'body_html' =>
                '<p>Test scheduler</p>',

            'body_text' =>
                'Test scheduler',

            'status' =>
                'approved',

            'approved_at' =>
                $approvedAt,
        ]);

        $emailIds[] = (int) $pdo->lastInsertId();
    }

    echo '6 prospects temporaires créés.'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Exécution du batch
    |--------------------------------------------------------------------------
    */

    $result = $repository->scheduleApprovedBatch(
        $scheduler,
        $timezone,
        50
    );

    echo 'Programmés par le batch : '
        . $result['scheduled']
        . PHP_EOL;

    echo 'Erreurs : '
        . $result['failed']
        . PHP_EOL;

    if ($result['scheduled'] !== 6) {
        throw new RuntimeException(
            'Les 6 prospects n’ont pas été programmés.'
        );
    }

    if ($result['failed'] !== 0) {
        throw new RuntimeException(
            'Le batch contient une ou plusieurs erreurs.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lecture des 6 programmations
    |--------------------------------------------------------------------------
    */

    $placeholders = implode(
        ',',
        array_fill(0, count($prospectIds), '?')
    );

    $statement = $pdo->prepare(
        "
        SELECT
            prospect_id,
            scheduled_at
        FROM prospect_emails
        WHERE prospect_id IN ({$placeholders})
          AND email_type = 'initial'
        ORDER BY scheduled_at ASC, prospect_id ASC
        "
    );

    $statement->execute($prospectIds);

    $scheduledEmails = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    $firstDayCount = 0;
    $secondDayCount = 0;

    foreach ($scheduledEmails as $email) {
        $scheduledDate = substr(
            (string) $email['scheduled_at'],
            0,
            10
        );

        echo '#'
            . $email['prospect_id']
            . ' -> '
            . $email['scheduled_at']
            . PHP_EOL;

        if ($scheduledDate === $expectedFirstDay) {
            $firstDayCount++;
        }

        if ($scheduledDate === $expectedSecondDay) {
            $secondDayCount++;
        }
    }

    echo PHP_EOL;

    echo $expectedFirstDay
        . ' : '
        . $firstDayCount
        . ' e-mails'
        . PHP_EOL;

    echo $expectedSecondDay
        . ' : '
        . $secondDayCount
        . ' e-mail'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Assertions
    |--------------------------------------------------------------------------
    */

    if ($firstDayCount !== 5) {
        throw new RuntimeException(
            'La limite de 5 e-mails du premier jour '
            . 'n’est pas respectée.'
        );
    }

    if ($secondDayCount !== 1) {
        throw new RuntimeException(
            'Le sixième e-mail n’a pas été reporté '
            . 'au jour suivant.'
        );
    }

    echo PHP_EOL;
    echo 'Test d’intégration batch OK.'
        . PHP_EOL;
} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    if ($prospectIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(0, count($prospectIds), '?')
        );

        /*
         * Les événements doivent être supprimés avant
         * les prospects si une FK est présente.
         */
        $statement = $pdo->prepare(
            "DELETE FROM prospect_events
            WHERE prospect_id IN ({$placeholders})"
        );

        $statement->execute($prospectIds);

        $statement = $pdo->prepare(
            "DELETE FROM prospect_emails
            WHERE prospect_id IN ({$placeholders})"
        );

        $statement->execute($prospectIds);

        $statement = $pdo->prepare(
            "DELETE FROM prospects
            WHERE id IN ({$placeholders})"
        );

        $statement->execute($prospectIds);

        echo 'Données temporaires supprimées.'
            . PHP_EOL;
    }
}
