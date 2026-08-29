<?php

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';

require_once __DIR__ . '/emails/template.php';


/*
|--------------------------------------------------------------------------
| Configuration SMTP
|--------------------------------------------------------------------------
*/

function getMailConfig(): array
{
    static $config = null;

    if ($config === null) {
        $config = require __DIR__ . '/../config/mail.php';
    }

    return $config;
}


/*
|--------------------------------------------------------------------------
| Envoi d'un email
|--------------------------------------------------------------------------
*/

function sendMail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody,
    ?string $replyToEmail = null,
    ?string $replyToName = null
): bool {

    $config = getMailConfig();

    if (
        empty($config['host']) ||
        empty($config['username']) ||
        empty($config['password'])
    ) {
        error_log(
            '[MAIL] SMTP configuration is incomplete.'
        );

        return false;
    }

    $mail = new PHPMailer(true);

    try {

        /*
        |--------------------------------------------------------------------------
        | SMTP
        |--------------------------------------------------------------------------
        */

        $mail->isSMTP();

        $mail->Host = $config['host'];
        $mail->SMTPAuth = true;

        $mail->Username = $config['username'];
        $mail->Password = $config['password'];

        $mail->Port = (int) $config['port'];

        $mail->SMTPSecure =
            $config['encryption'] === 'tls'
                ? PHPMailer::ENCRYPTION_STARTTLS
                : PHPMailer::ENCRYPTION_SMTPS;

        $mail->CharSet = 'UTF-8';


        /*
        |--------------------------------------------------------------------------
        | Expéditeur
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );


        /*
        |--------------------------------------------------------------------------
        | Destinataire
        |--------------------------------------------------------------------------
        */

        $mail->addAddress(
            $toEmail,
            $toName
        );


        /*
        |--------------------------------------------------------------------------
        | Reply-To
        |--------------------------------------------------------------------------
        */

        if ($replyToEmail !== null) {

            $mail->addReplyTo(
                $replyToEmail,
                $replyToName ?? $replyToEmail
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Contenu
        |--------------------------------------------------------------------------
        */

        $mail->isHTML(true);

        $mail->Subject = $subject;
        $mail->Body = $htmlBody;
        $mail->AltBody = $textBody;


        /*
        |--------------------------------------------------------------------------
        | Envoi
        |--------------------------------------------------------------------------
        */

        return $mail->send();

    } catch (Exception $exception) {

        error_log(
            '[MAIL] PHPMailer error: ' .
            $mail->ErrorInfo
        );

        return false;
    }
}