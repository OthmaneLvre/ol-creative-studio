<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectSendGuard.php';

require_once __DIR__
    . '/../../php/prospection/ProspectMailer.php';

require_once __DIR__
    . '/../../php/prospection/ProspectReplySendGuard.php';

require_once __DIR__
    . '/../../php/prospection/ProspectFollowUpSendGuard.php';

/*
|--------------------------------------------------------------------------
| Transport SMTP simulé
|--------------------------------------------------------------------------
|
| Aucun appel réseau.
|
*/

final class FakeProspectMailTransport
{
    public function __construct(
        private readonly bool $shouldSucceed
    ) {
    }

    public function send(
        array $message
    ): array {
        if ($this->shouldSucceed) {
            return [
                'success' => true,
                'message_id' =>
                    (string) $message['message_id'],
                'error' => null,
            ];
        }

        return [
            'success' => false,
            'message_id' => null,
            'error' =>
                'Fake SMTP failure',
        ];
    }
}

/*
|--------------------------------------------------------------------------
| Configuration de test
|--------------------------------------------------------------------------
|
| On active le SendGuard uniquement dans cette copie locale
| de configuration.
|
| Le vrai config/prospection.php n'est pas modifié.
|
*/

$config = require __DIR__
    . '/../../config/prospection.php';

$config['sending']['enabled'] = true;

$config['sending']['local_redirect_enabled'] =
    false;

$config['sending']['max_attempts'] =
    3;

$config['sending']['retry_delay_minutes'] =
    30;

$config['follow_up']['enabled'] =
    true;

$repository =
    new ProspectRepository($pdo);

$sendGuard =
    new ProspectSendGuard(
        $pdo,
        $config
    );

$replySendGuard =
    new ProspectReplySendGuard(
        $pdo,
        $config
    );

$followUpSendGuard =
    new ProspectFollowUpSendGuard(
        $pdo,
        $config
    );

$mailer =
    new ProspectMailer(
        $config
    );

$prospectIds = [];

/*
|--------------------------------------------------------------------------
| Création d'un fixture
|--------------------------------------------------------------------------
*/

function createFixture(
    PDO $pdo,
    string $suffix
): array {
    $scheduledAt =
        (new DateTimeImmutable('-5 minutes'))
            ->format('Y-m-d H:i:s');

    $recipientEmail =
        'orchestrator-'
        . $suffix
        . '@example.test';

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
            'TEST ORCHESTRATOR '
            . strtoupper($suffix),

        'activity' =>
            'TEST',

        'city' =>
            'Céret',

        'postal_code' =>
            '66400',

        'email' =>
            $recipientEmail,

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'orchestrator_test',

        'source_external_id' =>
            'orchestrator-'
            . $suffix
            . '-'
            . bin2hex(random_bytes(8)),

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $scheduledAt,
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
            $recipientEmail,

        'subject' =>
            'Test orchestrateur ' . $suffix,

        'body_html' =>
            '<p>Test orchestrateur</p>',

        'body_text' =>
            'Test orchestrateur',

        'status' =>
            'scheduled',

        'approved_at' =>
            (new DateTimeImmutable('-1 day'))
                ->format('Y-m-d H:i:s'),

        'scheduled_at' =>
            $scheduledAt,
    ]);

    return [
        'prospect_id' =>
            $prospectId,

        'email_id' =>
            (int) $pdo->lastInsertId(),
    ];
}

