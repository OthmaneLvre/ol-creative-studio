<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/IncomingReplyMatcher.php';

echo "========================================\n";
echo "TEST INCOMING REPLY MATCHER\n";
echo "========================================\n\n";

$matcher = new IncomingReplyMatcher($pdo);

$createdProspectIds = [];
$createdEmailIds = [];

function assertTest(
    bool $condition,
    string $successMessage,
    string $failureMessage
): void {
    if (!$condition) {
        throw new RuntimeException(
            $failureMessage
        );
    }

    echo $successMessage . PHP_EOL;
}

try {
    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    $insertProspect = $pdo->prepare(
        '
        INSERT INTO prospects (
            company_name,
            activity,
            city,
            postal_code,
            email,
            status,
            discovered_at,
            contacted_at
        ) VALUES (
            :company_name,
            :activity,
            :city,
            :postal_code,
            :email,
            :status,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        )
        '
    );

    $insertEmail = $pdo->prepare(
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

    /*
    |--------------------------------------------------------------------------
    | Prospect A
    |--------------------------------------------------------------------------
    */

    $emailA =
        'matcher-a-'
        . bin2hex(random_bytes(4))
        . '@example.test';

    $messageIdA =
        '<matcher-a-'
        . bin2hex(random_bytes(8))
        . '@olcreativestudio.fr>';

    $insertProspect->execute([
        'company_name' =>
            'TEST MATCHER A',

        'activity' =>
            'test',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            $emailA,

        'status' =>
            'contacted',
    ]);

    $prospectA =
        (int) $pdo->lastInsertId();

    $createdProspectIds[] =
        $prospectA;

    $insertEmail->execute([
        'prospect_id' =>
            $prospectA,

        'email_type' =>
            'initial',

        'recipient_email' =>
            $emailA,

        'subject' =>
            'Test matcher A',

        'body_text' =>
            'Message de test.',

        'status' =>
            'sent',

        'sent_at' =>
            (new DateTimeImmutable('-2 days'))
                ->format('Y-m-d H:i:s'),

        'message_id' =>
            $messageIdA,
    ]);

    $emailIdA =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $emailIdA;

    /*
    |--------------------------------------------------------------------------
    | Prospect B
    |--------------------------------------------------------------------------
    */

    $emailB =
        'matcher-b-'
        . bin2hex(random_bytes(4))
        . '@example.test';

    $messageIdB =
        '<matcher-b-'
        . bin2hex(random_bytes(8))
        . '@olcreativestudio.fr>';

    $insertProspect->execute([
        'company_name' =>
            'TEST MATCHER B',

        'activity' =>
            'test',

        'city' =>
            'Le Boulou',

        'postal_code' =>
            '66160',

        'email' =>
            $emailB,

        'status' =>
            'contacted',
    ]);

    $prospectB =
        (int) $pdo->lastInsertId();

    $createdProspectIds[] =
        $prospectB;

    $insertEmail->execute([
        'prospect_id' =>
            $prospectB,

        'email_type' =>
            'initial',

        'recipient_email' =>
            $emailB,

        'subject' =>
            'Test matcher B',

        'body_text' =>
            'Message de test.',

        'status' =>
            'sent',

        'sent_at' =>
            (new DateTimeImmutable('-1 day'))
                ->format('Y-m-d H:i:s'),

        'message_id' =>
            $messageIdB,
    ]);

    $emailIdB =
        (int) $pdo->lastInsertId();

    $createdEmailIds[] =
        $emailIdB;

    /*
    |--------------------------------------------------------------------------
    | 1. In-Reply-To exact
    |--------------------------------------------------------------------------
    */

    $match = $matcher->match(
        $messageIdA,
        [],
        'unknown@example.test'
    );

    assertTest(
        $match !== null
        && (int) $match['email_id']
            === $emailIdA
        && $match['match_type']
            === 'message_id',
        'In-Reply-To exact : OK',
        'Échec du rattachement par In-Reply-To.'
    );

    /*
    |--------------------------------------------------------------------------
    | 2. References
    |--------------------------------------------------------------------------
    */

    $match = $matcher->match(
        null,
        [
            '<unknown@olcreativestudio.fr>',
            $messageIdB,
        ],
        'unknown@example.test'
    );

    assertTest(
        $match !== null
        && (int) $match['email_id']
            === $emailIdB
        && $match['match_type']
            === 'message_id',
        'References : OK',
        'Échec du rattachement par References.'
    );

    /*
    |--------------------------------------------------------------------------
    | 3. Fallback expéditeur unique
    |--------------------------------------------------------------------------
    */

    $match = $matcher->match(
        null,
        [],
        $emailA
    );

    assertTest(
        $match !== null
        && (int) $match['prospect_id']
            === $prospectA
        && $match['match_type']
            === 'sender_email',
        'Fallback expéditeur unique : OK',
        'Échec du fallback par expéditeur.'
    );

    /*
    |--------------------------------------------------------------------------
    | 4. Expéditeur ambigu
    |--------------------------------------------------------------------------
    */

    $sharedEmail =
        'matcher-shared-'
        . bin2hex(random_bytes(4))
        . '@example.test';

    $sharedProspectIds = [];

    for ($i = 1; $i <= 2; $i++) {
        $insertProspect->execute([
            'company_name' =>
                'TEST MATCHER SHARED ' . $i,

            'activity' =>
                'test',

            'city' =>
                'Céret',

            'postal_code' =>
                '66400',

            'email' =>
                'matcher-shared-prospect-'
                . $i
                . '-'
                . bin2hex(random_bytes(4))
                . '@example.test',

            'status' =>
                'contacted',
        ]);

        $sharedProspectId =
            (int) $pdo->lastInsertId();

        $createdProspectIds[] =
            $sharedProspectId;

        $sharedProspectIds[] =
            $sharedProspectId;

        $sharedMessageId =
            '<matcher-shared-'
            . $i
            . '-'
            . bin2hex(random_bytes(8))
            . '@olcreativestudio.fr>';

        $insertEmail->execute([
            'prospect_id' =>
                $sharedProspectId,

            'email_type' =>
                'initial',

            'recipient_email' =>
                $sharedEmail,

            'subject' =>
                'Test matcher partagé',

            'body_text' =>
                'Message de test.',

            'status' =>
                'sent',

            'sent_at' =>
                (new DateTimeImmutable('-1 day'))
                    ->format('Y-m-d H:i:s'),

            'message_id' =>
                $sharedMessageId,
        ]);

        $createdEmailIds[] =
            (int) $pdo->lastInsertId();
    }

    $match = $matcher->match(
        null,
        [],
        $sharedEmail
    );

    assertTest(
        $match === null,
        'Expéditeur ambigu refusé : OK',
        'Un expéditeur ambigu a été rattaché automatiquement.'
    );

    /*
    |--------------------------------------------------------------------------
    | 5. Message inconnu
    |--------------------------------------------------------------------------
    */

    $match = $matcher->match(
        '<unknown-message@olcreativestudio.fr>',
        [
            '<unknown-reference@olcreativestudio.fr>',
        ],
        'nobody-' . bin2hex(random_bytes(4))
            . '@example.test'
    );

    assertTest(
        $match === null,
        'Message inconnu ignoré : OK',
        'Un message inconnu a été rattaché.'
    );

    echo "\n========================================\n";
    echo "TOUS LES TESTS SONT PASSÉS\n";
    echo "========================================\n";

} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    if ($createdEmailIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdEmailIds),
                '?'
            )
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospect_emails
            WHERE id IN ({$placeholders})"
        );

        $statement->execute(
            $createdEmailIds
        );
    }

    if ($createdProspectIds !== []) {
        $placeholders = implode(
            ',',
            array_fill(
                0,
                count($createdProspectIds),
                '?'
            )
        );

        $statement = $pdo->prepare(
            "DELETE FROM prospects
            WHERE id IN ({$placeholders})"
        );

        $statement->execute(
            $createdProspectIds
        );
    }

    echo "\nFixtures temporaires supprimées.\n";
}
