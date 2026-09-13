<?php

declare(strict_types=1);

require __DIR__ . '/../../php/db.php';
require __DIR__ . '/../../php/prospection/FollowUpManager.php';

$config = require __DIR__ . '/../../config/prospection.php';

$timezone = new DateTimeZone(
    $config['timezone']
        ?? 'Europe/Paris'
);

$manager = new FollowUpManager(
    $pdo,
    $config
);

$passed = 0;
$failed = 0;

function assertTest(
    bool $condition,
    string $message
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo '[OK]   ' . $message . PHP_EOL;
        return;
    }

    $failed++;
    echo '[FAIL] ' . $message . PHP_EOL;
}

function createProspect(
    PDO $pdo,
    string $email,
    string $status = 'contacted'
): int {
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
            status
        ) VALUES (
            :company_name,
            :activity,
            :city,
            :postal_code,
            :email,
            :email_source_url,
            :email_type,
            :status
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'Test Follow Up',
        'activity' =>
            'Plomberie',
        'city' =>
            'Ceret',
        'postal_code' =>
            '66400',
        'email' =>
            $email,
        'email_source_url' =>
            'https://example.test/contact',
        'email_type' =>
            'generic',
        'status' =>
            $status,
    ]);

    return (int) $pdo->lastInsertId();
}

function createInitialEmail(
    PDO $pdo,
    int $prospectId,
    string $email,
    string $sentAt
): int {
    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            email_type,
            recipient_email,
            subject,
            body_text,
            status,
            sent_at,
            message_id
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_text,
            :status,
            :sent_at,
            :message_id
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
        'email_type' =>
            'initial',
        'recipient_email' =>
            $email,
        'subject' =>
            'Votre presence en ligne',
        'body_text' =>
            'Message initial de test.',
        'status' =>
            'sent',
        'sent_at' =>
            $sentAt,
        'message_id' =>
            '<initial-'
            . bin2hex(random_bytes(6))
            . '@example.test>',
    ]);

    return (int) $pdo->lastInsertId();
}

function createReply(
    PDO $pdo,
    int $prospectId,
    int $initialEmailId,
    string $email,
    string $classification,
    string $receivedAt
): int {
    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_replies (
            prospect_id,
            prospect_email_id,
            sender_email,
            subject,
            body_text,
            message_id,
            classification,
            received_at
        ) VALUES (
            :prospect_id,
            :prospect_email_id,
            :sender_email,
            :subject,
            :body_text,
            :message_id,
            :classification,
            :received_at
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
        'prospect_email_id' =>
            $initialEmailId,
        'sender_email' =>
            $email,
        'subject' =>
            'Re: Votre presence en ligne',
        'body_text' =>
            'Reponse de test.',
        'message_id' =>
            '<reply-'
            . bin2hex(random_bytes(6))
            . '@example.test>',
        'classification' =>
            $classification,
        'received_at' =>
            $receivedAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function createUnsubscribe(
    PDO $pdo,
    int $prospectId,
    string $email,
    string $date
): void {
    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_unsubscribes (
            prospect_id,
            email,
            reason,
            source,
            unsubscribed_at
        ) VALUES (
            :prospect_id,
            :email,
            :reason,
            :source,
            :unsubscribed_at
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
        'email' =>
            $email,
        'reason' =>
            'Test automatique.',
        'source' =>
            'manual',
        'unsubscribed_at' =>
            $date,
    ]);
}

function createSentFollowUp1(
    PDO $pdo,
    int $prospectId,
    string $email,
    string $sentAt
): int {
    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            email_type,
            recipient_email,
            subject,
            body_text,
            status,
            scheduled_at,
            sent_at,
            message_id
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_text,
            :status,
            :scheduled_at,
            :sent_at,
            :message_id
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
        'email_type' =>
            'follow_up_1',
        'recipient_email' =>
            $email,
        'subject' =>
            'Re: Votre presence en ligne',
        'body_text' =>
            'Relance 1 de test.',
        'status' =>
            'sent',
        'scheduled_at' =>
            $sentAt,
        'sent_at' =>
            $sentAt,
        'message_id' =>
            '<followup1-'
            . bin2hex(random_bytes(6))
            . '@example.test>',
    ]);

    return (int) $pdo->lastInsertId();
}

function countEmailType(
    PDO $pdo,
    int $prospectId,
    string $type
): int {
    $statement = $pdo->prepare(
        '
        SELECT COUNT(*)
        FROM prospect_emails
        WHERE prospect_id = :prospect_id
        AND email_type = :email_type
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
        'email_type' =>
            $type,
    ]);

    return (int) $statement->fetchColumn();
}

function getProspectStatus(
    PDO $pdo,
    int $prospectId
): string {
    $statement = $pdo->prepare(
        '
        SELECT status
        FROM prospects
        WHERE id = :prospect_id
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
    ]);

    return (string) $statement->fetchColumn();
}

function cleanup(
    PDO $pdo,
    int $prospectId,
    string $email
): void {
    $statement = $pdo->prepare(
        '
        DELETE FROM prospect_unsubscribes
        WHERE LOWER(email) = LOWER(:email)
        '
    );

    $statement->execute([
        'email' => $email,
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
}

function testEmail(
    string $suffix
): string {
    return
        'followup-'
        . $suffix
        . '-'
        . bin2hex(random_bytes(4))
        . '@example.test';
}

echo '=== TEST FOLLOW UP MANAGER ==='
    . PHP_EOL
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| 1. J+4 : aucune relance
|--------------------------------------------------------------------------
*/

$email = testEmail('j4');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email
        );

    createInitialEmail(
        $pdo,
        $prospectId,
        $email,
        '2026-09-14 09:30:00'
    );

    $result =
        $manager->createDueFollowUps(
            new DateTimeImmutable(
                '2026-09-18 09:30:00',
                $timezone
            )
        );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_1'
        ) === 0,
        'J+4 : aucune relance creee'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 2. J+5 : relance 1 + idempotence
