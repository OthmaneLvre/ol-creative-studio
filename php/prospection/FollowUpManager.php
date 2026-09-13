<?php

declare(strict_types=1);

final class FollowUpManager
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config
    ) {
    }

    /**
     * Retourne les relances devenues éligibles.
     *
     * Cette méthode ne crée et n'envoie aucun e-mail.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findDueFollowUps(
        DateTimeImmutable $now,
        int $limit = 100
    ): array {
        if ($limit < 1) {
            throw new InvalidArgumentException(
                'Limite invalide.'
            );
        }

        $followUpConfig =
            $this->config['follow_up']
            ?? [];

        if (
            !(bool) (
                $followUpConfig['enabled']
                ?? false
            )
        ) {
            return [];
        }

        $delays =
            $followUpConfig['delays']
            ?? [];

        $followUp1Delay =
            (int) (
                $delays['follow_up_1']
                ?? 5
            );

        $followUp2Delay =
            (int) (
                $delays['follow_up_2']
                ?? 12
            );

        if (
            $followUp1Delay < 1
            || $followUp2Delay <= $followUp1Delay
        ) {
            throw new RuntimeException(
                'Configuration des relances invalide.'
            );
        }

        $statement = $this->pdo->prepare(
            "
            SELECT
                p.id AS prospect_id,
                p.company_name,
                p.email AS prospect_email,
                p.status AS prospect_status,

                initial.id AS initial_email_id,
                initial.recipient_email,
                initial.subject AS initial_subject,
                initial.sent_at AS initial_sent_at,
                initial.message_id AS initial_message_id,

                follow1.id AS follow_up_1_id,
                follow1.status AS follow_up_1_status,
                follow1.sent_at AS follow_up_1_sent_at,

                follow2.id AS follow_up_2_id,
                follow2.status AS follow_up_2_status,
                follow2.sent_at AS follow_up_2_sent_at

            FROM prospects p

            INNER JOIN prospect_emails initial
                ON initial.prospect_id = p.id
                AND initial.email_type = 'initial'
                AND initial.status = 'sent'
                AND initial.sent_at IS NOT NULL

            LEFT JOIN prospect_emails follow1
                ON follow1.prospect_id = p.id
                AND follow1.email_type = 'follow_up_1'

            LEFT JOIN prospect_emails follow2
                ON follow2.prospect_id = p.id
                AND follow2.email_type = 'follow_up_2'

            WHERE p.status IN (
                'contacted',
                'follow_up'
            )

            AND NOT EXISTS (
                SELECT 1
                FROM prospect_replies r
                WHERE r.prospect_id = p.id
                AND r.classification <> 'automatic'
)

            AND NOT EXISTS (
                SELECT 1
                FROM prospect_unsubscribes u
                WHERE LOWER(u.email) =
                    LOWER(initial.recipient_email)
            )

            ORDER BY
                initial.sent_at ASC,
                p.id ASC

            LIMIT {$limit}
            "
        );

        $statement->execute();

        $rows = $statement->fetchAll(
            PDO::FETCH_ASSOC
        );

        $results = [];

        foreach ($rows as $row) {
            $initialSentAt =
                new DateTimeImmutable(
                    (string) $row[
                        'initial_sent_at'
                    ]
                );

            $followUp1DueAt =
                $this->addBusinessDays(
                    $initialSentAt,
                    $followUp1Delay
                );

            $followUp2DueAt =
                $this->addBusinessDays(
                    $initialSentAt,
                    $followUp2Delay
                );

            /*
            |--------------------------------------------------------------------------
            | Relance 1
            |--------------------------------------------------------------------------
            */

            if (
                empty($row['follow_up_1_id'])
                && $now >= $followUp1DueAt
            ) {
                $results[] =
                    $this->buildResult(
                        $row,
                        'follow_up_1',
                        $followUp1DueAt
                    );

                continue;
            }

            /*
            |--------------------------------------------------------------------------
            | Relance 2
            |--------------------------------------------------------------------------
            |
            | On n'autorise la seconde relance que si la première
            | a réellement été envoyée.
            |
            | Si la première relance a échoué définitivement,
            | aucune seconde relance ne sera générée.
            |
            */

            if (
                !empty($row['follow_up_1_id'])
                && $row['follow_up_1_status']
                    === 'sent'
                && !empty(
                    $row['follow_up_1_sent_at']
                )
                && empty(
                    $row['follow_up_2_id']
                )
                && $now >= $followUp2DueAt
            ) {
                $results[] =
                    $this->buildResult(
                        $row,
                        'follow_up_2',
                        $followUp2DueAt
                    );
            }
        }

        return $results;
    }

    public function calculateDueAt(
        DateTimeImmutable $initialSentAt,
        string $followUpType
    ): DateTimeImmutable {
        if (
            !in_array(
                $followUpType,
                [
                    'follow_up_1',
                    'follow_up_2',
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Type de relance invalide.'
            );
        }

        $delays =
            $this->config['follow_up']['delays']
            ?? [];

        $days =
            (int) (
                $delays[$followUpType]
                ?? (
                    $followUpType
                        === 'follow_up_1'
                            ? 5
                            : 12
                )
            );

        if ($days < 1) {
            throw new RuntimeException(
                'Délai de relance invalide.'
            );
        }

        return $this->addBusinessDays(
            $initialSentAt,
            $days
        );
    }

    public function createDueFollowUps(
        DateTimeImmutable $now,
        int $limit = 100
    ): array {
        $dueItems =
            $this->findDueFollowUps(
                $now,
                $limit
            );

        $results = [
            'found' => count($dueItems),
            'created' => 0,
            'skipped' => 0,
            'items' => [],
        ];

        foreach ($dueItems as $item) {
            try {
                $created =
                    $this->createFollowUp(
                        $item,
                        $now
                    );

                if ($created) {
                    $results['created']++;
                } else {
                    $results['skipped']++;
                }

                $results['items'][] = [
                    'prospect_id' =>
                        $item['prospect_id'],
                    'follow_up_type' =>
                        $item['follow_up_type'],
                    'created' =>
                        $created,
                ];
            } catch (Throwable $exception) {
                $results['skipped']++;

                $results['items'][] = [
                    'prospect_id' =>
                        $item['prospect_id'],
                    'follow_up_type' =>
                        $item['follow_up_type'],
                    'created' =>
                        false,
                    'error' =>
                        $exception->getMessage(),
                ];
            }
        }

        return $results;
    }

    private function addBusinessDays(
        DateTimeImmutable $date,
        int $days
    ): DateTimeImmutable {
        if ($days < 0) {
            throw new InvalidArgumentException(
                'Nombre de jours ouvrés invalide.'
            );
        }

        $result = $date;
        $added = 0;

        while ($added < $days) {
            $result =
                $result->modify('+1 day');

            $dayOfWeek =
                (int) $result->format('N');

            if ($dayOfWeek <= 5) {
                $added++;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildResult(
        array $row,
        string $followUpType,
        DateTimeImmutable $dueAt
    ): array {
        return [
            'prospect_id' =>
                (int) $row['prospect_id'],

            'company_name' =>
                (string) $row[
                    'company_name'
                ],

            'prospect_status' =>
                (string) $row[
                    'prospect_status'
                ],

            'recipient_email' =>
                strtolower(
                    trim(
                        (string) $row[
                            'recipient_email'
                        ]
                    )
                ),

            'initial_email_id' =>
                (int) $row[
                    'initial_email_id'
                ],

            'initial_subject' =>
                (string) $row[
                    'initial_subject'
                ],

            'initial_message_id' =>
                (string) (
                    $row[
                        'initial_message_id'
                    ]
                    ?? ''
                ),

            'follow_up_type' =>
                $followUpType,

            'initial_sent_at' =>
                (string) $row[
                    'initial_sent_at'
                ],

            'due_at' =>
                $dueAt->format(
                    'Y-m-d H:i:s'
                ),
        ];
    }

    private function createFollowUp(
        array $item,
        DateTimeImmutable $now
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $prospectId =
                (int) $item['prospect_id'];

            $followUpType =
                (string) $item['follow_up_type'];

            $prospect =
                $this->lockProspect(
                    $prospectId
                );

            if ($prospect === null) {
                throw new RuntimeException(
                    'Prospect introuvable.'
                );
            }

            if (
                !in_array(
                    (string) $prospect['status'],
                    [
                        'contacted',
                        'follow_up',
                    ],
                    true
                )
            ) {
                $this->pdo->rollBack();

                return false;
            }

            if (
                $this->hasReply(
                    $prospectId
                )
            ) {
                $this->pdo->rollBack();

                return false;
            }

            $recipientEmail =
                strtolower(
                    trim(
                        (string) $item[
                            'recipient_email'
                        ]
                    )
                );

            if (
                $this->isUnsubscribed(
                    $recipientEmail
                )
            ) {
                $this->pdo->rollBack();

                return false;
            }

            if (
                $this->followUpExists(
                    $prospectId,
                    $followUpType
                )
            ) {
                $this->pdo->rollBack();

                return false;
            }

            if (
                $followUpType === 'follow_up_2'
                && !$this->wasFirstFollowUpSent(
                    $prospectId
                )
            ) {
                $this->pdo->rollBack();

                return false;
            }

            $subject =
                $this->buildSubject(
                    (string) $item[
                        'initial_subject'
                    ]
                );

            $bodyText =
                $this->buildBodyText(
                    $followUpType,
                    (string) $item[
                        'company_name'
                    ]
                );

            $bodyHtml =
                nl2br(
                    htmlspecialchars(
                        $bodyText,
                        ENT_QUOTES
                        | ENT_SUBSTITUTE,
                        'UTF-8'
                    )
                );

            $scheduledAt =
                new DateTimeImmutable(
                    (string) $item['due_at']
                );

            if ($scheduledAt < $now) {
                $scheduledAt = $now;
            }

            $statement =
                $this->pdo->prepare(
                    '
                    INSERT INTO prospect_emails (
                        prospect_id,
                        email_type,
                        recipient_email,
                        subject,
                        body_html,
                        body_text,
                        status,
                        scheduled_at
                    ) VALUES (
                        :prospect_id,
                        :email_type,
                        :recipient_email,
                        :subject,
                        :body_html,
                        :body_text,
                        :status,
                        :scheduled_at
                    )
                    '
                );

            $statement->execute([
                'prospect_id' =>
                    $prospectId,

                'email_type' =>
                    $followUpType,

                'recipient_email' =>
                    $recipientEmail,

                'subject' =>
                    $subject,

                'body_html' =>
                    $bodyHtml,

                'body_text' =>
                    $bodyText,

                'status' =>
                    'scheduled',

                'scheduled_at' =>
                    $scheduledAt->format(
                        'Y-m-d H:i:s'
                    ),
            ]);

            $emailId =
                (int) $this->pdo
                    ->lastInsertId();

            $previousStatus =
                (string) $prospect['status'];

            $this->markProspectAsFollowUp(
                $prospectId
            );

            $this->insertFollowUpEvent(
                $prospectId,
                $emailId,
                $followUpType,
                $scheduledAt,
                $previousStatus
            );

            $this->pdo->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function lockProspect(
        int $prospectId
    ): ?array {
        $statement =
            $this->pdo->prepare(
                '
                SELECT
                    id,
                    status,
                    email
                FROM prospects
                WHERE id = :prospect_id
                LIMIT 1
                FOR UPDATE
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        $row =
            $statement->fetch(
                PDO::FETCH_ASSOC
            );

        return $row !== false
            ? $row
            : null;
    }

    private function hasReply(
        int $prospectId
    ): bool {
        $statement =
            $this->pdo->prepare(
                "
                SELECT 1
                FROM prospect_replies
                WHERE prospect_id = :prospect_id
                AND classification <> 'automatic'
                LIMIT 1
                "
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        return $statement->fetchColumn()
            !== false;
    }

    private function isUnsubscribed(
        string $email
    ): bool {
        $statement =
            $this->pdo->prepare(
                '
                SELECT 1
                FROM prospect_unsubscribes
                WHERE LOWER(email) =
                    LOWER(:email)
                LIMIT 1
                '
            );

        $statement->execute([
            'email' => $email,
        ]);

        return $statement->fetchColumn()
            !== false;
    }

    private function followUpExists(
        int $prospectId,
        string $followUpType
    ): bool {
        $statement =
            $this->pdo->prepare(
                '
                SELECT 1
                FROM prospect_emails
                WHERE prospect_id = :prospect_id
                AND email_type = :email_type
                LIMIT 1
                '
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,

            'email_type' =>
                $followUpType,
        ]);

        return $statement->fetchColumn()
            !== false;
    }

    private function wasFirstFollowUpSent(
        int $prospectId
    ): bool {
        $statement =
            $this->pdo->prepare(
                "
                SELECT 1
                FROM prospect_emails
                WHERE prospect_id = :prospect_id
                AND email_type = 'follow_up_1'
                AND status = 'sent'
                AND sent_at IS NOT NULL
                LIMIT 1
                "
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        return $statement->fetchColumn()
            !== false;
    }

    private function markProspectAsFollowUp(
        int $prospectId
    ): void {
        $statement =
            $this->pdo->prepare(
                '
                UPDATE prospects
                SET status = :status
                WHERE id = :prospect_id
                AND status IN (
                    :contacted,
                    :follow_up
                )
                '
            );

        $statement->execute([
            'status' =>
                'follow_up',

            'prospect_id' =>
                $prospectId,

            'contacted' =>
                'contacted',

            'follow_up' =>
                'follow_up',
        ]);
    }

    private function insertFollowUpEvent(
        int $prospectId,
        int $emailId,
        string $followUpType,
        DateTimeImmutable $scheduledAt,
        string $previousStatus
    ): void {
        $statement =
            $this->pdo->prepare(
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

        $metadata =
            json_encode(
                [
                    'email_id' =>
                        $emailId,

                    'email_type' =>
                        $followUpType,

                    'scheduled_at' =>
                        $scheduledAt->format(
                            'Y-m-d H:i:s'
                        ),
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            );

        $statement->execute([
            'prospect_id' =>
                $prospectId,

            'event_type' =>
                'prospect_follow_up_scheduled',

            'previous_status' =>
                $previousStatus,

            'new_status' =>
                'follow_up',

            'description' =>
                'Relance programmée.',

            'metadata' =>
                $metadata,
        ]);
    }

    private function buildSubject(
        string $initialSubject
    ): string {
        $initialSubject =
            trim($initialSubject);

        if ($initialSubject === '') {
            return 'Re: OL Creative Studio';
        }

        if (
            preg_match(
                '/^re:/i',
                $initialSubject
            ) === 1
        ) {
            return $initialSubject;
        }

        return 'Re: ' . $initialSubject;
    }

    private function buildBodyText(
        string $followUpType,
        string $companyName
    ): string {
        $companyName =
            trim($companyName);

        if ($followUpType === 'follow_up_1') {
            return
                "Bonjour,\n\n"
                . "Je me permets de revenir vers vous concernant mon précédent message"
                . (
                    $companyName !== ''
                        ? " au sujet de {$companyName}"
                        : ''
                )
                . ".\n\n"
                . "Si le sujet peut vous intéresser, je peux vous envoyer quelques pistes concrètes.\n\n"
                . "Bonne journée,\n"
                . "Othmane\n"
                . "OL Creative Studio";
        }

        return
            "Bonjour,\n\n"
            . "Je me permets un dernier message suite à mon précédent contact"
            . (
                $companyName !== ''
                    ? " concernant {$companyName}"
                    : ''
            )
            . ".\n\n"
            . "Si ce n’est pas un sujet d’actualité, aucun souci, je ne vous relancerai pas davantage.\n\n"
            . "Bonne journée,\n"
            . "Othmane\n"
            . "OL Creative Studio";
    }
}
