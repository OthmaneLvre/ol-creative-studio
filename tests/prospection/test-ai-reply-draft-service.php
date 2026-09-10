<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

require_once __DIR__
    . '/../../php/prospection/ReplyGeneratorInterface.php';

require_once __DIR__
    . '/../../php/prospection/AiReplyDraftService.php';


final class FakeReplyGenerator implements ReplyGeneratorInterface
{
    public int $calls = 0;

    public function generate(
        array $prospect,
        array $reply,
        array $outboundEmail
    ): array {
        $this->calls++;

        return [
            'subject' => 'Re: Message de test',
            'body' =>
                'Bonjour, merci pour votre retour. '
                . 'Quel créneau vous conviendrait ?',
        ];
    }
}


echo "=== TEST AI REPLY DRAFT SERVICE ==="
    . PHP_EOL
    . PHP_EOL;

$passed = 0;
$failed = 0;


/*
 * ------------------------------------------------------------
 * Assertions
 * ------------------------------------------------------------
 */

function assertTrue(
    string $name,
    bool $condition
): void {
    global $passed, $failed;

    if ($condition) {
        echo "[OK]   {$name}"
            . PHP_EOL;

        $passed++;
        return;
    }

    echo "[FAIL] {$name}"
        . PHP_EOL;

    $failed++;
}


function assertSameValue(
    string $name,
    mixed $expected,
    mixed $actual
): void {
    global $passed, $failed;

    if ($expected === $actual) {
        echo "[OK]   {$name}"
            . PHP_EOL;

        $passed++;
        return;
    }

    echo "[FAIL] {$name}"
        . PHP_EOL;

    echo "       attendu : "
        . var_export($expected, true)
        . PHP_EOL;

    echo "       obtenu  : "
        . var_export($actual, true)
        . PHP_EOL;

    $failed++;
}


function assertThrows(
    string $name,
    callable $callback,
    string $expectedMessagePart
): void {
    global $passed, $failed;

    try {
        $callback();

        echo "[FAIL] {$name}"
            . " - aucune exception"
            . PHP_EOL;

        $failed++;
    } catch (Throwable $e) {
        if (
            str_contains(
                $e->getMessage(),
                $expectedMessagePart
            )
        ) {
            echo "[OK]   {$name}"
                . PHP_EOL;

            $passed++;
            return;
        }

        echo "[FAIL] {$name}"
            . " - message inattendu : "
            . $e->getMessage()
            . PHP_EOL;

        $failed++;
    }
}


/*
 * ------------------------------------------------------------
 * Helpers DB
 * ------------------------------------------------------------
 */

function createProspect(
    PDO $pdo,
    string $status = 'replied'
): int {
    $stmt =
        $pdo->prepare(
            "
            INSERT INTO prospects (
                company_name,
                activity,
                city,
                email,
                source,
                source_external_id,
                status
            ) VALUES (
                :company_name,
                :activity,
                :city,
                :email,
                :source,
                :source_external_id,
                :status
            )
            "
        );

    $token =
        bin2hex(
            random_bytes(6)
        );

    $stmt->execute([
        'company_name' =>
            'Entreprise Test AI Reply',

        'activity' =>
            'Plomberie',

        'city' =>
            'Céret',

        'email' =>
            "test-ai-reply-{$token}@example.test",

        'source' =>
            'ai_reply_draft_test',

        'source_external_id' =>
            'test-' . $token,

        'status' =>
            $status,
    ]);

    return (int) $pdo->lastInsertId();
}


function createSentEmail(
    PDO $pdo,
    int $prospectId,
    string $recipient
): int {
    $stmt =
        $pdo->prepare(
            "
            INSERT INTO prospect_emails (
                prospect_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status,
                sent_at
            ) VALUES (
                :prospect_id,
                'initial',
                :recipient_email,
                'Message de test',
                'Bonjour, message initial de test.',
                'sent',
                NOW()
            )
            "
        );

    $stmt->execute([
        'prospect_id' =>
            $prospectId,

        'recipient_email' =>
            $recipient,
    ]);

    return (int) $pdo->lastInsertId();
}


function createReply(
    PDO $pdo,
    int $prospectId,
    int $prospectEmailId,
    string $senderEmail,
    string $classification
): int {
    $stmt =
        $pdo->prepare(
            "
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
                'Re: Message de test',
                :body_text,
                :message_id,
                :in_reply_to,
                :classification,
                NOW()
            )
            "
        );

    $token =
        bin2hex(
            random_bytes(8)
        );

    $messageId =
        "<ai-reply-test-{$token}@example.test>";

    $stmt->execute([
        'prospect_id' =>
            $prospectId,

        'prospect_email_id' =>
            $prospectEmailId,

        'sender_email' =>
            $senderEmail,

        'body_text' =>
            'Bonjour, je suis intéressé.',

        'message_id' =>
            $messageId,

        'in_reply_to' =>
            '<outbound-test@example.test>',

        'classification' =>
            $classification,
    ]);

    return (int) $pdo->lastInsertId();
}


function cleanupProspect(
    PDO $pdo,
    int $prospectId
): void {
    $stmt =
        $pdo->prepare(
            "
            DELETE FROM prospects
            WHERE id = :id
            "
        );

    $stmt->execute([
        'id' =>
            $prospectId,
    ]);
}


/*
 * ------------------------------------------------------------
 * Test principal
 * ------------------------------------------------------------
 */

$generator =
    new FakeReplyGenerator();

$service =
    new AiReplyDraftService(
        $pdo,
        $generator
    );

