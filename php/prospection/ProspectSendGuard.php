<?php

declare(strict_types=1);

final class ProspectSendGuard
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config
    ) {
    }

    public function evaluate(array $email): array
    {
        $reasons = [];

        $sendingConfig = $this->config['sending'] ?? [];
        $complianceConfig = $this->config['compliance'] ?? [];

        $enabled = (bool) (
            $sendingConfig['enabled']
            ?? false
        );

        if (!$enabled) {
            $reasons[] = 'Envoi désactivé par configuration.';
        }

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

        $effectiveRecipient = $recipientEmail;

        $localRedirectEnabled = (bool) (
            $sendingConfig['local_redirect_enabled']
            ?? false
        );

        if ($localRedirectEnabled) {
            $redirectEmail = strtolower(
                trim(
                    (string) (
                        $sendingConfig['local_redirect_email']
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
                $effectiveRecipient = $redirectEmail;
            }
        }

        $publicEmailsOnly = (bool) (
            $complianceConfig['public_emails_only']
            ?? true
        );

        if ($publicEmailsOnly) {
            $sourceUrl = trim(
                (string) (
                    $email['email_source_url']
                    ?? ''
                )
            );

            if ($sourceUrl === '') {
                $reasons[] =
                    'Source publique de l’e-mail absente.';
            }
        }

        $checkUnsubscribeList = (bool) (
            $complianceConfig[
                'check_unsubscribe_list'
            ]
            ?? true
        );

        if (
            $checkUnsubscribeList
            && $recipientEmail !== ''
            && $this->isUnsubscribed(
                $recipientEmail
            )
        ) {
            $reasons[] =
                'Adresse désinscrite.';
        }

        $prospectStatus = (string) (
            $email['prospect_status']
            ?? ''
        );

        if ($prospectStatus !== 'scheduled') {
            $reasons[] =
                'Le prospect n’est pas programmé.';
        }

        return [
            'allowed' => $reasons === [],
            'reasons' => $reasons,

            'recipient_email' =>
                $recipientEmail,

            'effective_recipient_email' =>
                $effectiveRecipient,

            'redirected' =>
                $effectiveRecipient !== $recipientEmail,

            'send_attempts' =>
                $sendAttempts,

            'max_attempts' =>
                $maxAttempts,
        ];
    }

    private function isUnsubscribed(
        string $email
    ): bool {
        $statement = $this->pdo->prepare(
            '
            SELECT 1
            FROM prospect_unsubscribes
            WHERE LOWER(email) = LOWER(:email)
            LIMIT 1
            '
        );

        $statement->execute([
            'email' => $email,
        ]);

        return $statement->fetchColumn()
            !== false;
    }
}
