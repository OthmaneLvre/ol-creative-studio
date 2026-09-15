<?php

declare(strict_types=1);

final class ProspectFollowUpSendGuard
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config
    ) {
    }

    /**
     * Vérifie qu'une relance peut réellement être envoyée.
     *
     * Cette méthode ne modifie aucune donnée.
     *
     * @return array<string, mixed>
     */
    public function evaluate(array $email): array
    {
        $reasons = [];

        $sendingConfig =
            $this->config['sending']
            ?? [];

        $followUpConfig =
            $this->config['follow_up']
            ?? [];

        /*
        |--------------------------------------------------------------------------
        | Kill switches
        |--------------------------------------------------------------------------
        */

        $sendingEnabled = (bool) (
            $sendingConfig['enabled']
            ?? false
        );

        if (!$sendingEnabled) {
            $reasons[] =
                'Envoi désactivé par configuration.';
        }

        $followUpEnabled = (bool) (
            $followUpConfig['enabled']
            ?? false
        );

        if (!$followUpEnabled) {
            $reasons[] =
                'Relances désactivées par configuration.';
        }

        /*
        |--------------------------------------------------------------------------
        | Type de message
        |--------------------------------------------------------------------------
        */

        $emailType = (string) (
            $email['email_type']
            ?? ''
        );

        if (
            !in_array(
                $emailType,
                [
                    'follow_up_1',
                    'follow_up_2',
                ],
                true
            )
        ) {
            $reasons[] =
                'Type de relance invalide.';
        }

        /*
        |--------------------------------------------------------------------------
        | Statut e-mail
        |--------------------------------------------------------------------------
        |
        | Avant claim : scheduled
        | Après claim : sending
        |
        */

        $emailStatus = (string) (
            $email['email_status']
            ?? $email['status']
            ?? ''
        );

        if (
            !in_array(
                $emailStatus,
                [
                    'scheduled',
                    'sending',
                ],
                true
            )
        ) {
            $reasons[] =
                'Statut de l’e-mail incompatible avec un envoi.';
        }

        /*
        |--------------------------------------------------------------------------
        | Tentatives
        |--------------------------------------------------------------------------
        */

        $maxAttempts = (int) (
            $sendingConfig['max_attempts']
            ?? 3
        );

        $sendAttempts = (int) (
            $email['send_attempts']
            ?? 0
        );

        if ($sendAttempts >= $maxAttempts) {
            $reasons[] =
                'Nombre maximal de tentatives atteint.';
        }

        /*
        |--------------------------------------------------------------------------
        | Destinataire
        |--------------------------------------------------------------------------
        */

        $recipientEmail = strtolower(
            trim(
                (string) (
                    $email['recipient_email']
                    ?? ''
                )
            )
        );

        if (
            filter_var(
                $recipientEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $reasons[] =
                'Adresse destinataire invalide.';
        }

        $effectiveRecipient =
            $recipientEmail;

        /*
        |--------------------------------------------------------------------------
        | Redirection locale
        |--------------------------------------------------------------------------
        */

        $localRedirectEnabled = (bool) (
            $sendingConfig[
                'local_redirect_enabled'
            ]
            ?? false
        );

        if ($localRedirectEnabled) {
            $redirectEmail = strtolower(
                trim(
                    (string) (
                        $sendingConfig[
                            'local_redirect_email'
                        ]
                        ?? ''
                    )
                )
            );

            if (
                filter_var(
                    $redirectEmail,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                $reasons[] =
                    'Adresse locale de redirection absente ou invalide.';
            } else {
                $effectiveRecipient =
                    $redirectEmail;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Prospect
        |--------------------------------------------------------------------------
        */

        $prospectId = (int) (
            $email['prospect_id']
            ?? 0
        );

        if ($prospectId < 1) {
            $reasons[] =
                'Prospect invalide.';
        }

        $prospectStatus = (string) (
            $email['prospect_status']
            ?? ''
        );

        if ($prospectStatus !== 'follow_up') {
            $reasons[] =
                'Le prospect n’est pas en phase de relance.';
        }

        /*
        |--------------------------------------------------------------------------
        | STOP : réponse humaine
        |--------------------------------------------------------------------------
        |
        | Une réponse automatique ne bloque pas la relance.
        |
        */

        if (
            $prospectId > 0
            && $this->hasHumanReply(
                $prospectId
            )
        ) {
            $reasons[] =
                'Une réponse du prospect a été détectée.';
        }

        /*
        |--------------------------------------------------------------------------
        | STOP : désinscription
        |--------------------------------------------------------------------------
        */

        if (
            $recipientEmail !== ''
            && $this->isUnsubscribed(
                $recipientEmail
            )
        ) {
            $reasons[] =
                'Adresse désinscrite.';
        }

        /*
        |--------------------------------------------------------------------------
        | Premier contact obligatoire
        |--------------------------------------------------------------------------
        */

        if (
            $prospectId > 0
            && !$this->initialEmailWasSent(
                $prospectId
            )
        ) {
            $reasons[] =
                'Le premier e-mail n’a pas été envoyé.';
        }

        /*
        |--------------------------------------------------------------------------
        | Relance 2
        |--------------------------------------------------------------------------
        */

        if (
            $emailType === 'follow_up_2'
            && $prospectId > 0
            && !$this->firstFollowUpWasSent(
                $prospectId
            )
        ) {
            $reasons[] =
                'La première relance n’a pas été envoyée.';
        }

        return [
            'allowed' =>
                $reasons === [],

            'reasons' =>
                $reasons,

            'recipient_email' =>
                $recipientEmail,

            'effective_recipient_email' =>
                $effectiveRecipient,

            'redirected' =>
                $effectiveRecipient
                !== $recipientEmail,

            'send_attempts' =>
                $sendAttempts,

            'max_attempts' =>
                $maxAttempts,
        ];
    }

    private function hasHumanReply(
        int $prospectId
    ): bool {
        $statement = $this->pdo->prepare(
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
        $statement = $this->pdo->prepare(
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

    private function initialEmailWasSent(
        int $prospectId
    ): bool {
        $statement = $this->pdo->prepare(
            "
            SELECT 1
            FROM prospect_emails
            WHERE prospect_id = :prospect_id
            AND email_type = 'initial'
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

    private function firstFollowUpWasSent(
        int $prospectId
    ): bool {
        $statement = $this->pdo->prepare(
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
}
