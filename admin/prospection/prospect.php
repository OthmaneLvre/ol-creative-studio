<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Prospect demandé
|--------------------------------------------------------------------------
*/

$prospectId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$prospectId || $prospectId < 1) {
    header('Location: /admin/prospection/prospects.php');
    exit;
}

/*
|--------------------------------------------------------------------------
| Prospect
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT *
     FROM prospects
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    'id' => $prospectId,
]);

$prospect = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$prospect) {
    http_response_code(404);

    $adminPageTitle = 'Prospect introuvable';
    $adminActivePage = 'prospection';
} else {
    $adminPageTitle = (string) $prospect['company_name'];
    $adminActivePage = 'prospection';
}

/*
|--------------------------------------------------------------------------
| Analyse
|--------------------------------------------------------------------------
*/

$analysis = null;

if ($prospect) {
    try {
        $analysisStmt = $pdo->prepare(
            'SELECT *
             FROM prospect_analyses
             WHERE prospect_id = :prospect_id
             ORDER BY id DESC
             LIMIT 1'
        );

        $analysisStmt->execute([
            'prospect_id' => $prospectId,
        ]);

        $analysis = $analysisStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $exception) {
        $analysis = null;
    }
}

/*
|--------------------------------------------------------------------------
| Dernier e-mail
|--------------------------------------------------------------------------
*/

$email = null;

