<?php

declare(strict_types=1);

require __DIR__ . '/../../php/db.php';
require __DIR__
    . '/../../php/prospection/ProspectFollowUpSendGuard.php';

$config = require __DIR__
    . '/../../config/prospection.php';

/*
|--------------------------------------------------------------------------
| Configuration de test
|--------------------------------------------------------------------------
|
| Aucun SMTP n'est utilisÃ© ici.
| On active uniquement le kill switch dans une copie locale
| de la configuration afin de pouvoir tester le guard.
|
*/

$testConfig = $config;

$testConfig['sending']['enabled'] = true;
$testConfig['sending']['max_attempts'] = 3;

$guard = new ProspectFollowUpSendGuard(
    $pdo,
    $testConfig
);

$passed = 0;
$failed = 0;

function assertGuard(
    bool $condition,
    string $message
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;

        echo '[OK]   '
            . $message
            . PHP_EOL;

        return;
    }

    $failed++;

    echo '[FAIL] '
        . $message
        . PHP_EOL;
}

function createGuardProspect(
    PDO $pdo,
    string $email,
    string $status = 'follow_up'
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
            'Test Follow Up Guard',

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

function createGuardInitial(
    PDO $pdo,
    int $prospectId,
    string $email
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

    $messageId =
        '<guard-initial-'
        . bin2hex(random_bytes(6))
        . '@example.test>';

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
            'Initial test.',

        'status' =>
            'sent',

        'scheduled_at' =>
            '2026-09-01 09:30:00',

        'sent_at' =>
            '2026-09-01 09:30:00',

        'message_id' =>
            $messageId,
    ]);

    return (int) $pdo->lastInsertId();
}

function createGuardFollowUp1(
    PDO $pdo,
    int $prospectId,
    string $email,
    string $status = 'sent'
): int {
    $sentAt =
        $status === 'sent'
            ? '2026-09-08 09:30:00'
            : null;

    $messageId =
        $status === 'sent'
            ? '<guard-follow1-'
                . bin2hex(random_bytes(6))
                . '@example.test>'
            : null;

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
            'Follow up 1 test.',

        'status' =>
            $status,

        'scheduled_at' =>
            '2026-09-08 09:30:00',

        'sent_at' =>
            $sentAt,

        'message_id' =>
            $messageId,
    ]);

    return (int) $pdo->lastInsertId();
}

function createGuardReply(
    PDO $pdo,
    int $prospectId,
    int $initialEmailId,
    string $email,
    string $classification
): void {
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
            'Reply test.',

        'message_id' =>
            '<guard-reply-'
            . bin2hex(random_bytes(6))
            . '@example.test>',

        'classification' =>
            $classification,

        'received_at' =>
            '2026-09-10 10:00:00',
    ]);
}

function createGuardUnsubscribe(
    PDO $pdo,
    int $prospectId,
    string $email
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
            'Guard test.',

        'source' =>
            'manual',

        'unsubscribed_at' =>
            '2026-09-10 10:00:00',
    ]);
}

function guardEmail(
    int $prospectId,
    string $email,
    string $type = 'follow_up_1',
    string $prospectStatus = 'follow_up',
    string $emailStatus = 'scheduled',
    int $attempts = 0
): array {
    return [
        'id' => 999999,

        'prospect_id' =>
            $prospectId,

        'email_type' =>
            $type,

        'recipient_email' =>
            $email,

        'prospect_status' =>
            $prospectStatus,

        'email_status' =>
            $emailStatus,

        'status' =>
            $emailStatus,

        'send_attempts' =>
            $attempts,
    ];
}

function guardEmailAddress(
    string $suffix
): string {
    return
        'followup-guard-'
        . $suffix
        . '-'
        . bin2hex(random_bytes(4))
        . '@example.test';
}

function cleanupGuardFixture(
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

echo '=== TEST FOLLOW UP SEND GUARD ==='
    . PHP_EOL
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| 1. Relance normale
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('allowed');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email
        )
    );

    assertGuard(
        $result['allowed'] === true,
        'Relance 1 normale autorisee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 2. Kill switch global
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('disabled');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    $disabledConfig = $testConfig;

    $disabledConfig['sending']['enabled'] =
        false;

    $disabledGuard =
        new ProspectFollowUpSendGuard(
            $pdo,
            $disabledConfig
        );

    $result =
        $disabledGuard->evaluate(
            guardEmail(
                $prospectId,
                $email
            )
        );

    assertGuard(
        $result['allowed'] === false,
        'Sending desactive : relance bloquee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 3. Mauvais statut prospect
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('status');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email,
            'contacted'
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email,
            'follow_up_1',
            'contacted'
        )
    );

    assertGuard(
        $result['allowed'] === false,
        'Prospect hors follow_up : envoi bloque'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 4. Desinscription
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('unsubscribe');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    createGuardUnsubscribe(
        $pdo,
        $prospectId,
        $email
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email
        )
    );

    assertGuard(
        $result['allowed'] === false,
        'Adresse desinscrite : relance bloquee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 5. Reponse humaine
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('human');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    $initialEmailId =
        createGuardInitial(
            $pdo,
            $prospectId,
            $email
        );

    createGuardReply(
        $pdo,
        $prospectId,
        $initialEmailId,
        $email,
        'positive'
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email
        )
    );

    assertGuard(
        $result['allowed'] === false,
        'Reponse humaine : relance bloquee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 6. Reponse automatique
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('automatic');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    $initialEmailId =
        createGuardInitial(
            $pdo,
            $prospectId,
            $email
        );

    createGuardReply(
        $pdo,
        $prospectId,
        $initialEmailId,
        $email,
        'automatic'
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email
        )
    );

    assertGuard(
        $result['allowed'] === true,
        'Reponse automatique : relance autorisee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 7. FU2 sans FU1 envoyee
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('fu2-blocked');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    createGuardFollowUp1(
        $pdo,
        $prospectId,
        $email,
        'scheduled'
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email,
            'follow_up_2'
        )
    );

    assertGuard(
        $result['allowed'] === false,
        'FU2 sans FU1 envoyee : bloquee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 8. FU2 avec FU1 envoyee
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('fu2-allowed');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    createGuardFollowUp1(
        $pdo,
        $prospectId,
        $email,
        'sent'
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email,
            'follow_up_2'
        )
    );

    assertGuard(
        $result['allowed'] === true,
        'FU2 avec FU1 envoyee : autorisee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 9. Type invalide
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('type');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email,
            'manual'
        )
    );

    assertGuard(
        $result['allowed'] === false,
        'Type manual : bloque par le guard relance'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

/*
|--------------------------------------------------------------------------
| 10. Max attempts
|--------------------------------------------------------------------------
*/

$email = guardEmailAddress('attempts');
$prospectId = 0;

try {
    $prospectId =
        createGuardProspect(
            $pdo,
            $email
        );

    createGuardInitial(
        $pdo,
        $prospectId,
        $email
    );

    $result = $guard->evaluate(
        guardEmail(
            $prospectId,
            $email,
            'follow_up_1',
            'follow_up',
            'scheduled',
            3
        )
    );

    assertGuard(
        $result['allowed'] === false,
        'Max attempts atteint : relance bloquee'
    );
} finally {
    if ($prospectId > 0) {
        cleanupGuardFixture(
            $pdo,
            $prospectId,
            $email
        );
    }
}

echo PHP_EOL;

echo '=== RESULTAT ==='
    . PHP_EOL;

echo 'OK   : '
    . $passed
    . PHP_EOL;

echo 'FAIL : '
    . $failed
    . PHP_EOL;

exit(
    $failed === 0
        ? 0
        : 1
);
