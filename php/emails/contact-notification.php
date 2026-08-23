<?php

declare(strict_types=1);

require_once __DIR__ . '/template.php';


function renderContactNotificationEmail(
    string $prenom,
    string $nom,
    string $email,
    string $telephone,
    string $objet,
    string $budget,
    string $message,
    string $ip
): string {

    $safePrenom = htmlspecialchars(
        $prenom,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeNom = htmlspecialchars(
        $nom,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeEmail = htmlspecialchars(
        $email,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeTelephone = htmlspecialchars(
        $telephone,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeObjet = htmlspecialchars(
        $objet,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeBudget = htmlspecialchars(
        $budget,
        ENT_QUOTES,
        'UTF-8'
    );

    $safeMessage = nl2br(
        htmlspecialchars(
            $message,
            ENT_QUOTES,
            'UTF-8'
        )
    );

    $safeIp = htmlspecialchars(
        $ip,
        ENT_QUOTES,
        'UTF-8'
    );


    $content = <<<HTML
        <p style="margin: 0 0 24px;">
            Une nouvelle demande a été envoyée
            depuis le formulaire de contact.
        </p>

        <table
            role="presentation"
            width="100%"
            cellspacing="0"
            cellpadding="0"
            border="0"
            style="
                width: 100%;
                border-collapse: collapse;
            "
        >

            <tr>
                <td
                    style="
                        padding: 10px 0;
                        color: #727B86;
                        font-size: 13px;
                        width: 140px;
                    "
                >
                    Nom
                </td>

                <td
                    style="
                        padding: 10px 0;
                        color: #07111F;
                        font-weight: 700;
                    "
                >
                    {$safePrenom} {$safeNom}
                </td>
            </tr>

            <tr>
                <td
                    style="
                        padding: 10px 0;
                        color: #727B86;
                        font-size: 13px;
                    "
                >
                    Email
                </td>

                <td style="padding: 10px 0;">
                    <a
                        href="mailto:{$safeEmail}"
                        style="
                            color: #1B9AAA;
                            text-decoration: none;
                        "
                    >
                        {$safeEmail}
                    </a>
                </td>
            </tr>

            <tr>
                <td
                    style="
                        padding: 10px 0;
                        color: #727B86;
                        font-size: 13px;
                    "
                >
                    Téléphone
                </td>

                <td style="padding: 10px 0;">
                    {$safeTelephone}
                </td>
            </tr>

            <tr>
                <td
                    style="
                        padding: 10px 0;
                        color: #727B86;
                        font-size: 13px;
                    "
                >
                    Projet
                </td>

                <td style="padding: 10px 0;">
                    {$safeObjet}
                </td>
            </tr>

            <tr>
                <td
                    style="
                        padding: 10px 0;
                        color: #727B86;
                        font-size: 13px;
                    "
                >
                    Budget
                </td>

                <td style="padding: 10px 0;">
                    {$safeBudget}
                </td>
            </tr>

        </table>

        <div
            style="
                margin-top: 30px;
                padding: 22px;

                background: #F5F7F8;
                border-radius: 14px;
            "
        >
            <div
                style="
                    margin-bottom: 10px;

                    color: #727B86;

                    font-size: 11px;
                    font-weight: 700;
                    letter-spacing: 1.5px;

                    text-transform: uppercase;
                "
            >
                Message
            </div>

            <div
                style="
                    color: #393E46;
                    line-height: 1.7;
                "
            >
                {$safeMessage}
            </div>
        </div>

        <p
            style="
                margin: 26px 0 0;

                color: #727B86;
                font-size: 12px;
            "
        >
            Consentement RGPD : Oui<br>
            IP : {$safeIp}
        </p>
    HTML;


    return renderEmailTemplate(
        'Nouvelle demande',
        'Un nouveau projet vient d’arriver.',
        $content
    );
}
