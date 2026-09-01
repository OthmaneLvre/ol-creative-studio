<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

$adminPageTitle =
    'Réconciliation des e-mails';

$adminActivePage =
    'prospection';

$repository =
    new ProspectRepository(
        $pdo
    );

$config =
    require __DIR__
        . '/../../config/prospection.php';

$scheduler =
    new ProspectScheduler(
        $config
    );

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function reconciliationDate(
    mixed $value
): string {
    if (
        $value === null
        || $value === ''
    ) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable(
                (string) $value
            )
        )->format(
            'd/m/Y H:i'
        );
    } catch (Throwable) {
        return '—';
    }
}

function reconciliationValue(
    mixed $value
): string {
    $text =
        trim(
            (string) $value
        );

    return $text !== ''
        ? htmlspecialchars(
            $text,
            ENT_QUOTES,
            'UTF-8'
        )
        : '—';
}

/*
|--------------------------------------------------------------------------
| Actions
|--------------------------------------------------------------------------
*/

$actionError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf =
        (string) (
            $_POST['csrf']
            ?? ''
        );

    $action =
        (string) (
            $_POST['action']
            ?? ''
        );

    $emailId =
        filter_input(
            INPUT_POST,
            'email_id',
            FILTER_VALIDATE_INT
        );

    if (
        $csrf === ''
        || !hash_equals(
            (string) $_SESSION[
                'csrf_token'
            ],
            $csrf
        )
    ) {
        http_response_code(403);

        $actionError =
            'Jeton de sécurité invalide. Rechargez la page.';

    } elseif (
        !$emailId
        || $emailId < 1
    ) {
        $actionError =
            'E-mail invalide.';

    } else {
        try {
            /*
            |--------------------------------------------------------------------------
            | Recharge depuis la BDD
            |--------------------------------------------------------------------------
            |
            | L'identifiant de l'e-mail vient du formulaire,
            | mais toutes les données sensibles utilisées pour
            | la résolution sont relues depuis la base.
            |
            */

            $pending =
                $repository
                    ->findInitialEmailsRequiringReconciliation();

            $target = null;

            foreach ($pending as $item) {
                if (
                    (int) $item['email_id']
                    === (int) $emailId
                ) {
                    $target = $item;
                    break;
                }
            }

            if ($target === null) {
                throw new RuntimeException(
                    'Cet e-mail ne nécessite plus de réconciliation.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Confirmer comme envoyé
            |--------------------------------------------------------------------------
            */

            if ($action === 'confirm_sent') {
                $sendingStartedAt =
                    $target[
                        'sending_started_at'
                    ]
                    ?? null;

                if (
                    $sendingStartedAt === null
                    || trim(
                        (string) $sendingStartedAt
                    ) === ''
                ) {
                    throw new RuntimeException(
                        'Impossible de retrouver la date de tentative SMTP.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | L'horodatage vient exclusivement de la BDD.
                |--------------------------------------------------------------------------
                */

                $confirmedAt =
                    new DateTimeImmutable(
                        (string) $sendingStartedAt
                    );

                $repository
                    ->confirmReconciliationAsSent(
                        (int) $emailId,
                        $confirmedAt
                    );

            /*
            |--------------------------------------------------------------------------
            | Reprogrammer
            |--------------------------------------------------------------------------
            */

            } elseif (
                $action === 'reschedule'
            ) {
                $timezone =
                    new DateTimeZone(
                        date_default_timezone_get()
                    );

                $repository
                    ->rescheduleReconciledInitialEmailWithDailyLimit(
                        (int) $emailId,
                        $scheduler,
                        $timezone
                    );

            } else {
                throw new InvalidArgumentException(
                    'Action inconnue.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Rotation CSRF
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );

            header(
                'Location: /admin/prospection/reconciliation.php?updated=1'
            );

            exit;

        } catch (Throwable $exception) {
            $actionError =
                $exception->getMessage();
        }
    }
}

/*
|--------------------------------------------------------------------------
| File de réconciliation
|--------------------------------------------------------------------------
*/

$emails = [];

try {
    $emails =
        $repository
            ->findInitialEmailsRequiringReconciliation();
} catch (Throwable $exception) {
    $emails = [];

    if ($actionError === null) {
        $actionError =
            $exception->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| Layout
|--------------------------------------------------------------------------
*/

?>

<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>
        Réconciliation des e-mails — OL Creative Studio
    </title>

    <link
        rel="icon"
        href="/favicon.ico"
    >

    <link
        rel="stylesheet"
        href="/admin/assets/css/admin.css"
    >

</head>

<body class="admin-body">

<div class="admin-layout">

    <?php
    include_once dirname(__DIR__)
        . '/partials/sidebar.php';
    ?>

    <main class="admin-main">

        <?php
        include_once dirname(__DIR__)
            . '/partials/header.php';
        ?>

        <div class="admin-content">

            <div class="admin-page">

            <div class="admin-page__header">

                <div>

                    <span class="admin-eyebrow">
                        Prospection
                    </span>

                    <h1>
                        Réconciliation des e-mails
                    </h1>

                    <p>
                        Vérifiez manuellement les envois dont
                        l’état SMTP est incertain avant toute
                        nouvelle tentative.
                    </p>

                </div>

            </div>

            <?php if (
                isset($_GET['updated'])
                && $_GET['updated'] === '1'
            ): ?>

                <div class="admin-alert admin-alert--success">
                    Action de réconciliation enregistrée.
                </div>

            <?php endif; ?>

            <?php if ($actionError !== null): ?>

                <div class="admin-alert admin-alert--error">
                    <?= htmlspecialchars(
                        $actionError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>

            <section class="admin-panel">

                <div class="admin-panel__header">

                    <div>

                        <span class="admin-eyebrow">
                            Sécurité SMTP
                        </span>

                        <h2>
                            Envois à vérifier
                        </h2>

                    </div>

                    <span class="admin-badge">
                        <?= count($emails) ?>
                    </span>

                </div>

                <?php if (empty($emails)): ?>

                    <div class="admin-empty-state">

                        <p>
                            Aucun e-mail ne nécessite
                            actuellement de réconciliation.
                        </p>

                        <span class="admin-stat-card__meta">
                            Les envois ambigus détectés par
                            l’orchestrateur apparaîtront ici.
                        </span>

                    </div>

                <?php else: ?>

                    <div class="admin-project-list">

                        <?php foreach ($emails as $email): ?>

                            <article class="prospect-row">

                                <div class="prospect-row__main">

                                    <div class="prospect-row__identity">

                                        <span class="prospect-row__location">
                                            <?= reconciliationValue(
                                                $email['city']
                                                ?? null
                                            ) ?>

                                            <?php if (
                                                !empty(
                                                    $email[
                                                        'postal_code'
                                                    ]
                                                )
                                            ): ?>
                                                ·
                                                <?= reconciliationValue(
                                                    $email[
                                                        'postal_code'
                                                    ]
                                                ) ?>
                                            <?php endif; ?>
                                        </span>

                                        <h3>
                                            <?= reconciliationValue(
                                                $email[
                                                    'company_name'
                                                ]
                                                ?? null
                                            ) ?>
                                        </h3>

                                        <?php if (
                                            !empty(
                                                $email[
                                                    'activity'
                                                ]
                                            )
                                        ): ?>

                                            <span class="prospect-row__activity">
                                                <?= reconciliationValue(
                                                    $email[
                                                        'activity'
                                                    ]
                                                ) ?>
                                            </span>

                                        <?php endif; ?>

                                    </div>

                                    <span class="admin-badge">
                                        Réconciliation requise
                                    </span>

                                </div>

                                <div class="prospect-reconciliation__details">

                                    <p>
                                        <strong>Destinataire :</strong>
                                        <?= reconciliationValue(
                                            $email[
                                                'recipient_email'
                                            ]
                                            ?? null
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Sujet :</strong>
                                        <?= reconciliationValue(
                                            $email[
                                                'subject'
                                            ]
                                            ?? null
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Message-ID :</strong>
                                        <code>
                                            <?= reconciliationValue(
                                                $email[
                                                    'message_id'
                                                ]
                                                ?? null
                                            ) ?>
                                        </code>
                                    </p>

                                    <p>
                                        <strong>Tentative SMTP :</strong>
                                        <?= reconciliationDate(
                                            $email[
                                                'sending_started_at'
                                            ]
                                            ?? null
                                        ) ?>
                                    </p>

                                    <p>
                                        <strong>Tentatives :</strong>
                                        <?= (int) (
                                            $email[
                                                'send_attempts'
                                            ]
                                            ?? 0
                                        ) ?>
                                    </p>

                                    <?php if (
                                        !empty(
                                            $email[
                                                'error_message'
                                            ]
                                        )
                                    ): ?>

                                        <p>
                                            <strong>Diagnostic :</strong>
                                            <?= reconciliationValue(
                                                $email[
                                                    'error_message'
                                                ]
                                            ) ?>
                                        </p>

                                    <?php endif; ?>

                                </div>

                                <div class="prospect-row__footer">

                                    <div class="prospect-row__actions">

                                        <a
                                            href="/admin/prospection/prospect.php?id=<?= (int) $email['prospect_id'] ?>"
                                            class="admin-text-link"
                                        >
                                            Ouvrir le prospect
                                            <span aria-hidden="true">
                                                →
                                            </span>
                                        </a>

                                    </div>

                                </div>

                                <div class="prospect-detail__actions">

                                    <form method="post">

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= htmlspecialchars(
                                                $_SESSION[
                                                    'csrf_token'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="email_id"
                                            value="<?= (int) $email['email_id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="confirm_sent"
                                        >

                                        <button
                                            type="submit"
                                            class="admin-button admin-button--primary"
                                        >
                                            Confirmer comme envoyé
                                        </button>

                                    </form>

                                    <form method="post">

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= htmlspecialchars(
                                                $_SESSION[
                                                    'csrf_token'
                                                ],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="email_id"
                                            value="<?= (int) $email['email_id'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="reschedule"
                                        >

                                        <button
                                            type="submit"
                                            class="admin-button"
                                        >
                                            Reprogrammer
                                        </button>

                                    </form>

                                </div>

                                <p class="prospect-detail__action-note">
                                    Confirmez uniquement après
                                    vérification de la boîte d’envoi
                                    ou des logs SMTP.
                                </p>

                            </article>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

            </div>

        </div>

    </main>

</div>

<?php
include_once dirname(__DIR__)
    . '/partials/footer.php';
?>

</body>

</html>