if ($prospect) {
    try {
        $emailStmt = $pdo->prepare(
            'SELECT *
             FROM prospect_emails
             WHERE prospect_id = :prospect_id
             ORDER BY id DESC
             LIMIT 1'
        );

        $emailStmt->execute([
            'prospect_id' => $prospectId,
        ]);

        $email = $emailStmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (PDOException $exception) {
        $email = null;
    }
}

/*
|--------------------------------------------------------------------------
| Historique
|--------------------------------------------------------------------------
*/

$events = [];

if ($prospect) {
    try {
        $eventsStmt = $pdo->prepare(
            'SELECT *
             FROM prospect_events
             WHERE prospect_id = :prospect_id
             ORDER BY created_at DESC, id DESC'
        );

        $eventsStmt->execute([
            'prospect_id' => $prospectId,
        ]);

        $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $exception) {
        $events = [];
    }
}

/*
|--------------------------------------------------------------------------
| Libellés
|--------------------------------------------------------------------------
*/

$statusLabels = [
    'discovered' => 'Découvert',
    'analyzing' => 'Analyse en cours',
    'analyzed' => 'Analysé',
    'pending_validation' => 'À valider',
    'approved' => 'Validé',
    'scheduled' => 'Programmé',
    'contacted' => 'Contacté',
    'follow_up' => 'Relance',
    'replied' => 'Réponse reçue',
    'qualified' => 'Qualifié',
    'converted' => 'Converti',
    'rejected' => 'Refusé',
    'unsubscribed' => 'Désinscrit',
    'invalid' => 'Invalide',
];

$statusLabel = $prospect
    ? ($statusLabels[$prospect['status']] ?? $prospect['status'])
    : '';

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function prospectValue(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

function prospectDate(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '—';
    }

    try {
        return (new DateTimeImmutable($value))
            ->format('d/m/Y à H:i');
    } catch (Exception $exception) {
        return htmlspecialchars(
            $value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

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
        <?= htmlspecialchars(
            $adminPageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        — OL Creative Studio
    </title>

    <link
        rel="icon"
        type="image/x-icon"
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
    include_once dirname(__DIR__) . '/partials/sidebar.php';
    ?>

    <main class="admin-main">

        <?php
        include_once dirname(__DIR__) . '/partials/header.php';
        ?>

        <div class="admin-content">

            <?php if (!$prospect): ?>

                <section class="admin-panel">

                    <div class="admin-empty-state">

                        <span class="admin-eyebrow">
                            Erreur
                        </span>

                        <h1>
                            Prospect introuvable
                        </h1>

                        <p>
                            Ce prospect n'existe pas ou a été supprimé.
                        </p>

                        <a
                            href="/admin/prospection/prospects.php"
                            class="admin-button admin-button--primary"
                        >
                            Retour aux prospects
                        </a>

                    </div>

                </section>

            <?php else: ?>

                <!-- =========================
                     INTRO
                     ========================= -->

                <section class="admin-dashboard__intro">

                    <div>

                        <span class="admin-eyebrow">
                            Prospect #<?= (int) $prospect['id'] ?>
                        </span>

                        <h1>
                            <?= htmlspecialchars(
                                $prospect['company_name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h1>

                        <p>
                            <?= prospectValue($prospect['activity']) ?>

                            <?php if (!empty($prospect['city'])): ?>
                                ·
                                <?= prospectValue($prospect['city']) ?>
                            <?php endif; ?>
                        </p>

                    </div>

                    <div class="prospect-detail__intro-actions">

                        <span class="admin-badge">
                            <?= htmlspecialchars(
                                $statusLabel,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                        <a
                            href="/admin/prospection/prospects.php"
                            class="admin-button"
                        >
                            ← Retour à la file
                        </a>

                    </div>

                </section>

                <!-- =========================
                     SCORE + IDENTITÉ
                     ========================= -->

                <section class="prospect-detail__overview">

                    <article class="prospect-score-card">

                        <span class="admin-eyebrow">
                            Score
                        </span>

                        <?php if ($prospect['score'] !== null): ?>

                            <strong class="prospect-score-card__value">
                                <?= (int) $prospect['score'] ?>
                                <small>/100</small>
                            </strong>

                        <?php else: ?>

                            <strong class="prospect-score-card__value">
                                —
                            </strong>

                        <?php endif; ?>

                        <p>
                            Potentiel commercial estimé
                            par le moteur de prospection.
                        </p>

                    </article>

                    <article class="admin-panel prospect-detail__identity">

                        <div class="admin-panel__header">

                            <div>

                                <span class="admin-eyebrow">
                                    Entreprise
                                </span>

                                <h2>
                                    Informations
                                </h2>

                            </div>

                        </div>

                        <dl class="prospect-detail__data">

                            <div>
                                <dt>Activité</dt>
                                <dd>
                                    <?= prospectValue($prospect['activity']) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Commune</dt>
                                <dd>
                                    <?= prospectValue($prospect['city']) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Code postal</dt>
                                <dd>
                                    <?= prospectValue($prospect['postal_code']) ?>
                                </dd>
                            </div>

                            <div>
                                <dt>Département</dt>
                                <dd>
                                    <?= prospectValue($prospect['department']) ?>
                                </dd>
                            </div>

                        </dl>

                    </article>

                </section>

                <!-- =========================
                     CONTACT
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Contact
                            </span>

                            <h2>
                                Coordonnées détectées
                            </h2>

                        </div>

                    </div>

                    <dl class="prospect-detail__data prospect-detail__data--three">

                        <div>

                            <dt>E-mail</dt>

                            <dd>
                                <?php if (!empty($prospect['email'])): ?>

                                    <a
                                        href="mailto:<?= htmlspecialchars(
                                            $prospect['email'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                    >
                                        <?= prospectValue($prospect['email']) ?>
                                    </a>

                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>

                        </div>

                        <div>

                            <dt>Téléphone</dt>

                            <dd>
                                <?= prospectValue($prospect['phone']) ?>
                            </dd>

                        </div>

                        <div>

                            <dt>Site internet</dt>

                            <dd>
                                <?php if (!empty($prospect['website_url'])): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $prospect['website_url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Visiter le site ↗
                                    </a>

                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </dd>

                        </div>

                    </dl>

                </section>

                <!-- =========================
                     SOURCE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Acquisition
                            </span>

                            <h2>
                                Source du prospect
                            </h2>

                        </div>

                    </div>

                    <dl class="prospect-detail__data prospect-detail__data--three">

                        <div>
                            <dt>Source</dt>
                            <dd>
                                <?= prospectValue($prospect['source']) ?>
                            </dd>
                        </div>

                        <div>

                            <dt>Page source</dt>

                            <dd>

                                <?php if (!empty($prospect['source_url'])): ?>

                                    <a
                                        href="<?= htmlspecialchars(
                                            $prospect['source_url'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Ouvrir ↗
                                    </a>

                                <?php else: ?>
                                    —
                                <?php endif; ?>

                            </dd>

                        </div>

                        <div>
                            <dt>Découvert le</dt>
                            <dd>
                                <?= prospectDate($prospect['discovered_at']) ?>
                            </dd>
                        </div>

                    </dl>

                </section>

                <!-- =========================
                     ANALYSE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Analyse
                            </span>

                            <h2>
                                Audit du site
                            </h2>

                        </div>

                    </div>

                    <?php if (!$analysis): ?>

                        <div class="prospect-detail__pending">

                            <strong>
                                Analyse non disponible
                            </strong>

                            <p>
                                Le moteur d'analyse n'a pas encore
                                audité ce prospect.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="prospect-analysis">

                            <p>
                                Une analyse technique est disponible
                                pour ce prospect.
                            </p>

                        </div>

                    <?php endif; ?>

                </section>

                <!-- =========================
                     MESSAGE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                E-mail
                            </span>

                            <h2>
                                Message de prospection
                            </h2>

                        </div>

                    </div>

                    <?php if (!$email): ?>

                        <div class="prospect-detail__pending">

                            <strong>
                                Aucun message généré
                            </strong>

                            <p>
                                Le message personnalisé sera généré
                                après l'analyse du prospect.
                            </p>

                        </div>

                    <?php else: ?>

                        <div class="prospect-email-preview">

                            <span class="prospect-email-preview__label">
                                Objet
                            </span>

                            <strong>
                                <?= prospectValue($email['subject'] ?? null) ?>
                            </strong>

                            <?php if (!empty($email['body_text'])): ?>

                                <div class="prospect-email-preview__body">
                                    <?= nl2br(
                                        htmlspecialchars(
                                            $email['body_text'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>
                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>

                </section>

                <!-- =========================
                     ACTIONS
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Décision
                            </span>

                            <h2>
                                Validation
                            </h2>

                        </div>

                    </div>

                    <?php if ($prospect['status'] === 'pending_validation'): ?>

                        <div class="prospect-detail__actions">

                            <button
                                type="button"
                                class="admin-button admin-button--primary"
                                disabled
                            >
                                Valider le prospect
                            </button>

                            <button
                                type="button"
                                class="admin-button"
                                disabled
                            >
                                Refuser
                            </button>

                        </div>

                        <p class="prospect-detail__action-note">
                            Les actions seront activées lors de
                            l'implémentation du workflow de validation.
                        </p>

                    <?php else: ?>

                        <p>
                            Statut actuel :
                            <strong>
                                <?= htmlspecialchars(
                                    $statusLabel,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </strong>
                        </p>

                    <?php endif; ?>

                </section>

                <!-- =========================
                     CHRONOLOGIE
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Historique
                            </span>

                            <h2>
                                Chronologie
                            </h2>

                        </div>

                    </div>

                    <div class="prospect-timeline">

                        <article class="prospect-timeline__item">

                            <span class="prospect-timeline__dot"></span>

                            <div>

                                <strong>
                                    Prospect découvert
                                </strong>

                                <span>
                                    <?= prospectDate(
                                        $prospect['discovered_at']
                                    ) ?>
                                </span>

                            </div>

                        </article>

                        <?php foreach ($events as $event): ?>

                            <article class="prospect-timeline__item">

                                <span class="prospect-timeline__dot"></span>

                                <div>

                                    <strong>
                                        <?= prospectValue(
                                            $event['event_type'] ?? 'Événement'
                                        ) ?>
                                    </strong>

                                    <?php if (!empty($event['description'])): ?>

                                        <p>
                                            <?= prospectValue(
                                                $event['description']
                                            ) ?>
                                        </p>

                                    <?php endif; ?>

                                    <span>
                                        <?= prospectDate(
                                            $event['created_at'] ?? null
                                        ) ?>
                                    </span>

                                </div>

                            </article>

                        <?php endforeach; ?>

                    </div>

                </section>

            <?php endif; ?>

        </div>

    </main>

</div>

<?php
include dirname(__DIR__) . '/partials/footer.php';
?>

</body>

</html>