$prospectId = null;

try {
    $prospectId =
        createProspect(
            $pdo
        );

    $stmt =
        $pdo->prepare(
            "
            SELECT email
            FROM prospects
            WHERE id = :id
            "
        );

    $stmt->execute([
        'id' =>
            $prospectId,
    ]);

    $prospect =
        $stmt->fetch();

    $recipientEmail =
        (string) $prospect['email'];

    $sentEmailId =
        createSentEmail(
            $pdo,
            $prospectId,
            $recipientEmail
        );

    $replyId =
        createReply(
            $pdo,
            $prospectId,
            $sentEmailId,
            $recipientEmail,
            'positive'
        );


    /*
     * --------------------------------------------------------
     * 1. Création du brouillon
     * --------------------------------------------------------
     */

    $result =
        $service->generateForReply(
            $replyId
        );

    assertSameValue(
        'Brouillon créé',
        true,
        $result['created']
    );

    assertSameValue(
        'Brouillon non existant initialement',
        false,
        $result['existing']
    );

    assertSameValue(
        'Statut draft retourné',
        'draft',
        $result['status']
    );

    assertSameValue(
        'Générateur appelé une fois',
        1,
        $generator->calls
    );

    $draftId =
        (int) $result['email_id'];


    /*
     * --------------------------------------------------------
     * 2. Vérification BDD
     * --------------------------------------------------------
     */

    $stmt =
        $pdo->prepare(
            "
            SELECT
                prospect_id,
                source_reply_id,
                email_type,
                recipient_email,
                subject,
                body_text,
                status
            FROM prospect_emails
            WHERE id = :id
            "
        );

    $stmt->execute([
        'id' =>
            $draftId,
    ]);

    $draft =
        $stmt->fetch();

    assertTrue(
        'Brouillon présent en BDD',
        $draft !== false
    );

    assertSameValue(
        'Prospect correct',
        $prospectId,
        (int) $draft['prospect_id']
    );

    assertSameValue(
        'source_reply_id correct',
        $replyId,
        (int) $draft['source_reply_id']
    );

    assertSameValue(
        'email_type manual',
        'manual',
        $draft['email_type']
    );

    assertSameValue(
        'status draft',
        'draft',
        $draft['status']
    );

    assertSameValue(
        'Destinataire correct',
        $recipientEmail,
        $draft['recipient_email']
    );


    /*
     * --------------------------------------------------------
     * 3. Événement
     * --------------------------------------------------------
     */

    $stmt =
        $pdo->prepare(
            "
            SELECT
                event_type,
                metadata
            FROM prospect_events
            WHERE
                prospect_id = :prospect_id
                AND event_type =
                    'prospect_reply_draft_generated'
            ORDER BY id DESC
            LIMIT 1
            "
        );

    $stmt->execute([
        'prospect_id' =>
            $prospectId,
    ]);

    $event =
        $stmt->fetch();

    assertTrue(
        'Événement généré',
        $event !== false
    );

    if ($event !== false) {
        $metadata =
            json_decode(
                $event['metadata'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        assertSameValue(
            'Event reply_id correct',
            $replyId,
            (int) $metadata['reply_id']
        );

        assertSameValue(
            'Event email_id correct',
            $draftId,
            (int) $metadata['prospect_email_id']
        );

        assertSameValue(
            'Event classification positive',
            'positive',
            $metadata['classification']
        );
    }


    /*
     * --------------------------------------------------------
     * 4. Idempotence
     * --------------------------------------------------------
     */

    $secondResult =
        $service->generateForReply(
            $replyId
        );

    assertSameValue(
        'Deuxième passage ne crée rien',
        false,
        $secondResult['created']
    );

    assertSameValue(
        'Deuxième passage retourne existing',
        true,
        $secondResult['existing']
    );

    assertSameValue(
        'Même email_id retourné',
        $draftId,
        (int) $secondResult['email_id']
    );

    assertSameValue(
        'Générateur toujours appelé une seule fois',
        1,
        $generator->calls
    );

    $stmt =
        $pdo->prepare(
            "
            SELECT COUNT(*)
            FROM prospect_emails
            WHERE source_reply_id = :reply_id
            "
        );

    $stmt->execute([
        'reply_id' =>
            $replyId,
    ]);

    assertSameValue(
        'Un seul brouillon persiste',
        1,
        (int) $stmt->fetchColumn()
    );


    /*
     * --------------------------------------------------------
     * 5. Classification interdite
     * --------------------------------------------------------
     */

    $negativeReplyId =
        createReply(
            $pdo,
            $prospectId,
            $sentEmailId,
            $recipientEmail,
            'negative'
        );

    assertThrows(
        'Réponse negative refusée',
        static function () use (
            $service,
            $negativeReplyId
        ): void {
            $service->generateForReply(
                $negativeReplyId
            );
        },
        'ne nécessite pas de brouillon IA'
    );

    assertSameValue(
        'Générateur non appelé pour negative',
        1,
        $generator->calls
    );


} finally {
    if (
        is_int($prospectId)
        && $prospectId > 0
    ) {
        cleanupProspect(
            $pdo,
            $prospectId
        );
    }
}


/*
 * ------------------------------------------------------------
 * Résultat
 * ------------------------------------------------------------
 */

echo PHP_EOL;

echo "Tests réussis : {$passed}"
    . PHP_EOL;

echo "Tests échoués : {$failed}"
    . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo PHP_EOL
    . "AI Reply Draft Service : OK"
    . PHP_EOL;

exit(0);
