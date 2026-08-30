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
                'Le message ne peut pas être préparé : '
                . 'le SendGuard ne l’a pas autorisé.'
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
                'Adresse expéditeur invalide.'
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
                'Nom expéditeur invalide.'
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
                'Développeur web & graphiste freelance',
                '',
                'Vous recevez ce message dans un contexte professionnel.',
                'Si vous ne souhaitez plus recevoir de messages de ma part,',
                'répondez simplement « STOP » à cet e-mail.',
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
                Développeur web &amp; graphiste freelance<br><br>

                Vous recevez ce message dans un contexte professionnel.<br>

                Si vous ne souhaitez plus recevoir de messages de ma part,
                répondez simplement <strong>STOP</strong> à cet e-mail.
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
                'Impossible de déterminer '
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
