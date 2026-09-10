<?php

declare(strict_types=1);

final class ProspectReplySendGuard
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly array $config
    ) {
    }

    public function evaluate(array $email): array
    {
        $reasons = [];

        $sendingConfig =
            $this->config['sending'] ?? [];

        $complianceConfig =
            $this->config['compliance'] ?? [];

        /*
        |--------------------------------------------------------------------------
        | Kill switch
        |--------------------------------------------------------------------------
        */

        $enabled = (bool) (
            $sendingConfig['enabled']
            ?? false
        );

        if (!$enabled) {
            $reasons[] =
                'Envoi desactive par configuration.';
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

        if ($maxAttempts < 1) {
            $reasons[] =
                'Configuration du nombre de tentatives invalide.';
        } elseif ($sendAttempts >= $maxAttempts) {
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

        /*
        |--------------------------------------------------------------------------
        | Le destinataire doit etre l'auteur de la reponse recue
        |--------------------------------------------------------------------------
        |
        | On ne reprend pas ici la contrainte "public email only".
        |
        | Cette adresse nous a directement contacte en repondant au message.
        |
        */

        $replySenderEmail = strtolower(
            trim(
                (string) (
                    $email['reply_sender_email']
                    ?? ''
                )
            )
        );

        if (
            filter_var(
                $replySenderEmail,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            $reasons[] =
                'Adresse de la reponse source invalide.';
        } elseif (
            $recipientEmail !== ''
            && $recipientEmail !== $replySenderEmail
        ) {
            $reasons[] =
                'Le destinataire ne correspond pas a la reponse source.';
        }

        /*
        |--------------------------------------------------------------------------
        | Redirection locale
        |--------------------------------------------------------------------------
        */

        $effectiveRecipient =
            $recipientEmail;

        $localRedirectEnabled = (bool) (
            $sendingConfig['local_redirect_enabled']
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
        | Desinscription
        |--------------------------------------------------------------------------
        */

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
                'Adresse desinscrite.';
        }

        /*
        |--------------------------------------------------------------------------
        | Type d'e-mail
        |--------------------------------------------------------------------------
        */

        $emailType = trim(
            (string) (
                $email['email_type']
                ?? ''
            )
        );

        if ($emailType !== 'manual') {
            $reasons[] =
                'Le message n est pas une reponse manuelle.';
        }

        /*
        |--------------------------------------------------------------------------
        | Reponse source
        |--------------------------------------------------------------------------
        */

        $sourceReplyId = (int) (
            $email['source_reply_id']
            ?? 0
        );

        if ($sourceReplyId <= 0) {
            $reasons[] =
                'Reponse source absente.';
        }

        /*
        |--------------------------------------------------------------------------
        | Classification
        |--------------------------------------------------------------------------
        */

        $replyClassification = trim(
            (string) (
                $email['reply_classification']
                ?? ''
            )
        );

        if (
            !in_array(
                $replyClassification,
                [
                    'positive',
                    'question',
                ],
                true
            )
        ) {
            $reasons[] =
                'Classification de reponse non autorisee pour envoi.';
        }

        /*
        |--------------------------------------------------------------------------
        | Etat de l'e-mail
        |--------------------------------------------------------------------------
        |
        | Le guard est utilise :
        | - avant claim : scheduled
        | - apres claim : sending
        |
        */

        $emailStatus = trim(
            (string) (
                $email['email_status']
                ?? $email['status']
                ?? ''
            )
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
                'Etat de l e-mail incompatible avec un envoi.';
        }

        /*
        |--------------------------------------------------------------------------
        | Etat du prospect
        |--------------------------------------------------------------------------
        |
        | Une reponse commerciale validee doit appartenir a une conversation
        | toujours en etat "replied".
        |
        | Si le prospect change entre temps (unsubscribed, converted, etc.),
        | l'envoi est bloque et devra etre revu manuellement.
        |
        */

        $prospectStatus = trim(
            (string) (
                $email['prospect_status']
                ?? ''
            )
        );

        if ($prospectStatus !== 'replied') {
            $reasons[] =
                'Le prospect n est plus dans l etat replied.';
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

            'source_reply_id' =>
                $sourceReplyId,
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
