<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$repository = new ProspectRepository($pdo);

$timezone = new DateTimeZone('Europe/Paris');

$now = new DateTimeImmutable(
    '2026-08-30 13:30:00',
    $timezone
);

$prospectIds = [];

try {
    $cases = [
        [
            'name' => 'TEST DUE',
            'scheduled_at' => '2026-08-30 12:00:00',
            'sent_at' => null,
            'expected' => true,
        ],
        [
            'name' => 'TEST FUTURE',
            'scheduled_at' => '2026-08-31 09:30:00',
            'sent_at' => null,
            'expected' => false,
        ],
        [
            'name' => 'TEST SENT',
            'scheduled_at' => '2026-08-30 11:00:00',
            'sent_at' => '2026-08-30 11:05:00',
            'expected' => false,
        ],
    ];

    foreach ($cases as $index => $case) {
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
            'company_name' => $case['name'],
            'activity' => 'TEST',
            'city' => 'Céret',
            'postal_code' => '66400',
            'email' => 'due-test-' . $index . '@example.test',
            'email_source_url' => 'https://example.test/contact',
            'email_type' => 'generic',
            'email_confidence' => 100,
            'source' => 'due_test',
            'source_external_id' =>
                'due-test-' . bin2hex(random_bytes(8)),
            'status' => 'scheduled',
            'scheduled_at' => $case['scheduled_at'],
        ]);

        $prospectId = (int) $pdo->lastInsertId();

        $prospectIds[$case['name']] = $prospectId;

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
                sent_at,
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
                :sent_at,
                :send_attempts
            )
            '
        );

        $statement->execute([
            'prospect_id' => $prospectId,
            'email_type' => 'initial',
            'recipient_email' =>
                'due-test-' . $index . '@example.test',
            'subject' => 'Test due email',
            'body_html' => '<p>Test</p>',
            'body_text' => 'Test',
            'status' => 'scheduled',
            'approved_at' => '2026-08-29 10:00:00',
            'scheduled_at' => $case['scheduled_at'],
            'sent_at' => $case['sent_at'],
            'send_attempts' => $case['sent_at'] !== null ? 1 : 0,
        ]);
    }

    $dueEmails = $repository->findDueScheduledInitialEmails(
        $now,
        10
    );

    echo 'E-mails retournés : '
        . count($dueEmails)
        . PHP_EOL;

    foreach ($dueEmails as $email) {
        echo '#'
            . $email['prospect_id']
            . ' - '
            . $email['company_name']
            . ' - '
            . $email['scheduled_at']
            . PHP_EOL;
    }

    if (count($dueEmails) !== 1) {
        throw new RuntimeException(
            'Le repository aurait dû retourner exactement 1 e-mail.'
        );
    }

    $returnedProspectId =
        (int) $dueEmails[0]['prospect_id'];

    if (
        $returnedProspectId
        !== $prospectIds['TEST DUE']
    ) {
        throw new RuntimeException(
            'Le mauvais e-mail a été sélectionné.'
        );
    }

    echo PHP_EOL;
    echo 'Cas dû : OK' . PHP_EOL;
    echo 'Cas futur exclu : OK' . PHP_EOL;
    echo 'Cas déjà envoyé exclu : OK' . PHP_EOL;
    echo PHP_EOL;
    echo 'Sélection des e-mails dus OK.' . PHP_EOL;
} finally {
    if ($prospectIds !== []) {
        $ids = array_values($prospectIds);

        $placeholders = implode(
            ',',
            array_fill(0, count($ids), '?')
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospect_events
            WHERE prospect_id IN ({$placeholders})"
        );

        $statement->execute($ids);

        $statement = $pdo->prepare(
            "DELETE FROM prospect_emails
            WHERE prospect_id IN ({$placeholders})"
        );

        $statement->execute($ids);

        $statement = $pdo->prepare(
            "DELETE FROM prospects
            WHERE id IN ({$placeholders})"
        );

        $statement->execute($ids);

        echo 'Données temporaires supprimées.'
            . PHP_EOL;
    }
}
