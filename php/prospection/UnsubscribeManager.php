<?php

declare(strict_types=1);

final class UnsubscribeManager
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function isUnsubscribeRequest(
        string $content
    ): bool {
        $content = $this->normalizeContent($content);

        if ($content === '') {
            return false;
        }

        $patterns = [
            '/^stop[.!]?\s*$/iu',
            '/^désinscri(?:s|vez)[\s-]*moi[.!]?\s*$/iu',
            '/^desinscri(?:s|vez)[\s-]*moi[.!]?\s*$/iu',
            '/\bje (?:ne )?souhaite plus (?:être|etre )?contact[ée]?\b/iu',
            '/\bje ne souhaite plus recevoir (?:vos )?(?:messages|e-mails|emails|mails)\b/iu',
            '/\bmerci de ne plus me contacter\b/iu',
            '/\bne me contactez plus\b/iu',
            '/\bretirez-moi de (?:votre|vos) (?:liste|listes)\b/iu',
            '/\bsupprimez mon adresse de (?:votre|vos) (?:liste|listes)\b/iu',
            '/\bje souhaite me désinscrire\b/iu',
            '/\bje souhaite me desinscrire\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content) === 1) {
                return true;
            }
        }

        return false;
    }

    public function unsubscribe(
        int $prospectId,
        int $replyId,
        string $email,
        string $reason,
        string $unsubscribedAt
    ): array {
        if ($prospectId <= 0) {
            throw new InvalidArgumentException(
                'Prospect invalide.'
            );
        }

        if ($replyId <= 0) {
            throw new InvalidArgumentException(
                'Réponse invalide.'
            );
        }

        $email = strtolower(
            trim($email)
        );

        if (
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Adresse e-mail invalide.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            $reason = 'Demande de désinscription reçue par e-mail.';
        }

        $this->assertDate($unsubscribedAt);

        $this->pdo->beginTransaction();

        try {
            $prospect = $this->lockProspect(
                $prospectId
            );

            if ($prospect === null) {
                throw new RuntimeException(
                    'Prospect introuvable.'
                );
            }

            $reply = $this->lockReply(
                $replyId,
                $prospectId
            );

            if ($reply === null) {
                throw new RuntimeException(
                    'Réponse du prospect introuvable.'
                );
            }

            $previousStatus = (string) $prospect['status'];

            $created = $this->persistUnsubscribe(
                $prospectId,
                $email,
                $reason,
                $unsubscribedAt
            );

            $this->classifyReplyAsUnsubscribed(
                $replyId
            );

            $this->markProspectAsUnsubscribed(
                $prospectId
            );

            $cancelledEmails =
                $this->cancelFutureEmails(
                    $prospectId
                );

            if (
                $previousStatus !== 'unsubscribed'
                || $created
            ) {
                $this->insertEvent(
                    $prospectId,
                    $previousStatus,
                    $replyId,
                    $email,
                    $reason,
                    $unsubscribedAt,
                    $cancelledEmails,
                    $created
                );
            }

            $this->pdo->commit();

            return [
                'unsubscribed' => true,
                'created' => $created,
                'prospect_id' => $prospectId,
                'reply_id' => $replyId,
                'previous_status' => $previousStatus,
                'prospect_status' => 'unsubscribed',
                'cancelled_emails' => $cancelledEmails,
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function persistUnsubscribe(
        int $prospectId,
        string $email,
        string $reason,
        string $unsubscribedAt
    ): bool {
        $statement = $this->pdo->prepare(
            '
            SELECT id
            FROM prospect_unsubscribes
            WHERE LOWER(email) = LOWER(:email)
            LIMIT 1
            FOR UPDATE
            '
        );

        $statement->execute([
            'email' => $email,
        ]);

        $existingId = $statement->fetchColumn();

        if ($existingId !== false) {
            return false;
        }

        $statement = $this->pdo->prepare(
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
            'prospect_id' => $prospectId,
            'email' => $email,
            'reason' => $reason,
            'source' => 'reply',
            'unsubscribed_at' => $unsubscribedAt,
        ]);

        return true;
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

    private function lockReply(
        int $replyId,
        int $prospectId
    ): ?array {
        $statement = $this->pdo->prepare(
            '
            SELECT
                id,
                prospect_id
            FROM prospect_replies
            WHERE id = :reply_id
            AND prospect_id = :prospect_id
            LIMIT 1
            FOR UPDATE
            '
        );

        $statement->execute([
            'reply_id' => $replyId,
            'prospect_id' => $prospectId,
        ]);

        $reply = $statement->fetch(
            PDO::FETCH_ASSOC
        );

        return $reply !== false
            ? $reply
            : null;
    }

    private function classifyReplyAsUnsubscribed(
        int $replyId
    ): void {
        $statement = $this->pdo->prepare(
            '
            UPDATE prospect_replies
            SET classification = :classification
            WHERE id = :reply_id
            '
        );

        $statement->execute([
            'classification' => 'unsubscribed',
            'reply_id' => $replyId,
        ]);
    }

    private function markProspectAsUnsubscribed(
        int $prospectId
    ): void {
        $statement = $this->pdo->prepare(
            '
            UPDATE prospects
            SET status = :status
            WHERE id = :prospect_id
            '
        );

        $statement->execute([
            'status' => 'unsubscribed',
            'prospect_id' => $prospectId,
        ]);
    }

    private function cancelFutureEmails(
        int $prospectId
    ): int {
        $statement = $this->pdo->prepare(
            '
            UPDATE prospect_emails
            SET
                status = :cancelled_status,
                error_message = NULL
            WHERE prospect_id = :prospect_id
            AND sent_at IS NULL
            AND status IN (
                :draft_status,
                :pending_status,
                :approved_status,
                :scheduled_status
            )
            '
        );

        $statement->execute([
            'cancelled_status' => 'cancelled',
            'prospect_id' => $prospectId,
            'draft_status' => 'draft',
            'pending_status' => 'pending_validation',
            'approved_status' => 'approved',
            'scheduled_status' => 'scheduled',
        ]);

        return $statement->rowCount();
    }

    private function insertEvent(
        int $prospectId,
        string $previousStatus,
        int $replyId,
        string $email,
        string $reason,
        string $unsubscribedAt,
        int $cancelledEmails,
        bool $created
    ): void {
        $metadata = json_encode(
            [
                'reply_id' => $replyId,
                'email' => $email,
                'source' => 'reply',
                'reason' => $reason,
                'unsubscribed_at' => $unsubscribedAt,
                'cancelled_emails' => $cancelledEmails,
                'blacklist_created' => $created,
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
                'prospect_unsubscribed',
            'previous_status' =>
                $previousStatus,
            'new_status' => 'unsubscribed',
            'description' =>
                'Désinscription demandée par e-mail.',
            'metadata' => $metadata,
        ]);
    }

    private function normalizeContent(
        string $content
    ): string {
        $content = trim($content);

        $content = preg_replace(
            '/[ \t]+/u',
            ' ',
            $content
        ) ?? $content;

        return $content;
    }

    private function assertDate(
        string $date
    ): void {
        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $date
        );

        if (
            $parsed === false
            || $parsed->format('Y-m-d H:i:s') !== $date
        ) {
            throw new InvalidArgumentException(
                'Date de désinscription invalide.'
            );
        }
    }
}
