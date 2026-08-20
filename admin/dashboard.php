<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| Statistiques Portfolio
|--------------------------------------------------------------------------
*/

$statsStmt = $pdo->query(
    'SELECT
        COUNT(*) AS total_projects,
        SUM(
            CASE
                WHEN featured = 1 THEN 1
                ELSE 0
            END
        ) AS featured_projects,
        MAX(annee) AS latest_year
     FROM portfolio'
);

$portfolioStats = $statsStmt->fetch(
    PDO::FETCH_ASSOC
);

$totalProjects =
    (int) ($portfolioStats['total_projects'] ?? 0);

$featuredProjects =
    (int) ($portfolioStats['featured_projects'] ?? 0);

$latestYear =
    $portfolioStats['latest_year'] ?? null;


/*
|--------------------------------------------------------------------------
| Derniers projets
|--------------------------------------------------------------------------
*/

$projectsStmt = $pdo->query(
    'SELECT
        id,
        titre,
        categorie,
        image,
        featured,
        date_creation
     FROM portfolio
     ORDER BY date_creation DESC
     LIMIT 4'
);

$recentProjects = $projectsStmt->fetchAll(
    PDO::FETCH_ASSOC
);


/*
|--------------------------------------------------------------------------
| Configuration de la page
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Tableau de bord';
$adminActivePage = 'dashboard';

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
        Tableau de bord — OL Creative Studio
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
        include __DIR__ . '/partials/sidebar.php';
        ?>


        <main class="admin-main">

            <?php
            include __DIR__ . '/partials/header.php';
            ?>


            <div class="admin-content">

                <!-- =========================
                     INTRO
                     ========================= -->

                <section class="admin-dashboard__intro">

                    <div>

                        <span class="admin-eyebrow">
                            Vue d’ensemble
                        </span>

                        <h1>
                            Bonjour,
                            <?= htmlspecialchars(
                                $_SESSION['admin_name'] ?? 'Admin',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>.
                        </h1>

                        <p>
                            Voici un aperçu de l’activité
                            d’OL Creative Studio.
                        </p>

                    </div>


                    <a
                        href="/admin/portfolio-add.php"
                        class="admin-button admin-button--primary"
                    >
                        Nouveau projet
                        <span aria-hidden="true">+</span>
                    </a>

                </section>


                <!-- =========================
                     STATS
                     ========================= -->

                <section
                    class="admin-stats"
                    aria-label="Statistiques"
                >

                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Projets
                        </span>

                        <strong>
                            <?= $totalProjects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            projets enregistrés
                        </span>

                    </article>


                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Mis en avant
                        </span>

                        <strong>
                            <?= $featuredProjects ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            projets featured
                        </span>

                    </article>


                    <article class="admin-stat-card">

                        <span class="admin-stat-card__label">
                            Dernière année
                        </span>

                        <strong>
                            <?= $latestYear
                                ? htmlspecialchars(
                                    (string) $latestYear,
                                    ENT_QUOTES,
                                    'UTF-8'
                                )
                                : '—'
                            ?>
                        </strong>

                        <span class="admin-stat-card__meta">
                            dernière réalisation
                        </span>

                    </article>

                </section>


                <!-- =========================
                     PROJETS RÉCENTS
                     ========================= -->

                <section class="admin-panel">

                    <div class="admin-panel__header">

                        <div>

                            <span class="admin-eyebrow">
                                Portfolio
                            </span>

                            <h2>
                                Projets récents
                            </h2>

                        </div>


                        <a
                            href="/admin/portfolio.php"
                            class="admin-text-link"
                        >
                            Voir tous les projets
                            <span aria-hidden="true">→</span>
                        </a>

                    </div>


                    <?php if (empty($recentProjects)): ?>

                        <div class="admin-empty-state">

                            <p>
                                Aucun projet enregistré
                                pour le moment.
                            </p>

                            <a
                                href="/admin/portfolio-add.php"
                                class="admin-button admin-button--primary"
                            >
                                Ajouter un projet
                            </a>

                        </div>

                    <?php else: ?>

                        <div class="admin-project-list">

                            <?php foreach ($recentProjects as $project): ?>

                                <article class="admin-project-row">

                                    <div class="admin-project-row__visual">

                                        <?php if (!empty($project['image'])): ?>

                                            <img
                                                src="/admin/uploads/creation/<?= htmlspecialchars(
                                                    $project['image'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                alt=""
                                                loading="lazy"
                                            >

                                        <?php endif; ?>

                                    </div>


                                    <div class="admin-project-row__content">

                                        <div>

                                            <span class="admin-project-row__category">
                                                <?= htmlspecialchars(
                                                    $project['categorie'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                            <h3>
                                                <?= htmlspecialchars(
                                                    $project['titre'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h3>

                                        </div>


                                        <?php if ((int) $project['featured'] === 1): ?>

                                            <span class="admin-badge">
                                                Mis en avant
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <div class="admin-project-row__actions">

                                        <a
                                            href="/portfolio-details.php?id=<?= (int) $project['id'] ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="Voir <?= htmlspecialchars(
                                                $project['titre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >
                                            ↗
                                        </a>

                                        <a
                                            href="/admin/portfolio-edit.php?id=<?= (int) $project['id'] ?>"
                                        >
                                            Modifier
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
                        href="/admin/portfolio-add.php"
                        class="admin-quick-action"
                    >
                        <span>Portfolio</span>

                        <strong>
                            Ajouter un projet
                        </strong>

                        <span aria-hidden="true">↗</span>
                    </a>


                    <a
                        href="/admin/avis-add.php"
                        class="admin-quick-action"
                    >
                        <span>Avis clients</span>

                        <strong>
                            Ajouter un avis
                        </strong>

                        <span aria-hidden="true">↗</span>
                    </a>

                </section>

            </div>

        </main>

    </div>


    <?php
    include __DIR__ . '/partials/footer.php';
    ?>

</body>

</html>