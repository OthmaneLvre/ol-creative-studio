<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Récupération des avis
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    'SELECT
        id,
        nom,
        categorie,
        commentaire,
        avatar
     FROM avis
     ORDER BY id DESC'
);

$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalReviews = count($reviews);


/*
|--------------------------------------------------------------------------
| Configuration de la page
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Avis clients';
$adminActivePage = 'avis';

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
        Avis clients — Administration OL Creative Studio
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
                     MESSAGES
                     ===================================================== -->

                <?php if (isset($_GET['created'])): ?>

                    <div class="admin-alert admin-alert--success">
                        L’avis client a bien été ajouté.
                    </div>

                <?php elseif (isset($_GET['updated'])): ?>

                    <div class="admin-alert admin-alert--success">
                        L’avis client a bien été modifié.
                    </div>

                <?php elseif (isset($_GET['deleted'])): ?>

                    <div class="admin-alert admin-alert--success">
                        L’avis client a bien été supprimé.
                    </div>

                <?php elseif (isset($_GET['error'])): ?>

                    <div class="admin-alert admin-alert--error">
                        Une erreur est survenue. Veuillez réessayer.
                    </div>

                <?php endif; ?>


                <!-- =====================================================
                     PAGE HEADER
                     ===================================================== -->

                <section class="admin-page-heading">

                    <div class="admin-page-heading__content">

                        <span class="admin-eyebrow">
                            Témoignages
                        </span>

                        <h1>
                            Avis clients.
                        </h1>

                        <p>
                            Gérez les témoignages affichés sur
                            OL Creative Studio.
                        </p>

                    </div>


                    <a
                        href="/admin/avis-add.php"
                        class="admin-button admin-button--primary"
                    >
                        Nouvel avis
                        <span aria-hidden="true">+</span>
                    </a>

                </section>


                <!-- =====================================================
                     TOOLBAR
                     ===================================================== -->

                <section class="admin-portfolio-toolbar">

                    <div class="admin-portfolio-toolbar__count">

                        <strong>
                            <?= $totalReviews ?>
                        </strong>

                        <span>
                            <?= $totalReviews > 1
                                ? 'avis clients'
                                : 'avis client'
                            ?>
                        </span>

                    </div>

                </section>


                <!-- =====================================================
                     AVIS
                     ===================================================== -->

                <section class="admin-reviews-panel">

                    <?php if (empty($reviews)): ?>

                        <div class="admin-empty-state">

                            <span class="admin-eyebrow">
                                Témoignages
                            </span>

                            <h2>
                                Aucun avis pour le moment.
                            </h2>

                            <p>
                                Ajoutez votre premier témoignage client.
                            </p>

                            <a
                                href="/admin/avis-add.php"
                                class="admin-button admin-button--primary"
                            >
                                Ajouter un avis
                            </a>

                        </div>

                    <?php else: ?>


                        <div class="admin-reviews-grid">

                            <?php foreach ($reviews as $review): ?>

                                <article class="admin-review-card">


                                    <!-- =========================
                                         HEADER
                                         ========================= -->

                                    <div class="admin-review-card__header">

                                        <div class="admin-review-card__avatar">

                                            <?php if (!empty($review['avatar'])): ?>

                                                <img
                                                    src="/admin/uploads/avatars/<?= htmlspecialchars(
                                                        $review['avatar'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                    alt=""
                                                    loading="lazy"
                                                >

                                            <?php else: ?>

                                                <span aria-hidden="true">
                                                    <?= htmlspecialchars(
                                                        strtoupper(
                                                            mb_substr(
                                                                $review['nom'],
                                                                0,
                                                                1
                                                            )
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>
                                                </span>

                                            <?php endif; ?>

                                        </div>


                                        <div class="admin-review-card__identity">

                                            <h2>
                                                <?= htmlspecialchars(
                                                    $review['nom'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </h2>

                                            <span>
                                                <?= htmlspecialchars(
                                                    $review['categorie'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>
                                            </span>

                                        </div>

                                    </div>


                                    <!-- =========================
                                         COMMENTAIRE
                                         ========================= -->

                                    <blockquote class="admin-review-card__quote">

                                        <p>
                                            “<?= nl2br(
                                                htmlspecialchars(
                                                    $review['commentaire'],
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                )
                                            ) ?>”
                                        </p>

                                    </blockquote>


                                    <!-- =========================
                                         ACTIONS
                                         ========================= -->

                                    <div class="admin-review-card__actions">

                                        <a
                                            href="/admin/avis-edit.php?id=<?= (int) $review['id'] ?>"
                                            class="admin-action-link"
                                        >
                                            Modifier
                                        </a>


                                        <form
                                            method="POST"
                                            action="/admin/avis-delete.php"
                                            class="admin-delete-form"
                                            data-review-name="<?= htmlspecialchars(
                                                $review['nom'],
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
                                                value="<?= (int) $review['id'] ?>"
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