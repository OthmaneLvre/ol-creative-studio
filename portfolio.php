<?php
$pageTitle = "Portfolio – Créateur de sites web & identités visuelles";
$pageDescription = "Découvrez mes créations : sites vitrines, boutiques en ligne, maquettes Figma, applications web & mobiles et identités visuelles. Travaux professionnels réalisés pour entreprises et indépendants.";
include_once __DIR__ . '/partials/header.php';
require_once __DIR__ . '/php/db.php';

?>

<main class="page-content">

    <!-- =========================== PORTFOLIO HERO =========================== -->

    <section class="portfolio-hero">

        <div class="container portfolio-hero__container">

            <div class="portfolio-hero__main reveal">

                <span class="portfolio-hero__eyebrow">
                    Portfolio
                </span>

                <h1 class="portfolio-hero__title">
                    Des projets pensés
                    <em>pour être vus, utilisés</em>
                    et retenus.
                </h1>

            </div>


            <div class="portfolio-hero__side reveal reveal--delay-100">

                <p class="portfolio-hero__intro">
                    Une sélection de projets web et graphiques conçus sur mesure,
                    avec une attention particulière portée au design,
                    à l’expérience et à la performance.
                </p>

                <div class="portfolio-hero__promise">

                    <span class="portfolio-hero__promise-label">
                        Une approche complète
                    </span>

                    <p>
                        Identité, interface, développement et expérience :
                        chaque projet est pensé comme un ensemble cohérent.
                    </p>

                </div>

            </div>

        </div>


        <div class="container portfolio-hero__footer">

            <span>
                01 · Projets
            </span>

            <a href="#portfolio-projects">
                Découvrir les réalisations
                <span aria-hidden="true">↓</span>
            </a>

        </div>

    </section>

    <!-- =========================== PORTFOLIO PROJECTS =========================== -->

    <?php

    $sql = "
        SELECT *
        FROM portfolio
        WHERE statut = 'published'
        ORDER BY
            ordre ASC,
            date_creation DESC,
            id DESC
    ";

    $stmt = $pdo->query($sql);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

$categoryLabels = [
    'figma'       => 'Maquettes Figma',
    'vitrine'     => 'Sites vitrines',
    'ecommerce'   => 'Boutiques en ligne',
    'application' => 'Applications web',
    'identite'    => 'Identités visuelles',
    'landing'     => 'Landing pages',
    'refonte'     => 'Refontes de sites',
    'seo'         => 'SEO & optimisation',
    'maintenance' => 'Maintenance & évolutions',
    'branding'    => 'Branding & direction artistique',
    'print'       => 'Supports print',
    'autre'       => 'Autres projets',
];

    $existingCategories = [];

    foreach ($projects as $project) {

        $category =
            (string) (
                $project['categorie']
                ?? ''
            );

        if (
            $category !== '' &&
            !in_array($category, $existingCategories, true)
        ) {
            $existingCategories[] = $category;
        }
    }

    ?>

    <section
        class="portfolio-projects"
        id="portfolio-projects"
    >

        <div class="container">

            <div class="portfolio-projects__header">

                <div class="reveal">

                    <span class="portfolio-projects__eyebrow">
                        02 · Réalisations
                    </span>

                    <h2 class="portfolio-projects__title">
                        Quelques projets
                        <em>qui ont pris vie.</em>
                    </h2>

                </div>

                <div class="portfolio-projects__side reveal reveal--delay-100">

                    <p>
                        Sites vitrines, e-commerce, interfaces et identités :
                        chaque réalisation répond à un univers, un besoin
                        et des objectifs différents.
                    </p>

                    <?php if (!empty($existingCategories)): ?>

                        <div
                            class="portfolio-projects__filters"
                            data-portfolio-filters
                        >

                            <button
                                type="button"
                                class="portfolio-filter is-active"
                                data-category="all"
                            >
                                Tous
                            </button>

                            <?php foreach ($existingCategories as $category): ?>

                                <button
                                    type="button"
                                    class="portfolio-filter"
                                    data-category="<?= htmlspecialchars($category); ?>"
                                >
                                    <?= htmlspecialchars(
                                        $categoryLabels[$category]
                                        ?? ucfirst($category)
                                    ); ?>
                                </button>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>

            </div>


            <?php if (!empty($projects)): ?>

                <div
                    class="portfolio-projects__grid"
                    data-portfolio-grid
                >

                    <?php foreach ($projects as $index => $project): ?>

                        <?php

                        $projectNumber = str_pad(
                            (string) ($index + 1),
                            2,
                            '0',
                            STR_PAD_LEFT
                        );

                        $category =
                            (string) (
                                $project['categorie']
                                ?? ''
                            );

                        $categoryLabel =
                            $categoryLabels[$category]
                            ?? ucfirst($category);

                        $technologies = json_decode(
                            $project['technologies'] ?? '[]',
                            true
                        );

                        if (!is_array($technologies)) {
                            $technologies = [];
                        }

                        ?>

                        <article
                            class="
                                portfolio-project
                                <?= $index === 0
                                    ? 'portfolio-project--featured'
                                    : ''; ?>
                                reveal
                            "
                            data-project
                            data-category="<?= htmlspecialchars($category); ?>"
                        >

                            <a
                                href="/portfolio-details.php?id=<?= (int) $project['id']; ?>"
                                class="portfolio-project__link"
                                aria-label="Découvrir le projet <?= htmlspecialchars($project['titre']); ?>"
                            >

                                <div class="portfolio-project__media">

                                    <img
                                        src="/admin/uploads/creation/<?= htmlspecialchars($project['image']); ?>"
                                        alt="<?= htmlspecialchars($project['titre']); ?>"
                                        loading="<?= $index === 0 ? 'eager' : 'lazy'; ?>"
                                    >

                                    <div
                                        class="portfolio-project__arrow"
                                        aria-hidden="true"
                                    >
                                        ↗
                                    </div>

                                </div>


                                <div class="portfolio-project__content">

                                    <div class="portfolio-project__meta">

                                        <span>
                                            <?= $projectNumber; ?>
                                        </span>

                                        <span>
                                            <?= htmlspecialchars($categoryLabel); ?>
                                        </span>

                                    </div>

                                    <h3>
                                        <?= htmlspecialchars($project['titre']); ?>
                                    </h3>


                                    <?php if (!empty($technologies)): ?>

                                        <div class="portfolio-project__stack">

                                            <?php foreach ($technologies as $technology): ?>

                                                <span>
                                                    <?= htmlspecialchars($technology); ?>
                                                </span>

                                            <?php endforeach; ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <p class="portfolio-projects__empty">
                    Les prochaines réalisations arrivent bientôt.
                </p>

            <?php endif; ?>

        </div>

    </section>

</main>

<script
    type="module"
    src="/assets/js/pages/portfolio.js"
></script>

<?php
include_once __DIR__ . '/partials/footer.php';
?>