|--------------------------------------------------------------------------
*/

$email = testEmail('j5');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email
        );

    createInitialEmail(
        $pdo,
        $prospectId,
        $email,
        '2026-09-14 09:30:00'
    );

    $now =
        new DateTimeImmutable(
            '2026-09-21 09:30:00',
            $timezone
        );

    $manager->createDueFollowUps(
        $now
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_1'
        ) === 1,
        'J+5 : relance 1 creee'
    );

    assertTest(
        getProspectStatus(
            $pdo,
            $prospectId
        ) === 'follow_up',
        'Prospect passe en follow_up'
    );

    $manager->createDueFollowUps(
        $now
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_1'
        ) === 1,
        'Relance 1 idempotente'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 3. Reponse humaine : STOP
|--------------------------------------------------------------------------
*/

$email = testEmail('human');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email
        );

    $initialEmailId =
        createInitialEmail(
            $pdo,
            $prospectId,
            $email,
            '2026-09-14 09:30:00'
        );

    createReply(
        $pdo,
        $prospectId,
        $initialEmailId,
        $email,
        'positive',
        '2026-09-17 10:00:00'
    );

    $manager->createDueFollowUps(
        new DateTimeImmutable(
            '2026-09-21 09:30:00',
            $timezone
        )
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_1'
        ) === 0,
        'Reponse humaine : aucune relance'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 4. Reponse automatique : la relance reste autorisee
|--------------------------------------------------------------------------
*/

$email = testEmail('automatic');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email
        );

    $initialEmailId =
        createInitialEmail(
            $pdo,
            $prospectId,
            $email,
            '2026-09-14 09:30:00'
        );

    createReply(
        $pdo,
        $prospectId,
        $initialEmailId,
        $email,
        'automatic',
        '2026-09-17 10:00:00'
    );

    $manager->createDueFollowUps(
        new DateTimeImmutable(
            '2026-09-21 09:30:00',
            $timezone
        )
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_1'
        ) === 1,
        'Reponse automatique : relance 1 autorisee'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 5. Desinscription : STOP
|--------------------------------------------------------------------------
*/

$email = testEmail('unsubscribe');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email
        );

    createInitialEmail(
        $pdo,
        $prospectId,
        $email,
        '2026-09-14 09:30:00'
    );

    createUnsubscribe(
        $pdo,
        $prospectId,
        $email,
        '2026-09-17 11:00:00'
    );

    $manager->createDueFollowUps(
        new DateTimeImmutable(
            '2026-09-21 09:30:00',
            $timezone
        )
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_1'
        ) === 0,
        'Desinscription : aucune relance'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 6. FU1 non envoyee : pas de FU2
|--------------------------------------------------------------------------
*/

$email = testEmail('fu2-blocked');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email,
            'follow_up'
        );

    createInitialEmail(
        $pdo,
        $prospectId,
        $email,
        '2026-09-14 09:30:00'
    );

    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            email_type,
            recipient_email,
            subject,
            body_text,
            status,
            scheduled_at
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_text,
            :status,
            :scheduled_at
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,
        'email_type' =>
            'follow_up_1',
        'recipient_email' =>
            $email,
        'subject' =>
            'Re: Votre presence en ligne',
        'body_text' =>
            'FU1 non envoyee.',
        'status' =>
            'scheduled',
        'scheduled_at' =>
            '2026-09-21 09:30:00',
    ]);

    $manager->createDueFollowUps(
        new DateTimeImmutable(
            '2026-09-30 09:30:00',
            $timezone
        )
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_2'
        ) === 0,
        'FU1 non envoyee : FU2 bloquee'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 7. FU1 envoyee : FU2 a J+12 + idempotence
|--------------------------------------------------------------------------
*/

$email = testEmail('fu2');
$prospectId = 0;

try {
    $prospectId =
        createProspect(
            $pdo,
            $email,
            'follow_up'
        );

    createInitialEmail(
        $pdo,
        $prospectId,
        $email,
        '2026-09-14 09:30:00'
    );

    createSentFollowUp1(
        $pdo,
        $prospectId,
        $email,
        '2026-09-21 09:30:00'
    );

    $before =
        new DateTimeImmutable(
            '2026-09-29 09:30:00',
            $timezone
        );

    $manager->createDueFollowUps(
        $before
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_2'
        ) === 0,
        'Avant J+12 : FU2 absente'
    );

    $due =
        new DateTimeImmutable(
            '2026-09-30 09:30:00',
            $timezone
        );

    $manager->createDueFollowUps(
        $due
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_2'
        ) === 1,
        'J+12 : FU2 creee'
    );

    $manager->createDueFollowUps(
        $due
    );

    assertTest(
        countEmailType(
            $pdo,
            $prospectId,
            'follow_up_2'
        ) === 1,
        'Relance 2 idempotente'
    );
} finally {
    if ($prospectId > 0) {
        cleanup(
            $pdo,
            $prospectId,
            $email
        );
    }
}

echo PHP_EOL;
echo '=== RESULTAT ===' . PHP_EOL;
echo 'OK   : ' . $passed . PHP_EOL;
echo 'FAIL : ' . $failed . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
