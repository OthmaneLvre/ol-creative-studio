<?php

declare(strict_types=1);

final class IncomingReplyMatcher
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function match(
        ?string $inReplyTo,
        array $references = [],
        ?string $senderEmail = null
    ): ?array {
        $messageIds = $this->collectMessageIds(
            $inReplyTo,
            $references
        );

        foreach ($messageIds as $messageId) {
            $match = $this->findByMessageId(
                $messageId
            );

            if ($match !== null) {
                return $match;
            }
        }

        return $this->findBySenderEmail(
            $senderEmail
        );
    }

    private function collectMessageIds(
        ?string $inReplyTo,
        array $references
    ): array {
        $messageIds = [];

        if ($inReplyTo !== null) {
            $messageId = $this->normalizeMessageId(
                $inReplyTo
            );

            if ($messageId !== null) {
                $messageIds[] = $messageId;
            }
        }

        foreach ($references as $reference) {
            $messageId = $this->normalizeMessageId(
                (string) $reference
            );

            if ($messageId !== null) {
                $messageIds[] = $messageId;
            }
        }

        return array_values(
            array_unique($messageIds)
        );
    }

    private function findByMessageId(
        string $messageId
    ): ?array {
        $statement = $this->pdo->prepare(
            '
            SELECT
                e.id AS email_id,
                e.prospect_id,
                e.email_type,
                e.recipient_email,
                e.message_id,
                e.sent_at,
                e.status,

                p.company_name,
                p.email AS prospect_email,
                p.status AS prospect_status

            FROM prospect_emails e

            INNER JOIN prospects p
                ON p.id = e.prospect_id

            WHERE e.message_id = :message_id
            AND e.status = :status
            AND e.sent_at IS NOT NULL

            LIMIT 2
            '
        );

        $statement->execute([
            'message_id' => $messageId,
            'status' => 'sent',
        ]);

        $matches = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        if (count($matches) !== 1) {
            return null;
        }

        $match = $matches[0];

        $match['match_type'] = 'message_id';
        $match['matched_message_id'] =
            $messageId;

        return $match;
    }

    private function findBySenderEmail(
        ?string $senderEmail
    ): ?array {
        $senderEmail = strtolower(
            trim((string) $senderEmail)
        );

        if (
            $senderEmail === ''
            || filter_var(
                $senderEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            return null;
        }

        $statement = $this->pdo->prepare(
            '
            SELECT
                e.id AS email_id,
                e.prospect_id,
                e.email_type,
                e.recipient_email,
                e.message_id,
                e.sent_at,
                e.status,

                p.company_name,
                p.email AS prospect_email,
                p.status AS prospect_status

            FROM prospect_emails e

            INNER JOIN prospects p
                ON p.id = e.prospect_id

            WHERE LOWER(e.recipient_email)
                = :sender_email

            AND e.status = :status
            AND e.sent_at IS NOT NULL

            ORDER BY
                e.sent_at DESC,
                e.id DESC
            '
        );

        $statement->execute([
            'sender_email' => $senderEmail,
            'status' => 'sent',
        ]);

        $matches = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        if ($matches === []) {
            return null;
        }

        $prospectIds = array_values(
            array_unique(
                array_map(
                    static fn (array $match): int =>
                        (int) $match['prospect_id'],
                    $matches
                )
            )
        );

        if (count($prospectIds) !== 1) {
            return null;
        }

        $match = $matches[0];

        $match['match_type'] =
            'sender_email';

        $match['matched_message_id'] =
            null;

        return $match;
    }

    private function normalizeMessageId(
        string $messageId
    ): ?string {
        $messageId = trim($messageId);

        if ($messageId === '') {
            return null;
        }

        if (
            preg_match(
                '/<[^<>@\s]+@[^<>@\s]+>/',
                $messageId,
                $matches
            ) !== 1
        ) {
            return null;
        }

        return trim($matches[0]);
    }
}
