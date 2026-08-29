<?php

declare(strict_types=1);

require_once __DIR__ . '/template.php';


function renderContactConfirmationEmail(
    string $prenom,
    string $objet,
    string $budget,
    string $message
): string {

    $safePrenom = htmlspecialchars(
        $prenom,
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


    $content = <<<HTML
        <p style="margin: 0 0 20px;">
            Bonjour {$safePrenom},
        </p>

        <p style="margin: 0 0 26px;">
            Merci pour votre message.
            Votre demande a bien été reçue par
            OL Creative Studio.
        </p>

        <div
            style="
                padding: 22px;

                background: #F5F7F8;
                border-radius: 14px;
            "
        >

            <p style="margin: 0 0 10px;">
                <strong>Projet :</strong>
                {$safeObjet}
            </p>

            <p style="margin: 0 0 18px;">
                <strong>Budget :</strong>
                {$safeBudget}
            </p>

            <div
                style="
                    padding-top: 18px;
                    border-top: 1px solid rgba(13, 27, 42, 0.1);
                "
            >
                {$safeMessage}
            </div>

        </div>

        <p style="margin: 28px 0 0;">
            Je prends le temps d’étudier votre demande
            et je reviens vers vous généralement
            sous <strong>24 heures ouvrées</strong>.
        </p>

        <p style="margin: 24px 0 0;">
            À bientôt,<br>
            <strong>Othmane</strong><br>
            OL Creative Studio
        </p>
    HTML;


    return renderEmailTemplate(
        'Demande reçue',
        'Merci pour votre message.',
        $content,
        'Découvrir OL Creative Studio',
        'https://olcreativestudio.fr'
    );
}
