<?php

declare(strict_types=1);

final class ReplyDraftManager
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function updateDraft(
        int $emailId,
        string $subject,
        string $bodyText
    ): void {
        $email =
            $this->loadDraft(
                $emailId
            );

        $subject =
            trim($subject);

        $bodyText =
            trim($bodyText);

        if ($subject === '') {
            throw new InvalidArgumentException(
                'L’objet du brouillon est obligatoire.'
            );
        }

        if ($bodyText === '') {
            throw new InvalidArgumentException(
                'Le message du brouillon est obligatoire.'
            );
        }

        if (mb_strlen($subject) > 255) {
            throw new InvalidArgumentException(
                'L’objet du brouillon est trop long.'
            );
        }

        $stmt =
            $this->pdo->prepare(
                "
                UPDATE prospect_emails
                SET
                    subject = :subject,
                    body_text = :body_text
                WHERE
                    id = :id
                    AND email_type = 'manual'
                    AND source_reply_id IS NOT NULL
                    AND status = 'draft'
                "
            );

        $stmt->execute([
            'subject' =>
                $subject,

            'body_text' =>
                $bodyText,

            'id' =>
                $emailId,
        ]);

        if ($stmt->rowCount() > 0) {
            $this->insertEvent(
                (int) $email['prospect_id'],
                (int) $email['source_reply_id'],
                $emailId,
                'prospect_reply_draft_updated',
                'Brouillon de réponse modifié.'
            );
        }
    }

    public function approveDraft(
        int $emailId,
        string $subject,
        string $bodyText
    ): void {
        $subject =
            trim($subject);

        $bodyText =
            trim($bodyText);

        if ($subject === '') {
            throw new InvalidArgumentException(
                'L’objet du brouillon est obligatoire.'
            );
        }

        if ($bodyText === '') {
            throw new InvalidArgumentException(
                'Le message du brouillon est obligatoire.'
            );
        }

        if (mb_strlen($subject) > 255) {
            throw new InvalidArgumentException(
                'L’objet du brouillon est trop long.'
            );
        }

        $this->pdo->beginTransaction();

        try {
            $email =
                $this->loadDraftForUpdate(
                    $emailId
                );

            /*
             * On sauvegarde les éventuelles
             * modifications du formulaire ET
             * on approuve dans la même opération.
             */
            $stmt =
                $this->pdo->prepare(
                    "
                    UPDATE prospect_emails
                    SET
                        subject = :subject,
                        body_text = :body_text,
                        status = 'approved',
                        approved_at = NOW()
                    WHERE
                        id = :id
                        AND email_type = 'manual'
                        AND source_reply_id IS NOT NULL
                        AND status = 'draft'
                    "
                );

            $stmt->execute([
                'subject' =>
                    $subject,

                'body_text' =>
                    $bodyText,

                'id' =>
                    $emailId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException(
                    'Le brouillon n’a pas pu être validé.'
                );
            }

            $this->insertEvent(
                (int) $email['prospect_id'],
                (int) $email['source_reply_id'],
                $emailId,
                'prospect_reply_draft_approved',
                'Brouillon de réponse validé.'
            );

            $this->pdo->commit();

        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function loadDraft(
        int $emailId
    ): array {
        if ($emailId <= 0) {
            throw new InvalidArgumentException(
                'E-mail invalide.'
            );
        }

        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    id,
                    prospect_id,
                    source_reply_id,
                    status
                FROM prospect_emails
                WHERE
                    id = :id
                    AND email_type = 'manual'
                    AND source_reply_id IS NOT NULL
                LIMIT 1
                "
            );

        $stmt->execute([
            'id' =>
                $emailId,
        ]);

        $email =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($email === false) {
            throw new RuntimeException(
                'Brouillon de réponse introuvable.'
            );
        }

        if ($email['status'] !== 'draft') {
            throw new RuntimeException(
                'Seul un brouillon peut être modifié.'
            );
        }

        return $email;
    }

    private function loadDraftForUpdate(
        int $emailId
    ): array {
        if ($emailId <= 0) {
            throw new InvalidArgumentException(
                'E-mail invalide.'
            );
        }

        $stmt =
            $this->pdo->prepare(
                "
                SELECT
                    id,
                    prospect_id,
                    source_reply_id,
                    status
                FROM prospect_emails
                WHERE
                    id = :id
                    AND email_type = 'manual'
                    AND source_reply_id IS NOT NULL
                LIMIT 1
                FOR UPDATE
                "
            );

        $stmt->execute([
            'id' =>
                $emailId,
        ]);

        $email =
            $stmt->fetch(
                PDO::FETCH_ASSOC
            );

        if ($email === false) {
            throw new RuntimeException(
                'Brouillon de réponse introuvable.'
            );
        }

        if ($email['status'] !== 'draft') {
            throw new RuntimeException(
                'Seul un brouillon peut être validé.'
            );
        }

        return $email;
    }

    private function insertEvent(
        int $prospectId,
        int $replyId,
        int $emailId,
        string $eventType,
        string $description
    ): void {
        $metadata =
            json_encode(
                [
                    'reply_id' =>
                        $replyId,

                    'prospect_email_id' =>
                        $emailId,
                ],
                JSON_THROW_ON_ERROR
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

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
                    :event_type,
                    NULL,
                    NULL,
                    :description,
                    :metadata
                )
                "
            );

        $stmt->execute([
            'prospect_id' =>
                $prospectId,

            'event_type' =>
                $eventType,

            'description' =>
                $description,

            'metadata' =>
                $metadata,
        ]);
    }
}