function createReplyFixture(
    PDO $pdo,
    string $suffix
): array {
    $scheduledAt =
        (new DateTimeImmutable('-5 minutes'))
            ->format('Y-m-d H:i:s');

    $senderEmail =
        'reply-orchestrator-'
        . $suffix
        . '@example.test';

    /*
    |--------------------------------------------------------------------------
    | Prospect
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
            replied_at
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
            NOW()
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'TEST REPLY ORCHESTRATOR '
            . strtoupper($suffix),

        'activity' =>
            'TEST',

        'city' =>
            'Ceret',

        'postal_code' =>
            '66400',

        'email' =>
            $senderEmail,

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'reply_orchestrator_test',

        'source_external_id' =>
            'reply-orchestrator-'
            . $suffix
            . '-'
            . bin2hex(random_bytes(8)),

        'status' =>
            'replied',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | E-mail initial déjà envoyé
    |--------------------------------------------------------------------------
    */

    $sourceMessageId =
        '<reply-orchestrator-source-'
        . $suffix
        . '@example.test>';

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
            message_id,
            send_attempts
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_html,
            :body_text,
            :status,
            NOW(),
            NOW(),
            NOW(),
            :message_id,
            1
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'initial',

        'recipient_email' =>
            $senderEmail,

        'subject' =>
            'Test conversation',

        'body_html' =>
            '<p>Message initial</p>',

        'body_text' =>
            'Message initial',

        'status' =>
            'sent',

        'message_id' =>
            $sourceMessageId,
    ]);

    $sourceEmailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Réponse entrante
    |--------------------------------------------------------------------------
    */

    $replyMessageId =
        '<reply-orchestrator-incoming-'
        . $suffix
        . '@example.test>';

    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_replies (
            prospect_id,
            prospect_email_id,
            sender_email,
            subject,
            body_text,
            message_id,
            in_reply_to,
            classification,
            received_at
        ) VALUES (
            :prospect_id,
            :prospect_email_id,
            :sender_email,
            :subject,
            :body_text,
            :message_id,
            :in_reply_to,
            :classification,
            NOW()
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $sourceEmailId,

        'sender_email' =>
            $senderEmail,

        'subject' =>
            'Re: Test conversation',

        'body_text' =>
            'Bonjour, je suis interesse.',

        'message_id' =>
            $replyMessageId,

        'in_reply_to' =>
            $sourceMessageId,

        'classification' =>
            'positive',
    ]);

    $replyId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Réponse sortante programmée
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        INSERT INTO prospect_emails (
            prospect_id,
            source_reply_id,
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
            :source_reply_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_html,
            :body_text,
            :status,
            NOW(),
            :scheduled_at,
            0
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'source_reply_id' =>
            $replyId,

        'email_type' =>
            'manual',

        'recipient_email' =>
            $senderEmail,

        'subject' =>
            'Re: Test conversation',

        'body_html' =>
            '<p>Merci pour votre retour.</p>',

        'body_text' =>
            'Merci pour votre retour.',

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $scheduledAt,
    ]);

    return [
        'prospect_id' =>
            $prospectId,

        'email_id' =>
            (int) $pdo->lastInsertId(),

        'reply_id' =>
            $replyId,

        'source_message_id' =>
            $sourceMessageId,

        'reply_message_id' =>
            $replyMessageId,
    ];
}

