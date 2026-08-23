<?php

declare(strict_types=1);

require_once __DIR__ . '/php/db.php';


/*
|--------------------------------------------------------------------------
| Identification du projet
|--------------------------------------------------------------------------
*/

$slug = trim(
    (string) ($_GET['slug'] ?? '')
);

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Recherche par slug
|--------------------------------------------------------------------------
*/

if ($slug !== '') {

    $stmt = $pdo->prepare(
        "SELECT *
         FROM portfolio
         WHERE slug = :slug
         AND statut = 'published'
         LIMIT 1"
    );

    $stmt->execute([
        ':slug' => $slug,
    ]);

} elseif (
    $id !== false &&
    $id !== null &&
    $id > 0
) {

    /*
    |--------------------------------------------------------------------------
    | Fallback par ID
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        "SELECT *
         FROM portfolio
         WHERE id = :id
         AND statut = 'published'
         LIMIT 1"
    );

    $stmt->execute([
        ':id' => $id,
    ]);

} else {

    header(
        'Location: /portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Projet
|--------------------------------------------------------------------------
*/

$project = $stmt->fetch(
    PDO::FETCH_ASSOC
);

if (!$project) {

    header(
        'Location: /portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Canonicalisation des anciennes URLs par ID
|--------------------------------------------------------------------------
*/

if (
    $slug === '' &&
    !empty($project['slug'])
) {

    header(
        'Location: /portfolio-details.php?slug='
        . rawurlencode(
            (string) $project['slug']
        ),
        true,
        301
    );

    exit;
}

$categories = [
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

$projectTypes = [
    'client'  => 'Projet client',
    'concept' => 'Projet conceptuel',
];

/*
|--------------------------------------------------------------------------
| ID interne du projet
|--------------------------------------------------------------------------
*/

$id = (int) $project['id'];

$categoryKey =
    (string) ($project['categorie'] ?? '');

$categoryLabel =
    $categories[$categoryKey]
    ?? 'Projet';

$projectTypeKey =
    (string) (
        $project['project_type']
        ?? 'client'
    );

$projectTypeLabel =
    $projectTypes[$projectTypeKey]
    ?? 'Projet';

$isConceptProject =
    $projectTypeKey === 'concept';

$isLogo =
    $categoryKey === 'identite';

$gallery = [];

if (!empty($project['images_gallery'])) {
    $decodedGallery = json_decode($project['images_gallery'], true);

    if (is_array($decodedGallery)) {
        $gallery = $decodedGallery;
    }
}

$technologies = [];

if (!empty($project['technologies'])) {
    $decodedTechnologies = json_decode($project['technologies'], true);

    if (is_array($decodedTechnologies)) {
        $technologies = $decodedTechnologies;
    }
}

$services = [];

if (!empty($project['services'])) {
    $decodedServices = json_decode($project['services'], true);

    if (is_array($decodedServices)) {
        $services = $decodedServices;
    }
}

/*
|--------------------------------------------------------------------------
| Projet suivant
|--------------------------------------------------------------------------
*/
$nextStmt = $pdo->prepare(
    "SELECT id, slug, titre, categorie, image
     FROM portfolio
     WHERE ordre > :ordre
     AND statut = 'published'
     ORDER BY ordre ASC
     LIMIT 1"
);

$nextStmt->execute([
    ':ordre' => (int) $project['ordre'],
]);

$nextProject = $nextStmt->fetch(PDO::FETCH_ASSOC);

if (!$nextProject) {
    $nextStmt = $pdo->prepare(
        "SELECT id, slug, titre, categorie, image
        FROM portfolio
        WHERE id != :id
        AND statut = 'published'
        ORDER BY ordre ASC
        LIMIT 1"
    );

    $nextStmt->execute([
        ':id' => $id,
    ]);

    $nextProject = $nextStmt->fetch(PDO::FETCH_ASSOC);
}

$nextProjectUrl = null;

if ($nextProject) {

    $nextProjectUrl =
        !empty($nextProject['slug'])
            ? '/portfolio-details.php?slug='
                . rawurlencode(
                    (string) $nextProject['slug']
                )
            : '/portfolio-details.php?id='
                . (int) $nextProject['id'];
}
/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

$pageTitle = !empty($project['titre'])
    ? $project['titre'] . ' — Portfolio | OL Creative Studio'
    : 'Projet — Portfolio | OL Creative Studio';

$pageDescription = !empty($project['meta_description'])
    ? $project['meta_description']
    : 'Découvrez ce projet réalisé par OL Creative Studio.';

include_once __DIR__ . '/partials/header.php';

?>

<section class="project-hero">

    <div class="container project-hero__inner">

        <a
            href="/portfolio.php"
            class="project-hero__back reveal"
        >
            ← Retour au portfolio
        </a>

        <div class="project-hero__top">

            <div class="project-hero__heading">

                <p class="project-hero__eyebrow reveal">

                    <?= htmlspecialchars(
                        $categoryLabel,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                    <?php if ($isConceptProject): ?>

                        <span aria-hidden="true">•</span>

                        <?= htmlspecialchars(
                            $projectTypeLabel,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php endif; ?>

                    <?php if (!empty($project['annee'])): ?>

                        <span aria-hidden="true">•</span>

                        <?= htmlspecialchars(
                            (string) $project['annee'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php endif; ?>

                </p>

                <h1 class="project-hero__title reveal reveal--large">
                    <?= htmlspecialchars($project['titre']) ?>
                </h1>

            </div>

            <?php if (!empty($project['role_projet'])): ?>
                <div class="project-hero__role reveal reveal--right">

                    <span class="project-hero__role-label">
                        Rôle
                    </span>

                    <p>
                        <?= htmlspecialchars($project['role_projet']) ?>
                    </p>

                </div>
            <?php endif; ?>

        </div>

        <div class="project-hero__bottom">

            <?php if (!empty($project['description'])): ?>
                <p class="project-hero__description reveal">
                    <?= nl2br(htmlspecialchars($project['description'])) ?>
                </p>
            <?php endif; ?>

            <?php if (!empty($project['url_demo'])): ?>
                <a
                    href="<?= htmlspecialchars($project['url_demo']) ?>"
                    class="button button--primary button--lg project-hero__link reveal"
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    Voir le site
                    <span aria-hidden="true">↗</span>
                </a>
            <?php endif; ?>

        </div>

    </div>

</section>


<section class="project-cover">

    <div class="container">

        <figure class="project-cover__frame reveal reveal--large">

            <img
                src="/admin/uploads/creation/<?= htmlspecialchars($project['image']) ?>"
                alt="Aperçu du projet <?= htmlspecialchars($project['titre']) ?>"
                width="1600"
                height="1000"
                loading="eager"
                fetchpriority="high"
                decoding="async"
                <?= $isLogo ? 'class="logo-protection"' : '' ?>
            >

        </figure>

    </div>

</section>


<section class="project-overview">

    <div class="container project-overview__grid">

        <div class="project-overview__intro reveal">

            <p class="section-eyebrow">
                Le projet
            </p>

            <h2>
                Une réalisation pensée
                <em>dans les détails.</em>
            </h2>

        </div>

        <div class="project-overview__meta">

            <?php if (!empty($project['client'])): ?>
                <div class="project-meta-item reveal">
                    <span>Client</span>
                    <p><?= htmlspecialchars($project['client']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($project['annee'])): ?>
                <div class="project-meta-item reveal">
                    <span>Année</span>
                    <p><?= htmlspecialchars((string) $project['annee']) ?></p>
                </div>
            <?php endif; ?>

            <?php if (!empty($services)): ?>
                <div class="project-meta-item reveal">

                    <span>Services</span>

                    <div class="project-meta-tags">
                        <?php foreach ($services as $service): ?>
                            <span>
                                <?= htmlspecialchars((string) $service) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endif; ?>

            <?php if (!empty($technologies)): ?>
                <div class="project-meta-item reveal">

                    <span>Technologies</span>

                    <div class="project-meta-tags">
                        <?php foreach ($technologies as $technology): ?>
                            <span>
                                <?= htmlspecialchars((string) $technology) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>

                </div>
            <?php endif; ?>

        </div>

    </div>

</section>


<section class="project-case-study">

    <div class="container">

        <div class="project-case-study__header reveal">

            <p class="section-eyebrow">
                Case study
            </p>

            <h2>
                Comprendre.
                Concevoir.
                <em>Construire.</em>
            </h2>

        </div>

        <div class="project-case-study__grid">

            <?php if (!empty($project['contexte'])): ?>
                <article class="project-case-card reveal">

                    <span class="project-case-card__number">
                        01
                    </span>

                    <div class="project-case-card__content">

                        <h3>Contexte</h3>

                        <p>
                            <?= nl2br(htmlspecialchars($project['contexte'])) ?>
                        </p>

                    </div>

                </article>
            <?php endif; ?>

            <?php if (!empty($project['objectif'])): ?>
                <article class="project-case-card reveal">

                    <span class="project-case-card__number">
                        02
                    </span>

                    <div class="project-case-card__content">

                        <h3>Objectif</h3>

                        <p>
                            <?= nl2br(htmlspecialchars($project['objectif'])) ?>
                        </p>

                    </div>

                </article>
            <?php endif; ?>

            <?php if (!empty($project['solution'])): ?>
                <article class="project-case-card reveal">

                    <span class="project-case-card__number">
                        03
                    </span>

                    <div class="project-case-card__content">

                        <h3>Solution</h3>

                        <p>
                            <?= nl2br(htmlspecialchars($project['solution'])) ?>
                        </p>

                    </div>

                </article>
            <?php endif; ?>

            <?php if (!empty($project['resultats'])): ?>
                <article class="project-case-card reveal">

                    <span class="project-case-card__number">
                        04
                    </span>

                    <div class="project-case-card__content">

                        <h3>Résultats</h3>

                        <p>
                            <?= nl2br(htmlspecialchars($project['resultats'])) ?>
                        </p>

                    </div>

                </article>
            <?php endif; ?>

        </div>

    </div>

</section>


<?php if (!empty($gallery)): ?>
<section class="project-gallery">

    <div class="container">

        <div class="project-gallery__header reveal">

            <p class="section-eyebrow">
                Galerie
            </p>

            <h2>
                Le projet
                <em>en images.</em>
            </h2>

        </div>

        <div class="project-gallery__grid">

            <?php foreach ($gallery as $index => $image): ?>

                <figure
                    class="project-gallery__item reveal <?= $index % 3 === 0 ? 'project-gallery__item--large' : '' ?>"
                >

                    <img
                        src="/admin/uploads/creation/<?= htmlspecialchars((string) $image) ?>"
                        alt="Vue <?= $index + 1 ?> du projet <?= htmlspecialchars($project['titre']) ?>"
                        loading="lazy"
                        width="1400"
                        height="900"
                    >

                </figure>

            <?php endforeach; ?>

        </div>

    </div>

</section>
<?php endif; ?>


<?php if ($nextProject): ?>

<section class="project-next">

    <div class="container">

        <a
            href="<?= htmlspecialchars(
                $nextProjectUrl,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            class="project-next__link reveal"
        >

            <div class="project-next__content">

                <span class="project-next__label">
                    Projet suivant
                </span>

                <h2>
                    <?= htmlspecialchars($nextProject['titre']) ?>
                </h2>

                <span class="project-next__arrow" aria-hidden="true">
                    ↗
                </span>

            </div>

            <?php if (!empty($nextProject['image'])): ?>
                <div class="project-next__visual">

                    <img
                        src="/admin/uploads/creation/<?= htmlspecialchars($nextProject['image']) ?>"
                        alt=""
                        loading="lazy"
                        width="1200"
                        height="800"
                    >

                </div>
            <?php endif; ?>

        </a>

    </div>

</section>

<?php endif; ?>


<section class="project-cta">

    <div class="container">

        <div class="project-cta__inner reveal">

            <div class="project-cta__content">

                <span class="section-eyebrow">
                    Votre projet
                </span>

                <h2>
                    Vous avez une idée ?
                    <em>Faisons-la exister.</em>
                </h2>

            </div>

            <a
                href="/contact.php"
                class="button button--primary button--lg"
            >
                Parler de votre projet
            </a>

        </div>

    </div>

</section>

<?php
$hideFooterCta = true;

include_once __DIR__ . '/partials/footer.php';
?>

