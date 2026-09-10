<?php

declare(strict_types=1);

final class ReplyScheduler
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    public function schedule(
        int $emailId,
        DateTimeImmutable $scheduledAt
    ): array {
        if ($emailId <= 0) {
            throw new InvalidArgumentException(
                'Identifiant e-mail invalide.'
            );
        }

        try {
            $this->pdo->beginTransaction();

            $email =
                $this->loadEmailForUpdate(
                    $emailId
                );

            if ($email === null) {
                throw new RuntimeException(
                    'Brouillon de reponse introuvable.'
                );
            }

            if (
                (string) $email['email_type']
                !== 'manual'
            ) {
                throw new RuntimeException(
                    'Seule une reponse manuelle peut etre programmee.'
                );
            }

            if (
                (int) (
                    $email['source_reply_id']
                    ?? 0
                ) <= 0
            ) {
                throw new RuntimeException(
                    'La reponse source est absente.'
                );
            }

            if (
                (string) $email['status']
                !== 'approved'
            ) {
                throw new RuntimeException(
                    'La reponse doit etre validee avant programmation.'
                );
            }

            if (
                empty($email['approved_at'])
            ) {
                throw new RuntimeException(
                    'La date de validation est absente.'
                );
            }

            if (
                (string) $email['prospect_status']
                !== 'replied'
            ) {
                throw new RuntimeException(
                    'Le prospect n est plus dans l etat replied.'
                );
            }

            if (
                !in_array(
                    (string) $email[
                        'reply_classification'
                    ],
                    [
                        'positive',
                        'question',
                    ],
                    true
                )
            ) {
                throw new RuntimeException(
                    'La classification de la reponse '
                    . 'n autorise pas la programmation.'
                );
            }

            $approvedAt =
                new DateTimeImmutable(
                    (string) $email['approved_at'],
                    $scheduledAt->getTimezone()
                );

            if ($scheduledAt < $approvedAt) {
                throw new RuntimeException(
                    'La programmation ne peut pas preceder la validation.'
                );
            }

            $statement =
                $this->pdo->prepare(
                    '
                    UPDATE prospect_emails
                    SET
                        status = :status,
                        scheduled_at = :scheduled_at,
                        error_message = NULL,
                        updated_at = NOW()
                    WHERE id = :id
                    '
                );

            $statement->execute([
                'status' =>
                    'scheduled',

                'scheduled_at' =>
                    $scheduledAt->format(
                        'Y-m-d H:i:s'
                    ),

                'id' =>
                    $emailId,
            ]);

            $this->insertEvent(
                (int) $email['prospect_id'],
                'prospect_reply_scheduled',
                'Reponse prospect programmee pour envoi.',
                [
                    'reply_id' =>
                        (int) $email[
                            'source_reply_id'
                        ],

                    'prospect_email_id' =>
                        $emailId,

                    'scheduled_at' =>
                        $scheduledAt->format(
                            DATE_ATOM
                        ),
                ]
            );

            $this->pdo->commit();

            return [
                'email_id' =>
                    $emailId,

                'prospect_id' =>
                    (int) $email[
                        'prospect_id'
                    ],

                'reply_id' =>
                    (int) $email[
                        'source_reply_id'
                    ],

                'status' =>
                    'scheduled',

                'scheduled_at' =>
                    $scheduledAt->format(
                        'Y-m-d H:i:s'
                    ),
            ];
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function loadEmailForUpdate(
        int $emailId
    ): ?array {
        $statement =
            $this->pdo->prepare(
                '
                SELECT
                    e.id,
                    e.prospect_id,
                    e.source_reply_id,
                    e.email_type,
                    e.status,
                    e.approved_at,
                    e.scheduled_at,

                    p.status AS prospect_status,

                    r.classification
                        AS reply_classification

                FROM prospect_emails e

                INNER JOIN prospects p
                    ON p.id = e.prospect_id

                INNER JOIN prospect_replies r
                    ON r.id = e.source_reply_id
                    AND r.prospect_id = e.prospect_id

                WHERE e.id = :id

                LIMIT 1
                FOR UPDATE
                '
            );

        $statement->execute([
            'id' =>
                $emailId,
        ]);

        $email =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $email !== false
            ? $email
            : null;
    }

    private function insertEvent(
        int $prospectId,
        string $eventType,
        string $description,
        array $metadata
    ): void {
        $statement =
            $this->pdo->prepare(
                '
                INSERT INTO prospect_events
                (
                    prospect_id,
                    event_type,
                    previous_status,
                    new_status,
                    description,
                    metadata
                )
                VALUES
                (
                    :prospect_id,
                    :event_type,
                    NULL,
                    NULL,
                    :description,
                    :metadata
                )
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,

            'event_type' =>
                $eventType,

            'description' =>
                $description,

            'metadata' =>
                json_encode(
                    $metadata,
                    JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                ),
        ]);
    }
}