function createFollowUpFixture(
    PDO $pdo,
    string $suffix
): array {
    $recipientEmail =
        'orchestrator-follow-up-'
        . $suffix
        . '@example.test';

    $initialMessageId =
        '<follow-up-initial-'
        . $suffix
        . '@example.test>';

    $scheduledAt =
        (new DateTimeImmutable('-5 minutes'))
            ->format('Y-m-d H:i:s');

    /*
    |--------------------------------------------------------------------------
    | Prospect
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
            status
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
            :status
        )
        '
    );

    $statement->execute([
        'company_name' =>
            'TEST FOLLOW UP '
            . strtoupper($suffix),

        'activity' =>
            'TEST',

        'city' =>
            'Ceret',

        'postal_code' =>
            '66400',

        'email' =>
            $recipientEmail,

        'email_source_url' =>
            'https://example.test/contact',

        'email_type' =>
            'generic',

        'email_confidence' =>
            100,

        'source' =>
            'orchestrator_test',

        'source_external_id' =>
            'follow-up-orchestrator-'
            . $suffix
            . '-'
            . bin2hex(random_bytes(8)),

        'status' =>
            'follow_up',
    ]);

    $prospectId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Premier contact deja envoye
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
            sent_at,
            send_attempts,
            message_id
        ) VALUES (
            :prospect_id,
            :email_type,
            :recipient_email,
            :subject,
            :body_html,
            :body_text,
            :status,
            NOW(),
            1,
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
            $recipientEmail,

        'subject' =>
            'Test follow-up',

        'body_html' =>
            '<p>Premier contact</p>',

        'body_text' =>
            'Premier contact',

        'status' =>
            'sent',

        'message_id' =>
            $initialMessageId,
    ]);

    $initialEmailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | FU1 due
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
            :scheduled_at,
            0
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $prospectId,

        'email_type' =>
            'follow_up_1',

        'recipient_email' =>
            $recipientEmail,

        'subject' =>
            'Re: Test follow-up',

        'body_html' =>
            '<p>Relance 1</p>',

        'body_text' =>
            'Relance 1',

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $scheduledAt,
    ]);

    $followUp1EmailId =
        (int) $pdo->lastInsertId();

    return [
        'prospect_id' =>
            $prospectId,

        'initial_email_id' =>
            $initialEmailId,

        'follow_up_1_email_id' =>
            $followUp1EmailId,

        'recipient_email' =>
            $recipientEmail,

        'initial_message_id' =>
            $initialMessageId,

        'scheduled_at' =>
            $scheduledAt,
    ];
}

try {
    /*
    |--------------------------------------------------------------------------
    | Scénario 1 : succès SMTP simulé
    |--------------------------------------------------------------------------
    */

    $successFixture =
        createFixture(
            $pdo,
            'success'
        );

    $prospectIds[] =
        $successFixture['prospect_id'];

    $now =
        new DateTimeImmutable();

    $dueEmails =
        $repository
            ->findDueScheduledInitialEmails(
                $now,
                10
            );

    $successEmail = null;

    foreach ($dueEmails as $email) {
        if (
            (int) $email['email_id']
            === $successFixture['email_id']
        ) {
            $successEmail = $email;

            break;
        }
    }

    if ($successEmail === null) {
        throw new RuntimeException(
            'Fixture succès introuvable parmi les e-mails dus.'
        );
    }

    echo 'Fixture succès détectée : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    */

    $guardResult =
        $sendGuard->evaluate(
            $successEmail
        );

    if (!$guardResult['allowed']) {
        throw new RuntimeException(
            'Le SendGuard a refusé le fixture succès : '
            . implode(
                ' | ',
                $guardResult['reasons']
            )
        );
    }

    echo 'Guard succès : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Préparation
    |--------------------------------------------------------------------------
    */

    $message =
        $mailer->prepare(
            $successEmail,
            $guardResult
        );

    if (
        empty($message['message_id'])
    ) {
        throw new RuntimeException(
            'Message-ID absent après préparation.'
        );
    }

    echo 'Préparation message : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Claim
    |--------------------------------------------------------------------------
    */

    $claimed =
        $repository
            ->claimScheduledInitialEmail(
                $successFixture['email_id'],
                (string) $message['message_id'],
                3
            );

    if (
        $claimed['status']
        !== 'sending'
    ) {
        throw new RuntimeException(
            'Le fixture succès devrait être sending.'
        );
    }

    if (
        $claimed['message_id']
        !== $message['message_id']
    ) {
        throw new RuntimeException(
            'Le Message-ID du claim est incorrect.'
        );
    }

    echo 'Claim succès : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Fake SMTP succès
    |--------------------------------------------------------------------------
    */

    $successTransport =
        new FakeProspectMailTransport(
            true
        );

    $transportResult =
        $successTransport->send(
            $message
        );

    if (
        $transportResult['success']
        !== true
    ) {
        throw new RuntimeException(
            'Le transport simulé aurait dû réussir.'
        );
    }

    if (
        $transportResult['message_id']
        !== $message['message_id']
    ) {
        throw new RuntimeException(
            'Le transport simulé a modifié le Message-ID.'
        );
    }

    echo 'Fake SMTP succès : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Persistance succès
    |--------------------------------------------------------------------------
    */

    $repository
        ->markInitialEmailSent(
            $successFixture['email_id'],
            (string) $message['message_id']
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
        'email_id' =>
            $successFixture['email_id'],
    ]);

    $successState =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if ($successState === false) {
        throw new RuntimeException(
            'État succès introuvable.'
        );
    }

    if (
        $successState['email_status']
        !== 'sent'
    ) {
        throw new RuntimeException(
            'Le fixture succès devrait être sent.'
        );
    }

    if (
        $successState['prospect_status']
        !== 'contacted'
    ) {
        throw new RuntimeException(
            'Le prospect succès devrait être contacted.'
        );
    }

    if (
        empty($successState['sent_at'])
        || empty($successState['contacted_at'])
    ) {
        throw new RuntimeException(
            'Dates de succès absentes.'
        );
    }

    if (
        $successState['message_id']
        !== $message['message_id']
    ) {
        throw new RuntimeException(
            'Message-ID succès incorrect.'
        );
    }

    if (
        (int) $successState['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'Une seule tentative était attendue.'
        );
    }

    echo 'Persistance sent/contacted : OK'
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Scénario 2 : échec SMTP simulé
    |--------------------------------------------------------------------------
    */

    $failureFixture =
        createFixture(
            $pdo,
            'failure'
        );

    $prospectIds[] =
        $failureFixture['prospect_id'];

    $dueEmails =
        $repository
            ->findDueScheduledInitialEmails(
                new DateTimeImmutable(),
                10
            );

    $failureEmail = null;

    foreach ($dueEmails as $email) {
        if (
            (int) $email['email_id']
            === $failureFixture['email_id']
        ) {
            $failureEmail = $email;

            break;
        }
    }

    if ($failureEmail === null) {
        throw new RuntimeException(
            'Fixture échec introuvable parmi les e-mails dus.'
        );
    }

    echo 'Fixture échec détectée : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Guard
    |--------------------------------------------------------------------------
    */

    $guardResult =
        $sendGuard->evaluate(
            $failureEmail
        );

    if (!$guardResult['allowed']) {
        throw new RuntimeException(
            'Le SendGuard a refusé le fixture échec : '
            . implode(
                ' | ',
                $guardResult['reasons']
            )
        );
    }

    echo 'Guard échec : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Préparation
    |--------------------------------------------------------------------------
    */

    $failureMessage =
        $mailer->prepare(
            $failureEmail,
            $guardResult
        );

    /*
    |--------------------------------------------------------------------------
    | Claim
    |--------------------------------------------------------------------------
    */

    $repository
        ->claimScheduledInitialEmail(
            $failureFixture['email_id'],
            (string) $failureMessage[
                'message_id'
            ],
            3
        );

    echo 'Claim échec : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Fake SMTP échec
    |--------------------------------------------------------------------------
    */

    $failureTransport =
        new FakeProspectMailTransport(
            false
        );

    $transportResult =
        $failureTransport->send(
            $failureMessage
        );

    if (
        $transportResult['success']
        !== false
    ) {
        throw new RuntimeException(
            'Le transport simulé aurait dû échouer.'
        );
    }

    echo 'Fake SMTP échec : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    */

    $failureResult =
        $repository
            ->markInitialEmailFailed(
                $failureFixture['email_id'],
                (string) $transportResult['error'],
                3,
                30
            );

    if (
        $failureResult['retry_scheduled']
        !== true
    ) {
        throw new RuntimeException(
            'Un retry aurait dû être programmé.'
        );
    }

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.error_message,
            e.message_id,
            e.send_attempts,
            e.sent_at,
            p.status AS prospect_status,
            p.contacted_at
        FROM prospect_emails e
        INNER JOIN prospects p
            ON p.id = e.prospect_id
        WHERE e.id = :email_id
        '
    );

    $statement->execute([
        'email_id' =>
            $failureFixture['email_id'],
    ]);

    $failureState =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if ($failureState === false) {
        throw new RuntimeException(
            'État échec introuvable.'
        );
    }

    if (
        $failureState['email_status']
        !== 'scheduled'
    ) {
        throw new RuntimeException(
            'L’e-mail en échec devrait être reprogrammé.'
        );
    }

    if (
        $failureState['prospect_status']
        !== 'scheduled'
    ) {
        throw new RuntimeException(
            'Le prospect en échec doit rester scheduled.'
        );
    }

    if (
        (int) $failureState['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'Une tentative était attendue.'
        );
    }

    if (
        empty($failureState['message_id'])
    ) {
        throw new RuntimeException(
            'Le Message-ID doit être conservé après échec.'
        );
    }

    if (
        $failureState['error_message']
        !== 'Fake SMTP failure'
    ) {
        throw new RuntimeException(
            'Erreur SMTP simulée non persistée.'
        );
    }

    if (
        $failureState['sent_at'] !== null
        || $failureState['contacted_at'] !== null
    ) {
        throw new RuntimeException(
            'Un échec ne doit pas être marqué comme envoyé.'
        );
    }

    echo 'Retry après faux SMTP : OK'
        . PHP_EOL;

    echo 'Message-ID conservé : OK'
        . PHP_EOL;

    echo 'Prospect reste scheduled : OK'
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Scénario 3 : réponse programmée avec threading
    |--------------------------------------------------------------------------
    */

    $replyFixture =
        createReplyFixture(
            $pdo,
            'success'
        );

    $prospectIds[] =
        $replyFixture['prospect_id'];

    $now =
        new DateTimeImmutable();

    $dueReplies =
        $repository
            ->findDueScheduledReplyEmails(
                $now,
                50
            );

    $replyEmail = null;

    foreach ($dueReplies as $email) {
        if (
            (int) $email['email_id']
            === $replyFixture['email_id']
        ) {
            $replyEmail = $email;

            break;
        }
    }

    if ($replyEmail === null) {
        throw new RuntimeException(
            'Fixture réponse introuvable parmi les réponses dues.'
        );
    }

    echo 'Fixture réponse détectée : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Guard réponse
    |--------------------------------------------------------------------------
    */

    $replyGuardResult =
        $replySendGuard->evaluate(
            $replyEmail
        );

    if (!$replyGuardResult['allowed']) {
        throw new RuntimeException(
            'Le ReplySendGuard a refusé la réponse : '
            . implode(
                ' | ',
                $replyGuardResult['reasons']
            )
        );
    }

    echo 'Guard réponse : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Préparation + threading
    |--------------------------------------------------------------------------
    */

    $replyMessage =
        $mailer->prepare(
            $replyEmail,
            $replyGuardResult
        );

    if (
        $replyMessage['in_reply_to']
        !== $replyFixture['reply_message_id']
    ) {
        throw new RuntimeException(
            'In-Reply-To incorrect.'
        );
    }

    $expectedReferences = [
        $replyFixture['source_message_id'],
        $replyFixture['reply_message_id'],
    ];

    if (
        $replyMessage['references']
        !== $expectedReferences
    ) {
        throw new RuntimeException(
            'References incorrectes.'
        );
    }

    echo 'Threading réponse : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Claim réponse
    |--------------------------------------------------------------------------
    */

    $claimedReply =
        $repository
            ->claimScheduledReplyEmail(
                $replyFixture['email_id'],
                (string) $replyMessage['message_id'],
                $now,
                3
            );

    if (
        $claimedReply['status']
        !== 'sending'
    ) {
        throw new RuntimeException(
            'La réponse devrait être sending.'
        );
    }

    echo 'Claim réponse : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Guard final réponse
    |--------------------------------------------------------------------------
    */

    $claimedReplyForGuard =
        $replyEmail;

    $claimedReplyForGuard['email_status'] =
        'sending';

    $claimedReplyForGuard['status'] =
        'sending';

    $claimedReplyForGuard['send_attempts'] =
        (int) $replyEmail['send_attempts']
        + 1;

    $finalReplyGuard =
        $replySendGuard->evaluate(
            $claimedReplyForGuard
        );

    if (!$finalReplyGuard['allowed']) {
        throw new RuntimeException(
            'Le ReplySendGuard final a refusé la réponse : '
            . implode(
                ' | ',
                $finalReplyGuard['reasons']
            )
        );
    }

    echo 'Guard final réponse : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Fake SMTP réponse
    |--------------------------------------------------------------------------
    */

    $replyTransport =
        new FakeProspectMailTransport(
            true
        );

    $replyTransportResult =
        $replyTransport->send(
            $replyMessage
        );

    if (
        $replyTransportResult['success']
        !== true
    ) {
        throw new RuntimeException(
            'Le faux SMTP réponse aurait dû réussir.'
        );
    }

    if (
        $replyTransportResult['message_id']
        !== $replyMessage['message_id']
    ) {
        throw new RuntimeException(
            'Le Message-ID réponse a été modifié.'
        );
    }

    echo 'Fake SMTP réponse : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Persistance réponse
    |--------------------------------------------------------------------------
    */

    $repository
        ->markReplyEmailSent(
            $replyFixture['email_id'],
            (string) $replyMessage['message_id']
        );

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.sent_at,
            e.send_attempts,
            e.message_id,
            p.status AS prospect_status
        FROM prospect_emails e
        INNER JOIN prospects p
            ON p.id = e.prospect_id
        WHERE e.id = :email_id
        '
    );

    $statement->execute([
        'email_id' =>
            $replyFixture['email_id'],
    ]);

    $replyState =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if ($replyState === false) {
        throw new RuntimeException(
            'État réponse introuvable.'
        );
    }

    if (
        $replyState['email_status']
        !== 'sent'
    ) {
        throw new RuntimeException(
            'La réponse devrait être sent.'
        );
    }

    if (
        $replyState['prospect_status']
        !== 'replied'
    ) {
        throw new RuntimeException(
            'Le prospect doit rester replied.'
        );
    }

    if (
        (int) $replyState['send_attempts']
        !== 1
    ) {
        throw new RuntimeException(
            'Une seule tentative réponse était attendue.'
        );
    }

    if (
        empty($replyState['sent_at'])
    ) {
        throw new RuntimeException(
            'sent_at réponse absent.'
        );
    }

    if (
        $replyState['message_id']
        !== $replyMessage['message_id']
    ) {
        throw new RuntimeException(
            'Message-ID réponse incorrect.'
        );
    }

    echo 'Persistance réponse sent : OK'
        . PHP_EOL;

    echo 'Prospect reste replied : OK'
        . PHP_EOL;

    echo PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Scenario 4 : follow-up 1 puis follow-up 2
    |--------------------------------------------------------------------------
    */

    $followUpFixture =
        createFollowUpFixture(
            $pdo,
            'success'
        );

    $prospectIds[] =
        $followUpFixture['prospect_id'];

    $now =
        new DateTimeImmutable();

    /*
    |--------------------------------------------------------------------------
    | FU1 : selection
    |--------------------------------------------------------------------------
    */

    $dueFollowUps =
        $repository
            ->findDueScheduledFollowUpEmails(
                $now,
                50
            );

    $followUp1Email = null;

    foreach ($dueFollowUps as $email) {
        if (
            (int) $email['email_id']
            === $followUpFixture[
                'follow_up_1_email_id'
            ]
        ) {
            $followUp1Email = $email;

            break;
        }
    }

    if ($followUp1Email === null) {
        throw new RuntimeException(
            'Fixture FU1 introuvable parmi les relances dues.'
        );
    }

    echo 'Fixture FU1 detectee : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU1 : guard
    |--------------------------------------------------------------------------
    */

    $followUp1Guard =
        $followUpSendGuard->evaluate(
            $followUp1Email
        );

    if (!$followUp1Guard['allowed']) {
        throw new RuntimeException(
            'FollowUpSendGuard FU1 refuse : '
            . implode(
                ' | ',
                $followUp1Guard['reasons']
            )
        );
    }

    echo 'Guard FU1 : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU1 : preparation + threading
    |--------------------------------------------------------------------------
    */

    $followUp1Message =
        $mailer->prepare(
            $followUp1Email,
            $followUp1Guard
        );

    if (
        $followUp1Message['in_reply_to']
        !== $followUpFixture[
            'initial_message_id'
        ]
    ) {
        throw new RuntimeException(
            'In-Reply-To FU1 incorrect.'
        );
    }

    if (
        $followUp1Message['references']
        !== [
            $followUpFixture[
                'initial_message_id'
            ],
        ]
    ) {
        throw new RuntimeException(
            'References FU1 incorrectes.'
        );
    }

    echo 'Threading FU1 : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU1 : claim
    |--------------------------------------------------------------------------
    */

    $claimedFollowUp1 =
        $repository
            ->claimScheduledFollowUpEmail(
                $followUpFixture[
                    'follow_up_1_email_id'
                ],
                (string) $followUp1Message[
                    'message_id'
                ],
                $now,
                3
            );

    if (
        $claimedFollowUp1['status']
        !== 'sending'
    ) {
        throw new RuntimeException(
            'FU1 devrait etre sending.'
        );
    }

    echo 'Claim FU1 : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU1 : guard final
    |--------------------------------------------------------------------------
    */

    $claimedFollowUp1ForGuard =
        $followUp1Email;

    $claimedFollowUp1ForGuard[
        'email_status'
    ] = 'sending';

    $claimedFollowUp1ForGuard[
        'status'
    ] = 'sending';

    $claimedFollowUp1ForGuard[
        'send_attempts'
    ] =
        (int) $followUp1Email[
            'send_attempts'
        ] + 1;

    $finalFollowUp1Guard =
        $followUpSendGuard->evaluate(
            $claimedFollowUp1ForGuard
        );

    if (!$finalFollowUp1Guard['allowed']) {
        throw new RuntimeException(
            'FollowUpSendGuard final FU1 refuse : '
            . implode(
                ' | ',
                $finalFollowUp1Guard['reasons']
            )
        );
    }

    echo 'Guard final FU1 : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU1 : fake SMTP
    |--------------------------------------------------------------------------
    */

    $followUpTransport =
        new FakeProspectMailTransport(
            true
        );

    $followUp1TransportResult =
        $followUpTransport->send(
            $followUp1Message
        );

    if (
        $followUp1TransportResult['success']
        !== true
        || $followUp1TransportResult[
            'message_id'
        ] !== $followUp1Message['message_id']
    ) {
        throw new RuntimeException(
            'Fake SMTP FU1 incorrect.'
        );
    }

    echo 'Fake SMTP FU1 : OK'
        . PHP_EOL;

    $repository
        ->markFollowUpEmailSent(
            $followUpFixture[
                'follow_up_1_email_id'
            ],
            (string) $followUp1Message[
                'message_id'
            ]
        );

    echo 'Persistance FU1 sent : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | Creation de FU2 apres FU1 sent
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
            :scheduled_at,
            0
        )
        '
    );

    $statement->execute([
        'prospect_id' =>
            $followUpFixture[
                'prospect_id'
            ],

        'email_type' =>
            'follow_up_2',

        'recipient_email' =>
            $followUpFixture[
                'recipient_email'
            ],

        'subject' =>
            'Re: Test follow-up',

        'body_html' =>
            '<p>Relance 2</p>',

        'body_text' =>
            'Relance 2',

        'status' =>
            'scheduled',

        'scheduled_at' =>
            $followUpFixture[
                'scheduled_at'
            ],
    ]);

    $followUp2EmailId =
        (int) $pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | FU2 : selection
    |--------------------------------------------------------------------------
    */

    $dueFollowUps =
        $repository
            ->findDueScheduledFollowUpEmails(
                new DateTimeImmutable(),
                50
            );

    $followUp2Email = null;

    foreach ($dueFollowUps as $email) {
        if (
            (int) $email['email_id']
            === $followUp2EmailId
        ) {
            $followUp2Email = $email;

            break;
        }
    }

    if ($followUp2Email === null) {
        throw new RuntimeException(
            'Fixture FU2 introuvable parmi les relances dues.'
        );
    }

    echo 'Fixture FU2 detectee : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU2 : guard + threading
    |--------------------------------------------------------------------------
    */

    $followUp2Guard =
        $followUpSendGuard->evaluate(
            $followUp2Email
        );

    if (!$followUp2Guard['allowed']) {
        throw new RuntimeException(
            'FollowUpSendGuard FU2 refuse : '
            . implode(
                ' | ',
                $followUp2Guard['reasons']
            )
        );
    }

    echo 'Guard FU2 : OK'
        . PHP_EOL;

    $followUp2Message =
        $mailer->prepare(
            $followUp2Email,
            $followUp2Guard
        );

    if (
        $followUp2Message['in_reply_to']
        !== $followUp1Message['message_id']
    ) {
        throw new RuntimeException(
            'In-Reply-To FU2 incorrect.'
        );
    }

    if (
        $followUp2Message['references']
        !== [
            $followUpFixture[
                'initial_message_id'
            ],
            $followUp1Message[
                'message_id'
            ],
        ]
    ) {
        throw new RuntimeException(
            'References FU2 incorrectes.'
        );
    }

    echo 'Threading FU2 : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU2 : claim + guard final
    |--------------------------------------------------------------------------
    */

    $claimedFollowUp2 =
        $repository
            ->claimScheduledFollowUpEmail(
                $followUp2EmailId,
                (string) $followUp2Message[
                    'message_id'
                ],
                new DateTimeImmutable(),
                3
            );

    if (
        $claimedFollowUp2['status']
        !== 'sending'
    ) {
        throw new RuntimeException(
            'FU2 devrait etre sending.'
        );
    }

    echo 'Claim FU2 : OK'
        . PHP_EOL;

    $claimedFollowUp2ForGuard =
        $followUp2Email;

    $claimedFollowUp2ForGuard[
        'email_status'
    ] = 'sending';

    $claimedFollowUp2ForGuard[
        'status'
    ] = 'sending';

    $claimedFollowUp2ForGuard[
        'send_attempts'
    ] =
        (int) $followUp2Email[
            'send_attempts'
        ] + 1;

    $finalFollowUp2Guard =
        $followUpSendGuard->evaluate(
            $claimedFollowUp2ForGuard
        );

    if (!$finalFollowUp2Guard['allowed']) {
        throw new RuntimeException(
            'FollowUpSendGuard final FU2 refuse : '
            . implode(
                ' | ',
                $finalFollowUp2Guard['reasons']
            )
        );
    }

    echo 'Guard final FU2 : OK'
        . PHP_EOL;

    /*
    |--------------------------------------------------------------------------
    | FU2 : fake SMTP + persistence
    |--------------------------------------------------------------------------
    */

    $followUp2TransportResult =
        $followUpTransport->send(
            $followUp2Message
        );

    if (
        $followUp2TransportResult['success']
        !== true
        || $followUp2TransportResult[
            'message_id'
        ] !== $followUp2Message['message_id']
    ) {
        throw new RuntimeException(
            'Fake SMTP FU2 incorrect.'
        );
    }

    echo 'Fake SMTP FU2 : OK'
        . PHP_EOL;

    $repository
        ->markFollowUpEmailSent(
            $followUp2EmailId,
            (string) $followUp2Message[
                'message_id'
            ]
        );

    /*
    |--------------------------------------------------------------------------
    | Etat final
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            e.status AS email_status,
            e.sent_at,
            e.send_attempts,
            e.message_id,
            p.status AS prospect_status
        FROM prospect_emails e
        INNER JOIN prospects p
            ON p.id = e.prospect_id
        WHERE e.id = :email_id
        '
    );

    $statement->execute([
        'email_id' =>
            $followUp2EmailId,
    ]);

    $followUp2State =
        $statement->fetch(
            PDO::FETCH_ASSOC
        );

    if ($followUp2State === false) {
        throw new RuntimeException(
            'Etat FU2 introuvable.'
        );
    }

    if (
        $followUp2State['email_status']
        !== 'sent'
    ) {
        throw new RuntimeException(
            'FU2 devrait etre sent.'
        );
    }

    if (
        $followUp2State['prospect_status']
        !== 'follow_up'
    ) {
        throw new RuntimeException(
            'Le prospect doit rester follow_up.'
        );
    }

    if (
        (int) $followUp2State[
            'send_attempts'
        ] !== 1
    ) {
        throw new RuntimeException(
            'FU2 devrait avoir une seule tentative.'
        );
    }

    if (
        empty($followUp2State['sent_at'])
    ) {
        throw new RuntimeException(
            'sent_at FU2 absent.'
        );
    }

    if (
        $followUp2State['message_id']
        !== $followUp2Message[
            'message_id'
        ]
    ) {
        throw new RuntimeException(
            'Message-ID FU2 incorrect.'
        );
    }

    echo 'Persistance FU2 sent : OK'
        . PHP_EOL;

    echo 'Prospect reste follow_up : OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo 'Orchestration SMTP simulée : OK'
        . PHP_EOL;

} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    foreach ($prospectIds as $prospectId) {
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
            DELETE FROM prospect_replies
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
    }

    echo 'Données temporaires supprimées.'
        . PHP_EOL;
}
