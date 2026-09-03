<?php

declare(strict_types=1);

final class IncomingReplyProcessor
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function process(
        array $incomingMessage,
        array $matchedEmail,
        string $classification = 'unknown'
    ): array {
        $classification =
            $this->normalizeClassification(
                $classification
            );
        $messageId = $this->normalizeMessageId(
            $incomingMessage['message_id'] ?? null
        );

        if ($messageId === null) {
            throw new InvalidArgumentException(
                'Le message entrant ne possède pas de Message-ID valide.'
            );
        }

        $prospectId = (int) (
            $matchedEmail['prospect_id'] ?? 0
        );

        $prospectEmailId = (int) (
            $matchedEmail['email_id'] ?? 0
        );

        if ($prospectId <= 0 || $prospectEmailId <= 0) {
            throw new InvalidArgumentException(
                'Rattachement du message entrant invalide.'
            );
        }

        $senderEmail = strtolower(
            trim(
                (string) (
                    $incomingMessage['sender_email']
                    ?? ''
                )
            )
        );

        if (
            filter_var(
                $senderEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Expéditeur du message entrant invalide.'
            );
        }

        $receivedAt = $this->normalizeReceivedAt(
            $incomingMessage['received_at'] ?? null
        );

        $this->pdo->beginTransaction();

        try {
            $existingReply =
                $this->findExistingReply(
                    $messageId
                );

            if ($existingReply !== null) {
                $this->pdo->commit();

                return [
                    'created' => false,
                    'duplicate' => true,
                    'reply_id' =>
                        (int) $existingReply['id'],
                    'prospect_id' =>
                        (int) $existingReply['prospect_id'],
                ];
            }

            $prospect = $this->lockProspect(
                $prospectId
            );

            if ($prospect === null) {
                throw new RuntimeException(
                    'Prospect introuvable.'
                );
            }

            $replyId = $this->insertReply(
                $prospectId,
                $prospectEmailId,
                $senderEmail,
                $messageId,
                $incomingMessage,
                $receivedAt,
                $classification
            );

            $previousStatus =
                (string) $prospect['status'];

            $prospectStatus =
                $previousStatus;

            $cancelledFollowUps = 0;

            if ($classification !== 'automatic') {
                $prospectStatus =
                    $this->resolveProspectStatusAfterReply(
                        $previousStatus
                    );

                $this->markProspectAsReplied(
                    $prospectId,
                    $prospectStatus,
                    $receivedAt
                );

                $cancelledFollowUps =
                    $this->cancelPendingFollowUps(
                        $prospectId
                    );
            }

            $this->insertEvent(
                $prospectId,
                $previousStatus,
                $prospectStatus,
                $replyId,
                $prospectEmailId,
                $messageId,
                $senderEmail,
                $receivedAt,
                $cancelledFollowUps,
                $classification
            );

            $this->pdo->commit();

            return [
                'created' => true,
                'duplicate' => false,
                'reply_id' => $replyId,
                'prospect_id' => $prospectId,
                'classification' => $classification,
                'previous_status' => $previousStatus,
                'prospect_status' => $prospectStatus,
                'cancelled_follow_ups' =>
                    $cancelledFollowUps,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function findExistingReply(
        string $messageId
    ): ?array {
        $statement = $this->pdo->prepare(
            '
            SELECT
                id,
                prospect_id
            FROM prospect_replies
            WHERE message_id = :message_id
            LIMIT 1
            FOR UPDATE
            '
        );

        $statement->execute([
            'message_id' => $messageId,
        ]);

        $reply = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return $reply !== false
            ? $reply
            : null;
    }

    private function lockProspect(
        int $prospectId
    ): ?array {
        $statement = $this->pdo->prepare(
            '
            SELECT
                id,
                status
            FROM prospects
            WHERE id = :prospect_id
            LIMIT 1
            FOR UPDATE
            '
        );

        $statement->execute([
            'prospect_id' => $prospectId,
        ]);

        $prospect = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return $prospect !== false
            ? $prospect
            : null;
    }

    private function insertReply(
        int $prospectId,
        int $prospectEmailId,
        string $senderEmail,
        string $messageId,
        array $incomingMessage,
        string $receivedAt,
        string $classification
    ): int {
        $statement = $this->pdo->prepare(
            '
            INSERT INTO prospect_replies (
                prospect_id,
                prospect_email_id,
                sender_email,
                subject,
                body_text,
                body_html,
                message_id,
                in_reply_to,
                classification,
                is_read,
                received_at
            ) VALUES (
                :prospect_id,
                :prospect_email_id,
                :sender_email,
                :subject,
                :body_text,
                :body_html,
                :message_id,
                :in_reply_to,
                :classification,
                0,
                :received_at
            )
            '
        );

        $statement->execute([
            'prospect_id' => $prospectId,
            'prospect_email_id' =>
                $prospectEmailId,
            'sender_email' => $senderEmail,
            'subject' => $this->nullableString(
                $incomingMessage['subject']
                    ?? null
            ),
            'body_text' => $this->nullableString(
                $incomingMessage['body_text']
                    ?? null
            ),
            'body_html' => $this->nullableString(
                $incomingMessage['body_html']
                    ?? null
            ),
            'message_id' => $messageId,
            'in_reply_to' =>
                $this->nullableString(
                    $incomingMessage['in_reply_to']
                        ?? null
                ),
            'classification' => $classification,
            'received_at' => $receivedAt,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    private function markProspectAsReplied(
        int $prospectId,
        string $status,
        string $receivedAt
    ): void {
        $statement = $this->pdo->prepare(
            '
            UPDATE prospects
            SET
                status = :status,
                replied_at = COALESCE(
                    replied_at,
                    :replied_at
                )
            WHERE id = :prospect_id
            '
        );

        $statement->execute([
            'status' => $status,
            'replied_at' => $receivedAt,
            'prospect_id' => $prospectId,
        ]);
    }

    private function resolveProspectStatusAfterReply(
        string $currentStatus
    ): string {
        $preservedStatuses = [
            'converted',
            'unsubscribed',
            'rejected',
            'invalid',
        ];

        if (
            in_array(
                $currentStatus,
                $preservedStatuses,
                true
            )
        ) {
            return $currentStatus;
        }

        return 'replied';
    }

    private function cancelPendingFollowUps(
        int $prospectId
    ): int {
        $statement = $this->pdo->prepare(
            '
            UPDATE prospect_emails
            SET
                status = :cancelled_status,
                error_message = NULL
            WHERE prospect_id = :prospect_id
            AND email_type IN (
                :follow_up_1,
                :follow_up_2
            )
            AND status IN (
                :draft_status,
                :pending_status,
                :approved_status,
                :scheduled_status
            )
            AND sent_at IS NULL
            '
        );

        $statement->execute([
            'cancelled_status' => 'cancelled',
            'prospect_id' => $prospectId,
            'follow_up_1' => 'follow_up_1',
            'follow_up_2' => 'follow_up_2',
            'draft_status' => 'draft',
            'pending_status' =>
                'pending_validation',
            'approved_status' => 'approved',
            'scheduled_status' => 'scheduled',
        ]);

        return $statement->rowCount();
    }

    private function insertEvent(
        int $prospectId,
        string $previousStatus,
        string $newStatus,
        int $replyId,
        int $prospectEmailId,
        string $messageId,
        string $senderEmail,
        string $receivedAt,
        int $cancelledFollowUps,
        string $classification
    ): void {
        $metadata = json_encode(
            [
                'reply_id' => $replyId,
                'prospect_email_id' =>
                    $prospectEmailId,
                'message_id' => $messageId,
                'sender_email' => $senderEmail,
                'received_at' => $receivedAt,
                'cancelled_follow_ups' =>
                    $cancelledFollowUps,
                'classification' => $classification,
            ],
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );

        $statement = $this->pdo->prepare(
            '
            INSERT INTO prospect_events (
                prospect_id,
                event_type,
                previous_status,
                new_status,
                description,
                metadata
            ) VALUES (
                :prospect_id,
                :event_type,
                :previous_status,
                :new_status,
                :description,
                :metadata
            )
            '
        );

        $statement->execute([
            'prospect_id' => $prospectId,
            'event_type' =>
                $classification === 'automatic'
                    ? 'prospect_automatic_reply_received'
                    : 'prospect_reply_received',
            'previous_status' =>
                $previousStatus,
            'new_status' => $newStatus,
            'description' =>
                $classification === 'automatic'
                    ? 'Réponse automatique reçue par e-mail.'
                    : 'Réponse reçue par e-mail.',
            'metadata' => $metadata,
        ]);
    }

    private function normalizeClassification(
        string $classification
    ): string {
        $classification = strtolower(
            trim($classification)
        );

        $allowed = [
            'positive',
            'negative',
            'question',
            'unsubscribed',
            'automatic',
            'unknown',
        ];

        if (
            !in_array(
                $classification,
                $allowed,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Classification de réponse invalide.'
            );
        }

        return $classification;
    }

    private function normalizeMessageId(
        mixed $messageId
    ): ?string {
        if (!is_string($messageId)) {
            return null;
        }

        $messageId = trim($messageId);

        if (
            preg_match(
                '/<[^<>@\s]+@[^<>@\s]+>/',
                $messageId,
                $matches
            ) !== 1
        ) {
            return null;
        }

        return $matches[0];
    }

    private function normalizeReceivedAt(
        mixed $receivedAt
    ): string {
        if (!is_string($receivedAt)) {
            throw new InvalidArgumentException(
                'Date de réception invalide.'
            );
        }

        $date = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $receivedAt
        );

        if (
            $date === false
            || $date->format('Y-m-d H:i:s')
                !== $receivedAt
        ) {
            throw new InvalidArgumentException(
                'Date de réception invalide.'
            );
        }

        return $receivedAt;
    }

    private function nullableString(
        mixed $value
    ): ?string {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
