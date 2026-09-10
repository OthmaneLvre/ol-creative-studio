<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/AiReplyGenerator.php';

require_once __DIR__
    . '/../../php/prospection/AiReplyDraftService.php';

require_once __DIR__
    . '/../../php/prospection/ReplyContentExtractor.php';

require_once __DIR__
    . '/../../php/prospection/ReplyDraftManager.php';

require_once __DIR__
    . '/../../php/prospection/ReplyScheduler.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Réponses';
$adminActivePage = 'prospection';

$allowedClassifications = [
    'all',
    'positive',
    'question',
    'negative',
    'unknown',
    'automatic',
    'unsubscribed',
];

$classification = (string) (
    $_GET['classification']
    ?? 'all'
);

if (
    !in_array(
        $classification,
        $allowedClassifications,
        true
    )
) {
    $classification = 'all';
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

    $replyId =
        filter_input(
            INPUT_POST,
            'reply_id',
            FILTER_VALIDATE_INT
        );

    $returnClassification =
        (string) (
            $_POST['classification']
            ?? 'all'
        );

    if (
        !in_array(
            $returnClassification,
            $allowedClassifications,
            true
        )
    ) {
        $returnClassification =
            'all';
    }

    if (
        $csrf === ''
        || !hash_equals(
            (string) $_SESSION['csrf_token'],
            $csrf
        )
    ) {
        http_response_code(403);

        $actionError =
            'Jeton de sécurité invalide. Rechargez la page.';

    } elseif (
        !$replyId
        || $replyId < 1
    ) {
        $actionError =
            'Réponse invalide.';

    } else {
        try {
            /*
            |--------------------------------------------------------------------------
            | Génération IA
            |--------------------------------------------------------------------------
            */

            if ($action === 'generate_ai_draft') {
                $apiKey =
                    getenv(
                        'OPENAI_API_KEY'
                    );

                if (
                    !is_string($apiKey)
                    || trim($apiKey) === ''
                ) {
                    $apiKey =
                        (string) (
                            $_ENV['OPENAI_API_KEY']
                            ?? ''
                        );
                }

                $apiKey =
                    trim($apiKey);

                if ($apiKey === '') {
                    throw new RuntimeException(
                        'Clé API OpenAI absente.'
                    );
                }

                $model =
                    getenv(
                        'OPENAI_MODEL'
                    );

                if (
                    !is_string($model)
                    || trim($model) === ''
                ) {
                    $model =
                        (string) (
                            $_ENV['OPENAI_MODEL']
                            ?? 'gpt-5.6-luna'
                        );
                }

                $model =
                    trim($model);

                if ($model === '') {
                    $model =
                        'gpt-5.6-luna';
                }

                $generator =
                    new AiReplyGenerator(
                        $apiKey,
                        $model
                    );

                $contentExtractor =
                    new ReplyContentExtractor();

                $draftService =
                    new AiReplyDraftService(
                        $pdo,
                        $generator,
                        $contentExtractor
                    );

                $result =
                    $draftService
                        ->generateForReply(
                            (int) $replyId
                        );

                $resultType =
                    !empty($result['created'])
                        ? 'created'
                        : 'existing';

            /*
            |--------------------------------------------------------------------------
            | Modification / approbation
            |--------------------------------------------------------------------------
            */

            } elseif (
                $action === 'save_reply_draft'
                || $action === 'approve_reply_draft'
            ) {
                $emailId =
                    filter_input(
                        INPUT_POST,
                        'email_id',
                        FILTER_VALIDATE_INT
                    );

                $subject =
                    (string) (
                        $_POST['subject']
                        ?? ''
                    );

                $bodyText =
                    (string) (
                        $_POST['body_text']
                        ?? ''
                    );

                if (
                    !$emailId
                    || $emailId < 1
                ) {
                    throw new InvalidArgumentException(
                        'Brouillon invalide.'
                    );
                }

                $manager =
                    new ReplyDraftManager(
                        $pdo
                    );

                if (
                    $action === 'save_reply_draft'
                ) {
                    $manager->updateDraft(
                        (int) $emailId,
                        $subject,
                        $bodyText
                    );

                    $resultType =
                        'saved';

                } else {
                    $manager->approveDraft(
                        (int) $emailId,
                        $subject,
                        $bodyText
                    );

                    $resultType =
                        'approved';
                }

                /*
                |--------------------------------------------------------------------------
                | Programmation de la reponse
                |--------------------------------------------------------------------------
                */

                } elseif (
                    $action === 'schedule_reply'
                ) {
                    $emailId =
                        filter_input(
                            INPUT_POST,
                            'email_id',
                            FILTER_VALIDATE_INT
                        );

                    if (
                        !$emailId
                        || $emailId < 1
                    ) {
                        throw new InvalidArgumentException(
                            'Reponse a programmer invalide.'
                        );
                    }

                    $prospectionConfig =
                        require __DIR__
                            . '/../../config/prospection.php';

                    $timezoneName =
                        (string) (
                            $prospectionConfig['timezone']
                            ?? 'Europe/Paris'
                        );

                    $timezone =
                        new DateTimeZone(
                            $timezoneName
                        );

                    $scheduledAt =
                        new DateTimeImmutable(
                            'now',
                            $timezone
                        );

                    $scheduler =
                        new ReplyScheduler(
                            $pdo
                        );

                    $scheduler->schedule(
                        (int) $emailId,
                        $scheduledAt
                    );

                    $resultType =
                        'scheduled';

            } else {
                throw new InvalidArgumentException(
                    'Action inconnue.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Rotation CSRF + PRG
            |--------------------------------------------------------------------------
            */

            $_SESSION['csrf_token'] =
                bin2hex(
                    random_bytes(32)
                );

            header(
                'Location: /admin/prospection/replies.php'
                . '?classification='
                . urlencode(
                    $returnClassification
                )
                . '&draft='
                . urlencode(
                    $resultType
                )
                . '&reply_id='
                . (int) $replyId
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
| Helpers
|--------------------------------------------------------------------------
*/

function replyValue(
    mixed $value
): string {
    $value = trim(
        (string) $value
    );

    return $value !== ''
        ? htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        )
        : '—';
}


function replyDate(
    mixed $value
): string {
    if (
        $value === null
        || trim((string) $value) === ''
    ) {
        return '—';
    }

    try {
        return (
            new DateTimeImmutable(
                (string) $value
            )
        )->format('d/m/Y H:i');
    } catch (Throwable) {
        return '—';
    }
}


function replyClassificationLabel(
    string $classification
): string {
    return match ($classification) {
        'positive' =>
            'Positive',

        'question' =>
            'Question',

        'negative' =>
            'Négative',

        'automatic' =>
            'Automatique',

        'unsubscribed' =>
            'Désinscription',

        default =>
            'À classifier',
    };
}


/*
|--------------------------------------------------------------------------
| Compteurs
|--------------------------------------------------------------------------
*/

$counts = [
    'all' => 0,
    'positive' => 0,
    'question' => 0,
    'negative' => 0,
    'unknown' => 0,
    'automatic' => 0,
    'unsubscribed' => 0,
];

$loadError = null;

try {
    $countStmt = $pdo->query(
        "
        SELECT
            classification,
            COUNT(*) AS total
        FROM prospect_replies
        GROUP BY classification
        "
    );

    foreach (
        $countStmt->fetchAll(PDO::FETCH_ASSOC)
        as $row
    ) {
        $type =
            (string) $row['classification'];

        $total =
            (int) $row['total'];

        if (
            array_key_exists(
                $type,
                $counts
            )
        ) {
            $counts[$type] =
                $total;

            $counts['all'] +=
                $total;
        }
    }
} catch (Throwable $exception) {
    $loadError =
        $exception->getMessage();
}


/*
|--------------------------------------------------------------------------
| Réponses
|--------------------------------------------------------------------------
*/

$replies = [];

try {
    $sql = "
        SELECT
            r.id AS reply_id,
            r.prospect_id,
            r.prospect_email_id,
            r.sender_email,
            r.subject,
            r.body_text,
            r.classification,
            r.is_read,
            r.received_at,

            p.company_name,
            p.activity,
            p.city,
            p.postal_code,

            source_email.subject
                AS outbound_subject,

            draft.id
                AS draft_email_id,

            draft.status
                AS draft_status,

            draft.subject
                AS draft_subject,

            draft.body_text
                AS draft_body_text,

            draft.approved_at
                AS draft_approved_at,

            draft.scheduled_at
                AS draft_scheduled_at

        FROM prospect_replies r

        INNER JOIN prospects p
            ON p.id = r.prospect_id

        LEFT JOIN prospect_emails source_email
            ON source_email.id =
                r.prospect_email_id

        LEFT JOIN prospect_emails draft
            ON draft.source_reply_id =
                r.id
    ";

    $params = [];

    if ($classification !== 'all') {
        $sql .= "
            WHERE r.classification =
                :classification
        ";

        $params['classification'] =
            $classification;
    }

    $sql .= "
        ORDER BY
            r.received_at DESC,
            r.id DESC
        LIMIT 100
    ";

    $stmt =
        $pdo->prepare(
            $sql
        );

    $stmt->execute(
        $params
    );

    $replies =
        $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
} catch (Throwable $exception) {
    $replies = [];

    if ($loadError === null) {
        $loadError =
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
        Réponses — OL Creative Studio
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
                            Réponses reçues
                        </h1>

                        <p>
                            Consultez et traitez les réponses
                            reçues à vos campagnes de prospection.
                        </p>

                    </div>

                </div>

                <?php if (
                    isset($_GET['draft'])
                    && $_GET['draft'] === 'created'
                ): ?>

                    <div class="admin-alert admin-alert--success">
                        Brouillon IA généré avec succès.
                        Il doit être relu et validé avant tout envoi.
                    </div>

                <?php elseif (
                    isset($_GET['draft'])
                    && $_GET['draft'] === 'existing'
                ): ?>

                    <div class="admin-alert admin-alert--success">
                        Un brouillon existe déjà pour cette réponse.
                    </div>

                <?php elseif (
                    isset($_GET['draft'])
                    && $_GET['draft'] === 'saved'
                ): ?>

                    <div class="admin-alert admin-alert--success">
                        Brouillon enregistré.
                    </div>

                <?php elseif (
                    isset($_GET['draft'])
                    && $_GET['draft'] === 'approved'
                ): ?>

                    <div class="admin-alert admin-alert--success">
                        Réponse validée.
                        Aucun e-mail n'a encore été envoyé.
                    </div>

                <?php elseif (
                    isset($_GET['draft'])
                    && $_GET['draft'] === 'scheduled'
                ): ?>

                    <div class="admin-alert admin-alert--success">
                        Réponse programmée pour envoi.
                        Aucun e-mail n'a encore été envoyé.
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


                <?php if ($loadError !== null): ?>

                    <div
                        class="admin-alert admin-alert--error"
                    >
                        <?= htmlspecialchars(
                            $loadError,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                <?php endif; ?>


                <!-- =========================
                     FILTRES
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Boîte de réception
                            </span>

                            <h2>
                                <?= $counts['all'] ?>
                                réponse<?= $counts['all'] > 1 ? 's' : '' ?>
                            </h2>

                        </div>

                    </div>


                    <div class="prospect-filters">

                        <?php
                        $filters = [
                            'all' =>
                                'Toutes',

                            'positive' =>
                                'Positives',

                            'question' =>
                                'Questions',

                            'negative' =>
                                'Négatives',

                            'unknown' =>
                                'À classifier',

                            'automatic' =>
                                'Automatiques',

                            'unsubscribed' =>
                                'Désinscriptions',
                        ];
                        ?>

                        <?php foreach (
                            $filters
                            as $filterKey => $filterLabel
                        ): ?>

                            <a
                                href="/admin/prospection/replies.php?classification=<?= urlencode($filterKey) ?>"
                                class="admin-button<?= $classification === $filterKey ? ' admin-button--primary' : '' ?>"
                            >
                                <?= htmlspecialchars(
                                    $filterLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                (<?= $counts[$filterKey] ?>)
                            </a>

                        <?php endforeach; ?>

                    </div>

                </section>


                <!-- =========================
                     LISTE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Messages
                            </span>

                            <h2>
                                Dernières réponses
                            </h2>

                        </div>

                        <span class="admin-badge">
                            <?= count($replies) ?>
                        </span>

                    </div>


                    <?php if (empty($replies)): ?>

                        <div class="admin-empty-state">

                            <p>
                                Aucune réponse dans cette catégorie.
                            </p>

                            <span class="admin-stat-card__meta">
                                Les nouvelles réponses détectées
                                par la boîte de réception
                                apparaîtront ici.
                            </span>

                        </div>

                    <?php else: ?>

                        <div class="admin-project-list">

                            <?php foreach (
                                $replies
                                as $reply
                            ): ?>

                                <article class="prospect-row">

                                    <div class="prospect-row__main">

                                        <div
                                            class="prospect-row__identity"
                                        >

                                            <span
                                                class="prospect-row__location"
                                            >
                                                <?= replyValue(
                                                    $reply['city']
                                                    ?? null
                                                ) ?>

                                                <?php if (
                                                    !empty(
                                                        $reply[
                                                            'postal_code'
                                                        ]
                                                    )
                                                ): ?>

                                                    ·

                                                    <?= replyValue(
                                                        $reply[
                                                            'postal_code'
                                                        ]
                                                    ) ?>

                                                <?php endif; ?>
                                            </span>

                                            <h3>
                                                <?= replyValue(
                                                    $reply[
                                                        'company_name'
                                                    ]
                                                    ?? null
                                                ) ?>
                                            </h3>

                                            <?php if (
                                                !empty(
                                                    $reply[
                                                        'activity'
                                                    ]
                                                )
                                            ): ?>

                                                <span
                                                    class="prospect-row__activity"
                                                >
                                                    <?= replyValue(
                                                        $reply[
                                                            'activity'
                                                        ]
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <span class="admin-badge">
                                            <?= htmlspecialchars(
                                                replyClassificationLabel(
                                                    (string) $reply[
                                                        'classification'
                                                    ]
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </div>


                                    <div
                                        class="prospect-reconciliation__details"
                                    >

                                        <p>
                                            <strong>
                                                Reçu :
                                            </strong>

                                            <?= replyDate(
                                                $reply[
                                                    'received_at'
                                                ]
                                                ?? null
                                            ) ?>
                                        </p>

                                        <p>
                                            <strong>
                                                Expéditeur :
                                            </strong>

                                            <?= replyValue(
                                                $reply[
                                                    'sender_email'
                                                ]
                                                ?? null
                                            ) ?>
                                        </p>

                                        <p>
                                            <strong>
                                                Sujet :
                                            </strong>

                                            <?= replyValue(
                                                $reply[
                                                    'subject'
                                                ]
                                                ?? null
                                            ) ?>
                                        </p>


                                        <?php if (
                                            !empty(
                                                $reply[
                                                    'outbound_subject'
                                                ]
                                            )
                                        ): ?>

                                            <p>
                                                <strong>
                                                    En réponse à :
                                                </strong>

                                                <?= replyValue(
                                                    $reply[
                                                        'outbound_subject'
                                                    ]
                                                ) ?>
                                            </p>

                                        <?php endif; ?>


                                        <?php if (
                                            !empty(
                                                $reply[
                                                    'body_text'
                                                ]
                                            )
                                        ): ?>

                                            <div>

                                                <strong>
                                                    Message :
                                                </strong>

                                                <p>
                                                    <?= nl2br(
                                                        htmlspecialchars(
                                                            trim(
                                                                (string) $reply[
                                                                    'body_text'
                                                                ]
                                                            ),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        )
                                                    ) ?>
                                                </p>

                                            </div>

                                        <?php endif; ?>

                                        <?php if (
                                            !empty(
                                                $reply[
                                                    'draft_email_id'
                                                ]
                                            )
                                        ): ?>

                                            <div class="prospect-reconciliation__details">

                                                <p>
                                                    <strong>
                                                        Brouillon IA
                                                    </strong>

                                                    <span class="admin-badge">
                                                        <?= replyValue(
                                                            $reply[
                                                                'draft_status'
                                                            ]
                                                            ?? null
                                                        ) ?>
                                                    </span>
                                                </p>


                                                <?php if (
                                                    $reply['draft_status']
                                                    === 'draft'
                                                ): ?>

                                                    <form method="post">

                                                        <input
                                                            type="hidden"
                                                            name="csrf"
                                                            value="<?= htmlspecialchars(
                                                                $_SESSION['csrf_token'],
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="reply_id"
                                                            value="<?= (int) $reply['reply_id'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="email_id"
                                                            value="<?= (int) $reply['draft_email_id'] ?>"
                                                        >

                                                        <input
                                                            type="hidden"
                                                            name="classification"
                                                            value="<?= htmlspecialchars(
                                                                $classification,
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?>"
                                                        >


                                                        <div class="admin-form__field">

                                                            <label
                                                                for="reply-subject-<?= (int) $reply['reply_id'] ?>"
                                                            >
                                                                Objet
                                                            </label>

                                                            <input
                                                                id="reply-subject-<?= (int) $reply['reply_id'] ?>"
                                                                type="text"
                                                                name="subject"
                                                                maxlength="255"
                                                                required
                                                                value="<?= htmlspecialchars(
                                                                    (string) (
                                                                        $reply[
                                                                            'draft_subject'
                                                                        ]
                                                                        ?? ''
                                                                    ),
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                            >

                                                        </div>


                                                        <div class="admin-form__field">

                                                            <label
                                                                for="reply-body-<?= (int) $reply['reply_id'] ?>"
                                                            >
                                                                Réponse
                                                            </label>

                                                            <textarea
                                                                id="reply-body-<?= (int) $reply['reply_id'] ?>"
                                                                name="body_text"
                                                                rows="8"
                                                                required
                                                            ><?= htmlspecialchars(
                                                                (string) (
                                                                    $reply[
                                                                        'draft_body_text'
                                                                    ]
                                                                    ?? ''
                                                                ),
                                                                ENT_QUOTES,
                                                                'UTF-8'
                                                            ) ?></textarea>

                                                        </div>

                                                        <div class="prospect-detail__actions">

                                                            <button
                                                                type="submit"
                                                                name="action"
                                                                value="save_reply_draft"
                                                                class="admin-button"
                                                            >
                                                                Enregistrer
                                                            </button>

                                                            <button
                                                                type="submit"
                                                                name="action"
                                                                value="approve_reply_draft"
                                                                class="admin-button admin-button--primary"
                                                            >
                                                                Valider la réponse
                                                            </button>

                                                        </div>

                                                    </form>

                                                    <p class="prospect-detail__action-note">
                                                        Vous pouvez modifier le brouillon avant validation.
                                                        La validation ne déclenche aucun envoi.
                                                    </p>


                                                <?php else: ?>

                                                    <p>
                                                        <strong>
                                                            Objet :
                                                        </strong>

                                                        <?= replyValue(
                                                            $reply[
                                                                'draft_subject'
                                                            ]
                                                            ?? null
                                                        ) ?>
                                                    </p>


                                                    <?php if (
                                                        !empty(
                                                            $reply[
                                                                'draft_body_text'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <div>

                                                            <strong>
                                                                Réponse validée :
                                                            </strong>

                                                            <p>
                                                                <?= nl2br(
                                                                    htmlspecialchars(
                                                                        trim(
                                                                            (string) $reply[
                                                                                'draft_body_text'
                                                                            ]
                                                                        ),
                                                                        ENT_QUOTES,
                                                                        'UTF-8'
                                                                    )
                                                                ) ?>
                                                            </p>

                                                        </div>

                                                    <?php endif; ?>


                                                    <?php if (
                                                        !empty(
                                                            $reply[
                                                                'draft_approved_at'
                                                            ]
                                                        )
                                                    ): ?>

                                                        <p class="prospect-detail__action-note">
                                                            Validée le
                                                            <?= replyDate(
                                                                $reply[
                                                                    'draft_approved_at'
                                                                ]
                                                            ) ?>.
                                                            Aucun e-mail n'a encore été envoyé.
                                                        </p>

                                                    <?php endif; ?>

                                                    <?php if (
                                                        $reply['draft_status']
                                                        === 'approved'
                                                    ): ?>

                                                        <form method="post">

                                                            <input
                                                                type="hidden"
                                                                name="csrf"
                                                                value="<?= htmlspecialchars(
                                                                    $_SESSION['csrf_token'],
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="action"
                                                                value="schedule_reply"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="reply_id"
                                                                value="<?= (int) $reply['reply_id'] ?>"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="email_id"
                                                                value="<?= (int) $reply['draft_email_id'] ?>"
                                                            >

                                                            <input
                                                                type="hidden"
                                                                name="classification"
                                                                value="<?= htmlspecialchars(
                                                                    $classification,
                                                                    ENT_QUOTES,
                                                                    'UTF-8'
                                                                ) ?>"
                                                            >

                                                            <div class="prospect-detail__actions">

                                                                <button
                                                                    type="submit"
                                                                    class="admin-button admin-button--primary"
                                                                >
                                                                    Programmer l'envoi
                                                                </button>

                                                            </div>

                                                        </form>

                                                        <p class="prospect-detail__action-note">
                                                            La programmation ne déclenche aucun envoi immédiat.
                                                        </p>

                                                    <?php elseif (
                                                        $reply['draft_status']
                                                        === 'scheduled'
                                                    ): ?>

                                                        <p class="prospect-detail__action-note">
                                                            Programmée le
                                                            <?= replyDate(
                                                                $reply[
                                                                    'draft_scheduled_at'
                                                                ]
                                                                ?? null
                                                            ) ?>.
                                                            En attente d'envoi.
                                                        </p>

                                                    <?php endif; ?>

                                                <?php endif; ?>

                                            </div>

                                        <?php elseif (
                                            in_array(
                                                (string) $reply[
                                                    'classification'
                                                ],
                                                [
                                                    'positive',
                                                    'question',
                                                ],
                                                true
                                            )
                                        ): ?>

                                            <div class="prospect-detail__actions">

                                                <form method="post">

                                                    <input
                                                        type="hidden"
                                                        name="csrf"
                                                        value="<?= htmlspecialchars(
                                                            $_SESSION['csrf_token'],
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="generate_ai_draft"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="reply_id"
                                                        value="<?= (int) $reply['reply_id'] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="classification"
                                                        value="<?= htmlspecialchars(
                                                            $classification,
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>"
                                                    >

                                                    <button
                                                        type="submit"
                                                        class="admin-button admin-button--primary"
                                                    >
                                                        Générer un brouillon IA
                                                    </button>

                                                </form>

                                            </div>

                                            <p class="prospect-detail__action-note">
                                                La génération crée uniquement un brouillon.
                                                Aucun e-mail ne sera envoyé automatiquement.
                                            </p>

                                        <?php endif; ?>

                                    </div>


                                    <div class="prospect-row__footer">

                                        <div
                                            class="prospect-row__actions"
                                        >

                                            <a
                                                href="/admin/prospection/prospect.php?id=<?= (int) $reply['prospect_id'] ?>"
                                                class="admin-text-link"
                                            >
                                                Ouvrir le prospect

                                                <span
                                                    aria-hidden="true"
                                                >
                                                    →
                                                </span>
                                            </a>

                                        </div>

                                    </div>

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
