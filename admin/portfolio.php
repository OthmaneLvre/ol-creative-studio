<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Catégories
|--------------------------------------------------------------------------
*/

$categories = [
    'figma'     => 'Maquettes Figma',
    'vitrine'   => 'Sites vitrines',
    'ecommerce' => 'Boutiques en ligne',
    'app'       => 'Applications',
    'logo'      => 'Logos & identités visuelles',
];


/*
|--------------------------------------------------------------------------
| Récupération des projets
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    'SELECT
        id,
        titre,
        slug,
        client,
        annee,
        categorie,
        image,
        featured,
        ordre,
        date_creation
     FROM portfolio
     ORDER BY
        ordre ASC,
        date_creation DESC'
);

$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalProjects = count($projects);


/*
|--------------------------------------------------------------------------
| Configuration de la page
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Portfolio';
$adminActivePage = 'portfolio';

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
        Portfolio — Administration OL Creative Studio
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
        include_once __DIR__ . '/partials/sidebar.php';
        ?>


        <main class="admin-main">

            <?php
            include_once __DIR__ . '/partials/header.php';
            ?>


            <div class="admin-content">

                <!-- =====================================================
                    FLASH MESSAGE
                    ===================================================== -->

                <?php $flash = getFlash(); ?>

                <?php if ($flash !== null): ?>

                    <div
                        class="admin-alert admin-alert--<?= htmlspecialchars(
                            $flash['type'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        role="<?= $flash['type'] === 'error'
                            ? 'alert'
                            : 'status'
                        ?>"
                    >
                        <?= htmlspecialchars(
                            $flash['message'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </div>

                <?php endif; ?>

                <!-- =====================================================
                     PAGE HEADER
                     ===================================================== -->

                <section class="admin-page-heading">

                    <div class="admin-page-heading__content">

                        <span class="admin-eyebrow">
                            Portfolio
                        </span>

                        <h1>
                            Vos réalisations.
                        </h1>

                        <p>
                            Gérez les projets présentés sur
                            OL Creative Studio.
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

                <!-- =====================================================
                     TOOLBAR
                     ===================================================== -->

                <section class="admin-portfolio-toolbar">

                    <div class="admin-portfolio-toolbar__count">

                        <strong>
                            <?= $totalProjects ?>
                        </strong>

                        <span>
                            <?= $totalProjects > 1
                                ? 'projets'
                                : 'projet'
                            ?>
                        </span>

                    </div>


                    <div class="admin-portfolio-toolbar__legend">

                        <span>
                            <span
                                class="admin-status-dot admin-status-dot--featured"
                                aria-hidden="true"
                            ></span>

                            Mis en avant
                        </span>

                    </div>

                </section>


                <!-- =====================================================
                     PROJECTS
                     ===================================================== -->

                <section class="admin-portfolio-panel">

                    <?php if (empty($projects)): ?>

                        <div class="admin-empty-state">

                            <span class="admin-eyebrow">
                                Portfolio
                            </span>

                            <h2>
                                Aucun projet pour le moment.
                            </h2>

                            <p>
                                Ajoutez votre première réalisation
                                au portfolio.
                            </p>

                            <a
                                href="/admin/portfolio-add.php"
                                class="admin-button admin-button--primary"
                            >
                                Ajouter un projet
                            </a>

                        </div>


                    <?php else: ?>


                        <!-- =========================
                             TABLE HEADER
                             ========================= -->

                        <div
                            class="admin-portfolio-table__header"
                            aria-hidden="true"
                        >

                            <span>Projet</span>

                            <span>Catégorie</span>

                            <span>Année</span>

                            <span>Ordre</span>

                            <span>Statut</span>

                            <span>Actions</span>

                        </div>


                        <!-- =========================
                             PROJECT ROWS
                             ========================= -->

                        <div class="admin-portfolio-table">

                            <?php foreach ($projects as $project): ?>

                                <?php

                                $categoryKey =
                                    (string) ($project['categorie'] ?? '');

                                $categoryLabel =
                                    $categories[$categoryKey]
                                    ?? ucfirst($categoryKey);

                                $isFeatured =
                                    (int) ($project['featured'] ?? 0) === 1;

                                ?>


                                <article class="admin-portfolio-item">


                                    <!-- PROJECT -->

                                    <div class="admin-portfolio-item__project">

                                        <div class="admin-portfolio-item__image">

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

                                            <?php else: ?>

                                                <span
                                                    class="admin-portfolio-item__placeholder"
                                                    aria-hidden="true"
                                                >
                                                    —
                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <div class="admin-portfolio-item__identity">

                                            <h2>
                                                <?= htmlspecialchars(
                                                    $project['titre'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h2>


                                            <?php if (!empty($project['client'])): ?>

                                                <span>
                                                    <?= htmlspecialchars(
                                                        $project['client'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>

                                    </div>


                                    <!-- CATEGORY -->

                                    <div
                                        class="admin-portfolio-item__data"
                                        data-label="Catégorie"
                                    >

                                        <?= htmlspecialchars(
                                            $categoryLabel,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>


                                    <!-- YEAR -->

                                    <div
                                        class="admin-portfolio-item__data"
                                        data-label="Année"
                                    >

                                        <?= !empty($project['annee'])
                                            ? htmlspecialchars(
                                                (string) $project['annee'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            )
                                            : '—'
                                        ?>

                                    </div>


                                    <!-- ORDER -->

                                    <div
                                        class="admin-portfolio-item__data"
                                        data-label="Ordre"
                                    >

                                        <?= (int) ($project['ordre'] ?? 0) ?>

                                    </div>


                                    <!-- STATUS -->

                                    <div
                                        class="admin-portfolio-item__status"
                                        data-label="Statut"
                                    >

                                        <?php if ($isFeatured): ?>

                                            <span class="admin-badge">
                                                Mis en avant
                                            </span>

                                        <?php else: ?>

                                            <span class="admin-badge admin-badge--neutral">
                                                Standard
                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <!-- ACTIONS -->

                                    <div class="admin-portfolio-item__actions">

                                        <a
                                            href="/portfolio-details.php?id=<?= (int) $project['id'] ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="admin-icon-action"
                                            title="Voir le projet"
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
                                            class="admin-action-link"
                                        >
                                            Modifier
                                        </a>


                                        <form
                                            method="POST"
                                            action="/admin/portfolio-delete.php"
                                            class="admin-delete-form"
                                            data-project-title="<?= htmlspecialchars(
                                                $project['titre'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                        >

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
                                                name="id"
                                                value="<?= (int) $project['id'] ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="admin-action-link admin-action-link--danger"
                                            >
                                                Supprimer
                                            </button>

                                        </form>

                                    </div>

                                </article>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </section>

            </div>

        </main>

    </div>


    <?php
    include_once __DIR__ . '/partials/footer.php';
    ?>

</body>

</html>
