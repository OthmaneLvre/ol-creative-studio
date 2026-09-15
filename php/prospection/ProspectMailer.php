<?php

declare(strict_types=1);

final class ProspectMailer
{
    public function __construct(
        private readonly array $config
    ) {
    }

    public function prepare(
        array $email,
        array $guardResult
    ): array {
        if (
            !isset($guardResult['allowed'])
            || $guardResult['allowed'] !== true
        ) {
            throw new RuntimeException(
                'Le message ne peut pas Ãªtre prÃ©parÃ© : '
                . 'le SendGuard ne lâ€™a pas autorisÃ©.'
            );
        }

        $senderEmail = strtolower(
            trim(
                (string) (
                    $this->config[
                        'sending'
                    ]['sender_email']
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
            throw new RuntimeException(
                'Adresse expÃ©diteur invalide.'
            );
        }

        $senderName = trim(
            (string) (
                $this->config[
                    'sending'
                ]['sender_name']
                ?? 'OL Creative Studio'
            )
        );

        if ($senderName === '') {
            throw new RuntimeException(
                'Nom expÃ©diteur invalide.'
            );
        }

        $recipientEmail = strtolower(
            trim(
                (string) (
                    $guardResult[
                        'effective_recipient_email'
                    ]
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
            throw new RuntimeException(
                'Destinataire effectif invalide.'
            );
        }

        $originalRecipientEmail =
            strtolower(
                trim(
                    (string) (
                        $guardResult[
                            'recipient_email'
                        ]
                        ?? ''
                    )
                )
            );

        $subject = trim(
            (string) (
                $email['subject']
                ?? ''
            )
        );

        if ($subject === '') {
            throw new RuntimeException(
                'Objet du message absent.'
            );
        }

        if (mb_strlen($subject) > 255) {
            throw new RuntimeException(
                'Objet du message trop long.'
            );
        }

        $bodyText = trim(
            (string) (
                $email['body_text']
                ?? ''
            )
        );

        if ($bodyText === '') {
            throw new RuntimeException(
                'Corps texte du message absent.'
            );
        }

        $bodyHtml = trim(
            (string) (
                $email['body_html']
                ?? ''
            )
        );

        if ($bodyHtml === '') {
            $bodyHtml =
                $this->textToHtml(
                    $bodyText
                );
        }

        $bodyText =
            $this->appendComplianceText(
                $bodyText
            );

        $bodyHtml =
            $this->appendComplianceHtml(
                $bodyHtml
            );

        $messageId =
            $this->generateMessageId(
                $senderEmail
            );

        /*
        |--------------------------------------------------------------------------
        | Threading
        |--------------------------------------------------------------------------
        |
        | RÃ©ponse manuelle :
        |   In-Reply-To = Message-ID du message entrant
        |   References  = message source + message entrant
        |
        | Follow-up 1 :
        |   In-Reply-To = Message-ID initial
        |   References  = message initial
        |
        | Follow-up 2 :
        |   In-Reply-To = Message-ID follow-up 1
        |   References  = message initial + follow-up 1
        |
        */

        $emailType = trim(
            (string) (
                $email['email_type']
                ?? ''
            )
        );

        $inReplyTo = null;
        $references = [];

        if (
            $emailType === 'follow_up_1'
            || $emailType === 'follow_up_2'
        ) {
            $initialMessageId =
                $this->normalizeMessageId(
                    (string) (
                        $email[
                            'initial_message_id'
                        ]
                        ?? ''
                    )
                );

            $followUp1MessageId =
                $this->normalizeMessageId(
                    (string) (
                        $email[
                            'follow_up_1_message_id'
                        ]
                        ?? ''
                    )
                );

            if ($emailType === 'follow_up_1') {
                if ($initialMessageId === null) {
                    throw new RuntimeException(
                        'Message-ID initial absent ou invalide '
                        . 'pour la relance 1.'
                    );
                }

                $inReplyTo =
                    $initialMessageId;

                $references[] =
                    $initialMessageId;
            } else {
                if ($initialMessageId === null) {
                    throw new RuntimeException(
                        'Message-ID initial absent ou invalide '
                        . 'pour la relance 2.'
                    );
                }

                if ($followUp1MessageId === null) {
                    throw new RuntimeException(
                        'Message-ID de la relance 1 absent '
                        . 'ou invalide pour la relance 2.'
                    );
                }

                $inReplyTo =
                    $followUp1MessageId;

                $references[] =
                    $initialMessageId;

                if (
                    !in_array(
                        $followUp1MessageId,
                        $references,
                        true
                    )
                ) {
                    $references[] =
                        $followUp1MessageId;
                }
            }
        } else {

            /*
            |--------------------------------------------------------------------------
            | Threading des rÃ©ponses
            |--------------------------------------------------------------------------
            */

            $inReplyTo =
                $this->normalizeMessageId(
                    (string) (
                        $email[
                            'reply_message_id'
                        ]
                        ?? ''
                    )
                );

            $sourceMessageId =
                $this->normalizeMessageId(
                    (string) (
                        $email[
                            'source_message_id'
                        ]
                        ?? ''
                    )
                );

            $replyInReplyTo =
                $this->normalizeMessageId(
                    (string) (
                        $email[
                            'reply_in_reply_to'
                        ]
                        ?? ''
                    )
                );

            if ($sourceMessageId !== null) {
                $references[] =
                    $sourceMessageId;
            } elseif ($replyInReplyTo !== null) {
                /*
                * Fallback si le Message-ID de l'e-mail source
                * n'est plus disponible en base.
                */
                $references[] =
                    $replyInReplyTo;
            }

            if (
                $inReplyTo !== null
                && !in_array(
                    $inReplyTo,
                    $references,
                    true
                )
            ) {
                $references[] =
                    $inReplyTo;
            }
        }

        return [
            'email_id' =>
                isset($email['email_id'])
                    ? (int) $email['email_id']
                    : null,

            'prospect_id' =>
                isset($email['prospect_id'])
                    ? (int) $email['prospect_id']
                    : null,

            'from_email' =>
                $senderEmail,

            'from_name' =>
                $senderName,

            'to_email' =>
                $recipientEmail,

            'original_recipient_email' =>
                $originalRecipientEmail,

            'redirected' =>
                (bool) (
                    $guardResult[
                        'redirected'
                    ]
                    ?? false
                ),

            'subject' =>
                $subject,

            'body_html' =>
                $bodyHtml,

            'body_text' =>
                $bodyText,

            'message_id' =>
                $messageId,

            'in_reply_to' =>
                $inReplyTo,

            'references' =>
                $references,

            'prepared_at' =>
                (new DateTimeImmutable())
                    ->format(DATE_ATOM),

            'transport' =>
                'preview',
        ];
    }

    private function appendComplianceText(
        string $bodyText
    ): string {
        $footer = implode(
            PHP_EOL,
            [
                '---',
                'OL Creative Studio',
                'DÃ©veloppeur web & graphiste freelance',
                '',
                'Vous recevez ce message dans un contexte professionnel.',
                'Si vous ne souhaitez plus recevoir de messages de ma part,',
                'rÃ©pondez simplement Â« STOP Â» Ã  cet e-mail.',
            ]
        );

        return rtrim($bodyText)
            . PHP_EOL
            . PHP_EOL
            . $footer;
    }

    private function appendComplianceHtml(
        string $bodyHtml
    ): string {
        $footer = '
            <div style="
                margin-top:32px;
                padding-top:16px;
                border-top:1px solid #e5e7eb;
                font-size:12px;
                line-height:1.6;
                color:#6b7280;
            ">
                <strong>OL Creative Studio</strong><br>
                DÃ©veloppeur web &amp; graphiste freelance<br><br>

                Vous recevez ce message dans un contexte professionnel.<br>

                Si vous ne souhaitez plus recevoir de messages de ma part,
                rÃ©pondez simplement <strong>STOP</strong> Ã  cet e-mail.
            </div>
        ';

        return rtrim($bodyHtml)
            . $footer;
    }

    private function textToHtml(
        string $bodyText
    ): string {
        return '<div>'
            . nl2br(
                htmlspecialchars(
                    $bodyText,
                    ENT_QUOTES
                    | ENT_SUBSTITUTE,
                    'UTF-8'
                )
            )
            . '</div>';
    }

    private function normalizeMessageId(
        string $messageId
    ): ?string {
        $messageId =
            trim($messageId);

        if ($messageId === '') {
            return null;
        }

        if (
            preg_match(
                '/^<[^<>\s@]+@[^<>\s@]+>$/',
                $messageId
            ) !== 1
        ) {
            return null;
        }

        return $messageId;
    }

    private function generateMessageId(
        string $senderEmail
    ): string {
        $domain = substr(
            strrchr(
                $senderEmail,
                '@'
            ) ?: '',
            1
        );

        if ($domain === '') {
            throw new RuntimeException(
                'Impossible de dÃ©terminer '
                . 'le domaine du Message-ID.'
            );
        }

        return sprintf(
            '<prospection-%s-%s@%s>',
            gmdate('YmdHis'),
            bin2hex(random_bytes(12)),
            $domain
        );
    }
}
