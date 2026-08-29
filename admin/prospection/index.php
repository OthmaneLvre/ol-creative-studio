<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Statistiques Prospection
|--------------------------------------------------------------------------
*/

$totalProspects = 0;
$pendingProspects = 0;
$scheduledProspects = 0;
$repliedProspects = 0;

try {
    $totalProspectsStmt = $pdo->query(
        'SELECT COUNT(*) AS total
         FROM prospects'
    );

    $totalProspects = (int) (
        $totalProspectsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
    );


    $pendingProspectsStmt = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM prospects
         WHERE status = 'pending_validation'"
    );

    $pendingProspects = (int) (
        $pendingProspectsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
    );


    $scheduledProspectsStmt = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM prospects
         WHERE status = 'scheduled'"
    );

    $scheduledProspects = (int) (
        $scheduledProspectsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
    );


    $repliedProspectsStmt = $pdo->query(
        "SELECT COUNT(*) AS total
         FROM prospects
         WHERE status IN ('replied', 'qualified', 'converted')"
    );

    $repliedProspects = (int) (
        $repliedProspectsStmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0
    );
} catch (PDOException $exception) {
    /*
     * Le module peut être affiché avant l'installation
     * de ses tables SQL.
     */
}


/*
|--------------------------------------------------------------------------
| Derniers prospects
|--------------------------------------------------------------------------
*/

$recentProspects = [];

try {
    $recentProspectsStmt = $pdo->query(
        'SELECT
            id,
            company_name,
            activity,
            city,
            status,
            score,
            discovered_at
         FROM prospects
         ORDER BY discovered_at DESC
         LIMIT 5'
    );

    $recentProspects = $recentProspectsStmt->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (PDOException $exception) {
    $recentProspects = [];
}


/*
|--------------------------------------------------------------------------
| Configuration de la page
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Prospection';
$adminActivePage = 'prospection';

$dailyTarget = (int) prospection_config(
    'discovery.daily_target',
    3
);

$maxPendingProspects = (int) prospection_config(
    'discovery.max_pending_prospects',
    30
);

$maxDailySends = (int) prospection_config(
    'sending.max_daily_sends',
    5
);

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
        Prospection — OL Creative Studio
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

                <!-- =========================
                     INTRO
                     ========================= -->

                <section class="admin-dashboard__intro">

                    <div>

                        <span class="admin-eyebrow">
                            Acquisition
                        </span>

                        <h1>
                            Prospection
                        </h1>

                        <p>
                            Analyse, valide et suis les prospects
                            détectés automatiquement par OL Creative Studio.
                        </p>

                    </div>


                    <a
                        href="/admin/prospection/prospects.php"
                        class="admin-button admin-button--primary"
                    >
                        Voir les prospects
                        <span aria-hidden="true">→</span>
                    </a>

                </section>


                <!-- =========================
                     STATS
                     ========================= -->

                <section
                    class="admin-stats"
                    aria-label="Statistiques de prospection"
                >

                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Prospects
                        </span>

                        <strong>
                            <?= $totalProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            prospects enregistrés
                        </span>

                    </article>


                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            À valider
                        </span>

                        <strong>
                            <?= $pendingProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            sur <?= $maxPendingProspects ?> maximum
                        </span>

                    </article>


                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Programmés
                        </span>

                        <strong>
                            <?= $scheduledProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            <?= $maxDailySends ?> envois maximum / jour
                        </span>

                    </article>


                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Réponses
                        </span>

                        <strong>
                            <?= $repliedProspects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            prospects ayant répondu
                        </span>

                    </article>

                </section>


                <!-- =========================
                     AUTOMATISATION
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Automatisation
                            </span>

                            <h2>
                                Recherche quotidienne
                            </h2>

                        </div>

                    </div>


                    <div class="admin-activity__grid">

                        <article class="admin-activity-card">

                            <span class="admin-activity-card__label">
                                Objectif quotidien
                            </span>

                            <h3>
                                <?= $dailyTarget ?> prospects
                            </h3>

                            <p>
                                Le moteur sélectionnera chaque jour
                                de nouveaux prospects à analyser.
                            </p>

                        </article>


                        <article class="admin-activity-card">

                            <span class="admin-activity-card__label">
                                Préparation
                            </span>

                            <h3>
                                <?= htmlspecialchars(
                                    (string) prospection_config(
                                        'discovery.preparation_time',
                                        '09:00'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>

                            <p>
                                Une notification sera générée lorsque
                                les prospects du jour seront prêts.
                            </p>

                        </article>


                        <article class="admin-activity-card">

                            <span class="admin-activity-card__label">
                                Envoi
                            </span>

                            <h3>
                                <?= htmlspecialchars(
                                    (string) prospection_config(
                                        'sending.send_time',
                                        '09:30'
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </h3>

                            <p>
                                Les messages validés seront programmés
                                pour le prochain jour ouvré.
                            </p>

                        </article>

                    </div>

                </section>


                <!-- =========================
                     DERNIERS PROSPECTS
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                File de prospection
                            </span>

                            <h2>
                                Derniers prospects
                            </h2>

                        </div>


                        <a
                            href="/admin/prospection/prospects.php"
                            class="admin-text-link"
                        >
                            Voir toute la file
                            <span aria-hidden="true">→</span>
                        </a>

                    </div>


                    <?php if (empty($recentProspects)): ?>

                        <div class="admin-empty-state">

                            <p>
                                Aucun prospect enregistré pour le moment.
                            </p>

                            <span class="admin-stat-card__meta">
                                Les prochains prospects apparaîtront ici
                                après la première recherche automatique.
                            </span>

                        </div>

                    <?php else: ?>

                        <div class="admin-project-list">

                            <?php foreach ($recentProspects as $prospect): ?>

                                <article class="admin-project-row">

                                    <div class="admin-project-row__content">

                                        <div>

                                            <span class="admin-project-row__category">
                                                <?= htmlspecialchars(
                                                    $prospect['city'] ?: 'Localisation inconnue',
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                            <h3>
                                                <?= htmlspecialchars(
                                                    $prospect['company_name'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h3>

                                            <?php if (!empty($prospect['activity'])): ?>

                                                <span class="admin-stat-card__meta">
                                                    <?= htmlspecialchars(
                                                        $prospect['activity'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <span class="admin-badge">
                                            <?= htmlspecialchars(
                                                $prospect['status'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </span>

                                    </div>


                                    <div class="admin-project-row__actions">

                                        <?php if ($prospect['score'] !== null): ?>

                                            <span>
                                                Score :
                                                <?= (int) $prospect['score'] ?>/100
                                            </span>

                                        <?php endif; ?>


                                        <a
                                            href="/admin/prospection/prospect.php?id=<?= (int) $prospect['id'] ?>"
                                        >
                                            Ouvrir
                                        </a>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>


                <!-- =========================
                     ACTIONS RAPIDES
                     ========================= -->

                <section class="admin-quick-actions">

                    <a
                        href="/admin/prospection/prospects.php"
                        class="admin-quick-action"
                    >
                        <span>Prospection</span>

                        <strong>
                            Gérer la file
                        </strong>

                        <span aria-hidden="true">↗</span>
                    </a>


                    <a
                        href="/admin/prospection/prospects.php?status=pending_validation"
                        class="admin-quick-action"
                    >
                        <span>Validation</span>

                        <strong>
                            Prospects à vérifier
                        </strong>

                        <span aria-hidden="true">↗</span>
                    </a>

                </section>

            </div>

        </main>

    </div>


    <?php
    include dirname(__DIR__) . '/partials/footer.php';
    ?>

</body>

</html>
