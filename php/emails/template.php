<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Template email OL Creative Studio
|--------------------------------------------------------------------------
*/

function renderEmailTemplate(
    string $eyebrow,
    string $title,
    string $contentHtml,
    ?string $buttonLabel = null,
    ?string $buttonUrl = null
): string {

    $safeEyebrow =
        htmlspecialchars(
            $eyebrow,
            ENT_QUOTES,
            'UTF-8'
        );

    $safeTitle =
        htmlspecialchars(
            $title,
            ENT_QUOTES,
            'UTF-8'
        );

    $buttonHtml = '';

    if (
        $buttonLabel !== null &&
        $buttonUrl !== null
    ) {

        $safeButtonLabel =
            htmlspecialchars(
                $buttonLabel,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeButtonUrl =
            htmlspecialchars(
                $buttonUrl,
                ENT_QUOTES,
                'UTF-8'
            );

        $buttonHtml = <<<HTML
            <tr>
                <td style="
                    padding-top: 32px;
                ">
                    <a
                        href="{$safeButtonUrl}"
                        style="
                            display: inline-block;
                            padding: 14px 22px;

                            background: #1B9AAA;
                            border-radius: 999px;

                            color: #07111F;
                            text-decoration: none;

                            font-family: Arial, sans-serif;
                            font-size: 14px;
                            font-weight: 700;
                        "
                    >
                        {$safeButtonLabel}
                    </a>
                </td>
            </tr>
        HTML;
    }


    return <<<HTML
<!DOCTYPE html>

<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>{$safeTitle}</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;

        background: #F5F7F8;
    "
>

    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        border="0"
        style="
            width: 100%;
            background: #F5F7F8;
        "
    >

        <tr>

            <td
                align="center"
                style="
                    padding: 40px 16px;
                "
            >

                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    border="0"
                    style="
                        width: 100%;
                        max-width: 620px;

                        background: #FFFFFF;

                        border-radius: 20px;

                        overflow: hidden;
                    "
                >

                    <tr>
                        <td
                            style="
                                padding: 30px 34px;

                                background: #07111F;
                            "
                        >

                            <div
                                style="
                                    color: #FFFFFF;

                                    font-family: Georgia, serif;
                                    font-size: 28px;
                                    font-weight: 500;
                                    line-height: 1;
                                "
                            >
                                OL Creative Studio
                            </div>

                        </td>
                    </tr>


                    <tr>

                        <td
                            style="
                                padding: 42px 34px;
                            "
                        >

                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                            >

                                <tr>
                                    <td
                                        style="
                                            padding-bottom: 12px;

                                            color: #1B9AAA;

                                            font-family: Arial, sans-serif;
                                            font-size: 11px;
                                            font-weight: 700;
                                            letter-spacing: 2px;

                                            text-transform: uppercase;
                                        "
                                    >
                                        {$safeEyebrow}
                                    </td>
                                </tr>


                                <tr>
                                    <td
                                        style="
                                            padding-bottom: 24px;

                                            color: #07111F;

                                            font-family: Georgia, serif;
                                            font-size: 34px;
                                            line-height: 1.1;
                                        "
                                    >
                                        {$safeTitle}
                                    </td>
                                </tr>


                                <tr>
                                    <td
                                        style="
                                            color: #393E46;

                                            font-family: Arial, sans-serif;
                                            font-size: 15px;
                                            line-height: 1.7;
                                        "
                                    >
                                        {$contentHtml}
                                    </td>
                                </tr>

                                {$buttonHtml}

                            </table>

                        </td>

                    </tr>


                    <tr>
                        <td
                            style="
                                padding: 24px 34px;

                                background: #0D1B2A;

                                color: #B8BEC5;

                                font-family: Arial, sans-serif;
                                font-size: 12px;
                                line-height: 1.6;
                            "
                        >
                            OL Creative Studio<br>
                            Céret · Pyrénées-Orientales · France<br>
                            contact@olcreativestudio.fr
                        </td>
                    </tr>

                </table>

            </td>

        </tr>

    </table>

</body>

</html>
HTML;
}