<?php

declare(strict_types=1);

require_once __DIR__ . '/ReplyGeneratorInterface.php';

final class AiReplyDraftService
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly ReplyGeneratorInterface $generator
    ) {
    }

    public function generateForReply(
        int $replyId
    ): array {
        if ($replyId <= 0) {
            throw new InvalidArgumentException(
                'Reply ID invalide.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $reply =
                $this->loadReplyForUpdate(
                    $replyId
                );

            if ($reply === null) {
                throw new RuntimeException(
                    'Réponse introuvable.'
                );
            }

            $classification =
                (string) $reply['classification'];

            if (
                !in_array(
                    $classification,
                    [
                        'positive',
                        'question',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'Cette réponse ne nécessite '
                    . 'pas de brouillon IA.'
                );
            }

            $existingDraft =
                $this->findExistingDraft(
                    $replyId
                );

            if ($existingDraft !== null) {
                $this->pdo->commit();

                return [
                    'created' =>
                        false,

                    'existing' =>
                        true,

                    'email_id' =>
                        (int) $existingDraft['id'],

                    'status' =>
                        $existingDraft['status'],

                    'subject' =>
                        $existingDraft['subject'],

                    'body_text' =>
                        $existingDraft['body_text'],
                ];
            }

            $prospect =
                $this->loadProspect(
                    (int) $reply['prospect_id']
                );

            if ($prospect === null) {
                throw new RuntimeException(
                    'Prospect introuvable.'
                );
            }

            $outboundEmail =
                $this->loadOutboundEmail(
                    $reply
                );

            if ($outboundEmail === null) {
                throw new RuntimeException(
                    'E-mail sortant d’origine introuvable.'
                );
            }

            $draft =
                $this->generator->generate(
                    $prospect,
                    [
                        'classification' =>
                            $classification,

                        'body_text' =>
                            $reply['body_text'],
                    ],
                    $outboundEmail
                );

            $emailId =
                $this->insertDraft(
                    $reply,
                    $draft
                );

            $this->insertEvent(
                (int) $reply['prospect_id'],
                $replyId,
                $emailId,
                $classification
            );

            $this->pdo->commit();

            return [
                'created' =>
                    true,

                'existing' =>
                    false,

                'email_id' =>
                    $emailId,

                'status' =>
                    'draft',

                'subject' =>
                    $draft['subject'],

                'body_text' =>
                    $draft['body'],
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    private function loadReplyForUpdate(
        int $replyId
    ): ?array {
        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    id,
                    prospect_id,
                    prospect_email_id,
                    sender_email,
                    subject,
                    body_text,
                    classification,
                    received_at
                FROM prospect_replies
                WHERE id = :id
                LIMIT 1
                FOR UPDATE
                "
            );

        $stmt->execute([
            'id' => $replyId,
        ]);

        $row = $stmt->fetch();

        return $row === false
            ? null
            : $row;
    }

    private function findExistingDraft(
        int $replyId
    ): ?array {
        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    id,
                    status,
                    subject,
                    body_text
                FROM prospect_emails
                WHERE source_reply_id = :reply_id
                LIMIT 1
                "
            );

        $stmt->execute([
            'reply_id' => $replyId,
        ]);

        $row = $stmt->fetch();

        return $row === false
            ? null
            : $row;
    }

    private function loadProspect(
        int $prospectId
    ): ?array {
        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    id,
                    company_name,
                    activity,
                    city
                FROM prospects
                WHERE id = :id
                LIMIT 1
                "
            );

        $stmt->execute([
            'id' => $prospectId,
        ]);

        $row = $stmt->fetch();

        return $row === false
            ? null
            : $row;
    }

    private function loadOutboundEmail(
        array $reply
    ): ?array {
        $prospectEmailId =
            $reply['prospect_email_id']
            ?? null;

        if (
            $prospectEmailId !== null
            && (int) $prospectEmailId > 0
        ) {
            $stmt =
                $this->pdo->prepare(
                    "
                    SELECT
                        id,
                        subject,
                        body_text,
                        email_type,
                        sent_at
                    FROM prospect_emails
                    WHERE
                        id = :id
                        AND prospect_id = :prospect_id
                        AND status = 'sent'
                        AND sent_at IS NOT NULL
                    LIMIT 1
                    "
                );

            $stmt->execute([
                'id' =>
                    (int) $prospectEmailId,

                'prospect_id' =>
                    (int) $reply['prospect_id'],
            ]);

            $row = $stmt->fetch();

            if ($row !== false) {
                return $row;
            }
        }

        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    id,
                    subject,
                    body_text,
                    email_type,
                    sent_at
                FROM prospect_emails
                WHERE
                    prospect_id = :prospect_id
                    AND status = 'sent'
                    AND sent_at IS NOT NULL
                ORDER BY sent_at DESC, id DESC
                LIMIT 1
                "
            );

        $stmt->execute([
            'prospect_id' =>
                (int) $reply['prospect_id'],
        ]);

        $row = $stmt->fetch();

        return $row === false
            ? null
            : $row;
    }

    private function insertDraft(
        array $reply,
        array $draft
    ): int {
        $recipientEmail =
            trim(
                (string) $reply['sender_email']
            );

        if (
            !filter_var(
                $recipientEmail,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Adresse e-mail du prospect invalide.'
            );
        }

        $stmt =
            $this->pdo->prepare(
                "
                INSERT INTO prospect_emails (
                    prospect_id,
                    source_reply_id,
                    email_type,
                    recipient_email,
                    subject,
                    body_text,
                    status
                ) VALUES (
                    :prospect_id,
                    :source_reply_id,
                    'manual',
                    :recipient_email,
                    :subject,
                    :body_text,
                    'draft'
                )
                "
            );

        $stmt->execute([
            'prospect_id' =>
                (int) $reply['prospect_id'],

            'source_reply_id' =>
                (int) $reply['id'],

            'recipient_email' =>
                $recipientEmail,

            'subject' =>
                $draft['subject'],

            'body_text' =>
                $draft['body'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function insertEvent(
        int $prospectId,
        int $replyId,
        int $emailId,
        string $classification
    ): void {
        $stmt =
            $this->pdo->prepare(
                "
                INSERT INTO prospect_events (
                    prospect_id,
                    event_type,
                    previous_status,
                    new_status,
                    description,
                    metadata
                ) VALUES (
                    :prospect_id,
                    'prospect_reply_draft_generated',
                    NULL,
                    NULL,
                    :description,
                    :metadata
                )
                "
            );

        $metadata =
            json_encode(
                [
                    'reply_id' =>
                        $replyId,

                    'prospect_email_id' =>
                        $emailId,

                    'classification' =>
                        $classification,

                    'source' =>
                        'ai_reply_generator',
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

        $stmt->execute([
            'prospect_id' =>
                $prospectId,

            'description' =>
                'Brouillon de réponse IA généré.',

            'metadata' =>
                $metadata,
        ]);
    }
}
