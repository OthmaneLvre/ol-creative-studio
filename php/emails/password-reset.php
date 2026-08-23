<?php

declare(strict_types=1);

require_once __DIR__ . '/template.php';


function renderPasswordResetEmail(
    string $username,
    string $resetUrl
): string {

    $safeUsername =
        htmlspecialchars(
            $username,
            ENT_QUOTES,
            'UTF-8'
        );

    $content = <<<HTML
        <p style="margin: 0 0 20px;">
            Bonjour {$safeUsername},
        </p>

        <p style="margin: 0 0 24px;">
            Une demande de réinitialisation du mot de passe
            de votre espace administrateur
            <strong>OL Creative Studio</strong>
            a été effectuée.
        </p>

        <p style="margin: 0 0 24px;">
            Cliquez sur le bouton ci-dessous pour définir
            un nouveau mot de passe.
        </p>

        <div
            style="
                margin-top: 28px;
                padding: 20px;

                background: #F5F7F8;
                border-radius: 14px;
            "
        >
            <p
                style="
                    margin: 0;

                    color: #727B86;
                    font-size: 13px;
                    line-height: 1.6;
                "
            >
                Ce lien de réinitialisation est valable pendant
                <strong style="color: #07111F;">
                    30 minutes
                </strong>.
            </p>
        </div>

        <p
            style="
                margin: 28px 0 0;

                color: #727B86;
                font-size: 13px;
                line-height: 1.6;
            "
        >
            Si vous n’êtes pas à l’origine de cette demande,
            vous pouvez simplement ignorer cet email.
            Votre mot de passe actuel restera inchangé.
        </p>
    HTML;


    return renderEmailTemplate(
        'Sécurité',
        'Réinitialiser votre mot de passe.',
        $content,
        'Définir un nouveau mot de passe',
        $resetUrl
    );
}