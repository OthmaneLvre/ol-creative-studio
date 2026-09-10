<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../../vendor/autoload.php';

final class ProspectMailTransport
{
    public function __construct(
        private readonly array $mailConfig
    ) {
    }

    /**
     * @return array{
     *     success: bool,
     *     message_id: ?string,
     *     error: ?string
     * }
     */
    public function send(array $message): array
    {
        $this->validateMessage($message);

        $config = $this->mailConfig;

        if (
            empty($config['host'])
            || empty($config['username'])
            || empty($config['password'])
        ) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Configuration SMTP incomplète.',
            ];
        }

        $mail = new PHPMailer(true);

        try {
            /*
            |--------------------------------------------------------------------------
            | SMTP
            |--------------------------------------------------------------------------
            */

            $mail->isSMTP();

            $mail->Host =
                (string) $config['host'];

            $mail->SMTPAuth = true;

            $mail->Username =
                (string) $config['username'];

            $mail->Password =
                (string) $config['password'];

            $mail->Port =
                (int) $config['port'];

            $mail->SMTPSecure =
                ($config['encryption'] ?? 'ssl') === 'tls'
                    ? PHPMailer::ENCRYPTION_STARTTLS
                    : PHPMailer::ENCRYPTION_SMTPS;

            $mail->CharSet = 'UTF-8';

            /*
            |--------------------------------------------------------------------------
            | Expéditeur
            |--------------------------------------------------------------------------
            */

            $mail->setFrom(
                (string) $message['from_email'],
                (string) $message['from_name']
            );

            /*
            |--------------------------------------------------------------------------
            | Destinataire effectif
            |--------------------------------------------------------------------------
            |
            | En local, cette valeur vient déjà du SendGuard et correspond
            | donc à l'adresse de redirection.
            |
            */

            $mail->addAddress(
                (string) $message['to_email']
            );

            /*
            |--------------------------------------------------------------------------
            | Reply-To
            |--------------------------------------------------------------------------
            */

            $mail->addReplyTo(
                (string) $message['from_email'],
                (string) $message['from_name']
            );

            /*
            |--------------------------------------------------------------------------
            | Message-ID
            |--------------------------------------------------------------------------
            */

            $mail->MessageID =
                (string) $message['message_id'];


            /*
            |--------------------------------------------------------------------------
            | Threading
            |--------------------------------------------------------------------------
            */

            $inReplyTo =
                trim(
                    (string) (
                        $message[
                            'in_reply_to'
                        ]
                        ?? ''
                    )
                );

            if ($inReplyTo !== '') {
                $mail->addCustomHeader(
                    'In-Reply-To',
                    $inReplyTo
                );
            }

            $references =
                $message['references']
                ?? [];

            if (
                is_array($references)
                && $references !== []
            ) {
                $mail->addCustomHeader(
                    'References',
                    implode(
                        ' ',
                        $references
                    )
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Contenu
            |--------------------------------------------------------------------------
            */

            $mail->isHTML(true);

            $mail->Subject =
                (string) $message['subject'];

            $mail->Body =
                (string) $message['body_html'];

            $mail->AltBody =
                (string) $message['body_text'];

            /*
            |--------------------------------------------------------------------------
            | Désinscription
            |--------------------------------------------------------------------------
            |
            | Pour la V1, l'utilisateur peut répondre STOP.
            |
            */

            $unsubscribeEmail =
                (string) $message['from_email'];

            $mail->addCustomHeader(
                'List-Unsubscribe',
                '<mailto:'
                . $unsubscribeEmail
                . '?subject=STOP>'
            );

            /*
            |--------------------------------------------------------------------------
            | Envoi
            |--------------------------------------------------------------------------
            */

            $success = $mail->send();

            if (!$success) {
                return [
                    'success' => false,
                    'message_id' => null,
                    'error' =>
                        $mail->ErrorInfo !== ''
                            ? $mail->ErrorInfo
                            : 'Échec SMTP inconnu.',
                ];
            }

            return [
                'success' => true,
                'message_id' =>
                    $mail->getLastMessageID()
                    ?: (string) $message['message_id'],
                'error' => null,
            ];
        } catch (Exception $exception) {
            $error =
                $mail->ErrorInfo !== ''
                    ? $mail->ErrorInfo
                    : $exception->getMessage();

            error_log(
                '[PROSPECTION MAIL] '
                . $error
            );

            return [
                'success' => false,
                'message_id' => null,
                'error' => $error,
            ];
        }
    }

    private function validateMessage(
        array $message
    ): void {
        $required = [
            'from_email',
            'from_name',
            'to_email',
            'subject',
            'body_html',
            'body_text',
            'message_id',
        ];

        foreach ($required as $field) {
            if (
                !isset($message[$field])
                || trim((string) $message[$field]) === ''
            ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Champ du message absent : %s.',
                        $field
                    )
                );
            }
        }

        if (
            filter_var(
                $message['from_email'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Adresse expéditeur invalide.'
            );
        }

        if (
            filter_var(
                $message['to_email'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new InvalidArgumentException(
                'Adresse destinataire invalide.'
            );
        }

        if (
            preg_match(
                '/^<[^<>\s@]+@[^<>\s@]+>$/',
                (string) $message['message_id']
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'Message-ID invalide.'
            );
        }

        $inReplyTo =
            trim(
                (string) (
                    $message[
                        'in_reply_to'
                    ]
                    ?? ''
                )
            );

        if (
            $inReplyTo !== ''
            && preg_match(
                '/^<[^<>\s@]+@[^<>\s@]+>$/',
                $inReplyTo
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'In-Reply-To invalide.'
            );
        }

        $references =
            $message['references']
            ?? [];

        if (!is_array($references)) {
            throw new InvalidArgumentException(
                'References invalide.'
            );
        }

        foreach ($references as $reference) {
            if (
                !is_string($reference)
                || preg_match(
                    '/^<[^<>\s@]+@[^<>\s@]+>$/',
                    trim($reference)
                ) !== 1
            ) {
                throw new InvalidArgumentException(
                    'Message-ID invalide dans References.'
                );
            }
        }
    }
}
