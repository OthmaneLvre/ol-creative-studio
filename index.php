<?php

declare(strict_types=1);

require_once __DIR__ . '/php/db.php';


/*
|--------------------------------------------------------------------------
| Projet mis en avant
|--------------------------------------------------------------------------
*/

$featuredStmt = $pdo->query(
    'SELECT *
     FROM portfolio
     WHERE featured = 1
     ORDER BY ordre ASC, date_creation DESC
     LIMIT 1'
);

$featuredProject = $featuredStmt->fetch(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| SEO
|--------------------------------------------------------------------------
*/

$pageTitle = 'Développeur Web Freelance à Céret';

$pageDescription =
    'Développeur web freelance à Céret dans les Pyrénées-Orientales. '
    . 'Création de sites vitrines, boutiques en ligne, référencement '
    . 'naturel SEO et identité visuelle pour artisans, indépendants '
    . 'et associations.';

include_once __DIR__ . '/partials/header.php';

?>

<main class="page-content">
    <!-- =========================== HERO SECTION =========================== -->

    <section class="home-hero">

        <div class="home-hero__background" aria-hidden="true">
            <span class="home-hero__orb home-hero__orb--one"></span>
            <span class="home-hero__orb home-hero__orb--two"></span>
            <span class="home-hero__grid"></span>
        </div>

        <div class="container home-hero__container">

            <div class="home-hero__content">

                <span class="home-hero__eyebrow">
                    Studio créatif · Céret
                </span>

                <h1 class="home-hero__title">
                    Des expériences digitales
                    <em>qui marquent.</em>
                </h1>

                <p class="home-hero__description">
                    J'imagine et développe des sites web sur mesure,
                    des boutiques en ligne et des identités visuelles
                    pensées pour donner du caractère à votre marque.
                </p>

                <div class="home-hero__actions">

                    <a
                        href="/contact.php"
                        class="button button--primary button--lg"
                    >
                        <span>Parler de votre projet</span>
                    </a>

                    <a
                        href="/portfolio.php"
                        class="home-hero__portfolio-link"
                    >
                        Voir les réalisations

                        <span aria-hidden="true"></span>
                    </a>

                </div>

            </div>

            <div class="home-hero__visual" aria-hidden="true">

                <div class="home-hero__visual-card">

                    <div class="home-hero__visual-top">

                        <span> OL / Creative Studio</span>

                        <span>2026</span>

                    </div>

                    <div class="home-hero__visual-center">

                        <span class="home-hero__visual-small">
                            Web
                        </span>

                        <strong>
                            Design
                            <em>&</em>
                            Code
                        </strong>

                        <span class="home-hero__visual-small home-hero__visual-small--right">
                            Identity
                        </span>

                    </div>

                    <div class="home-hero__visual-bottom">

                        <span>
                            Sites web
                        </span>

                        <span>
                            E-commerce
                        </span>

                        <span>
                            Branding
                        </span>

                    </div>

                </div>

            </div>

        </div>

        <div class="container home-hero__footer">

            <span>
                Développeur web freelance
            </span>

            <a href="#home-services">
                Découvrir le Studio
                <span aria-hidden="true">↓</span>
            </a>

        </div>

    </section>

        <!-- =========================== SERVICES SECTION =========================== -->

    <section class="home-services" id="home-services">

        <div class="container">

            <div class="home-services__header">

                <div class="home-services-heading">

                    <span class="homes-services__eyebrow">
                        Ce que je fais
                    </span>

                    <h2 class="home-services__title">
                        Des solutions digitales
                        <em>pensées pour votre image.</em>
                    </h2>

                </div>

                <p class="home-services__intro">
                    De la conception à la mise en ligne, je crée des expériences
                    digitales sur mesure, pensées pour être belles, rapides
                    et efficaces.
                </p>

            </div>

            <div class="home-services__list">

                <!-- SERVICE 01 -->
                <article class="home-service">

                    <div class="home-service__number">
                        01
                    </div>

                    <div class="home-service__main">

                        <h3>
                            Sites web
                        </h3>

                        <p>
                            Sites vitrines modernes, rapides et responsives,
                            conçus sur mesure pour présenter votre activité
                            avec une identité forte.
                        </p>

                    </div>

                    <div class="home-service__meta">

                        <span>
                            UX / UI
                        </span>

                        <span>
                            Développement
                        </span>

                        <span>
                            Responsive
                        </span>

                    </div>

                    <a
                        href="/services.php"
                        class="home-service__link"
                        aria-label="Découvrir le service création de sites web"
                    >
                        <span aria-hidden="true">↗</span>
                    </a>

                </article>


                <!-- SERVICE 02 -->
                <article class="home-service">

                    <div class="home-service__number">
                        02
                    </div>

                    <div class="home-service__main">

                        <h3>
                            E-commerce
                        </h3>

                        <p>
                            Boutiques en ligne performantes et pensées pour
                            convertir, avec paiement sécurisé, gestion des
                            produits et parcours client optimisé.
                        </p>

                    </div>

                    <div class="home-service__meta">

                        <span>
                            Stripe
                        </span>

                        <span>
                            Catalogue
                        </span>

                        <span>
                            Conversion
                        </span>

                    </div>

                    <a
                        href="/services.php"
                        class="home-service__link"
                        aria-label="Découvrir le service e-commerce"
                    >
                        <span aria-hidden="true">↗</span>
                    </a>

                </article>


                <!-- SERVICE 03 -->
                <article class="home-service">

                    <div class="home-service__number">
                        03
                    </div>

                    <div class="home-service__main">

                        <h3>
                            Identité visuelle
                        </h3>

                        <p>
                            Logos, univers graphiques et supports visuels
                            cohérents pour construire une marque identifiable,
                            professionnelle et mémorable.
                        </p>

                    </div>

                    <div class="home-service__meta">

                        <span>
                            Branding
                        </span>

                        <span>
                            Logo
                        </span>

                        <span>
                            Print & digital
                        </span>

                    </div>

                    <a
                        href="/services.php"
                        class="home-service__link"
                        aria-label="Découvrir le service identité visuelle"
                    >
                        <span aria-hidden="true">↗</span>
                    </a>

                </article>

            </div>


            <div class="home-services__footer">

                <a
                    href="/services.php"
                    class="button button--secondary"
                >
                    <span>Découvrir tous les services</span>

                    <span
                        class="button__icon"
                        aria-hidden="true"
                    >
                        ↗
                    </span>
                </a>

            </div>

        </div>

    </section>

    <!-- =========================== FEATURED PROJECT =========================== -->

    <?php if ($featuredProject): ?>

        <?php

        $featuredTechnologies = [];

        if (!empty($featuredProject['technologies'])) {

            $decodedTechnologies = json_decode(
                $featuredProject['technologies'],
                true
            );

            if (is_array($decodedTechnologies)) {
                $featuredTechnologies = $decodedTechnologies;
            }
        }


        $featuredServices = [];

        if (!empty($featuredProject['services'])) {

            $decodedServices = json_decode(
                $featuredProject['services'],
                true
            );

            if (is_array($decodedServices)) {
                $featuredServices = $decodedServices;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | URL affichée dans le faux navigateur
        |--------------------------------------------------------------------------
        */

        $featuredHost = '';

        if (!empty($featuredProject['url_demo'])) {

            $parsedHost = parse_url(
                $featuredProject['url_demo'],
                PHP_URL_HOST
            );

            if (is_string($parsedHost)) {

                $featuredHost = preg_replace(
                    '/^www\./',
                    '',
                    $parsedHost
                ) ?? $parsedHost;
            }
        }

        ?>

        <section class="home-featured-project">

            <div class="container">

                <div class="home-featured-project__header">

                    <span class="home-featured-project__eyebrow">
                        Projet sélectionné · 01
                    </span>

                    <div class="home-featured-project__heading">

                        <h2>
                            <?= htmlspecialchars(
                                $featuredProject['titre'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                            <?php if (!empty($featuredProject['role_projet'])): ?>

                                <em>
                                    <?= htmlspecialchars(
                                        $featuredProject['role_projet'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </em>

                            <?php endif; ?>
                        </h2>


                        <?php if (!empty($featuredProject['description'])): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $featuredProject['description'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>

                </div>


                <div class="home-featured-project__visual">

                    <div class="home-featured-project__browser">

                        <div class="home-featured-project__browser-bar">

                            <div class="home-featured-project__browser-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>

                            <span class="home-featured-project__browser-url">

                                <?= $featuredHost !== ''
                                    ? htmlspecialchars(
                                        $featuredHost,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : htmlspecialchars(
                                        $featuredProject['slug']
                                            ?? $featuredProject['titre'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ?>

                            </span>

                        </div>


                        <div class="home-featured-project__media">

                            <img
                                src="/admin/uploads/creation/<?= htmlspecialchars(
                                    $featuredProject['image'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                alt="Aperçu du projet <?= htmlspecialchars(
                                    $featuredProject['titre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                loading="lazy"
                                decoding="async"
                            >

                        </div>

                    </div>

                </div>


                <div class="home-featured-project__details">

                    <div class="home-featured-project__intro">

                        <span class="home-featured-project__label">
                            Le projet
                        </span>


                        <?php
                        $featuredIntro =
                            $featuredProject['solution']
                            ?: $featuredProject['contexte']
                            ?: $featuredProject['description'];
                        ?>

                        <?php if (!empty($featuredIntro)): ?>

                            <p>
                                <?= htmlspecialchars(
                                    $featuredIntro,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        <?php endif; ?>

                    </div>


                    <?php if (!empty($featuredServices)): ?>

                        <div class="home-featured-project__features">

                            <?php foreach (
                                array_slice(
                                    $featuredServices,
                                    0,
                                    4
                                )
                                as $index => $service
                            ): ?>

                                <div class="home-featured-project__feature">

                                    <span>
                                        <?= str_pad(
                                            (string) ($index + 1),
                                            2,
                                            '0',
                                            STR_PAD_LEFT
                                        ) ?>
                                    </span>

                                    <p>
                                        <?= htmlspecialchars(
                                            (string) $service,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </p>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>


                <div class="home-featured-project__footer">

                    <?php if (!empty($featuredTechnologies)): ?>

                        <div class="home-featured-project__stack">

                            <?php foreach (
                                array_slice(
                                    $featuredTechnologies,
                                    0,
                                    6
                                )
                                as $technology
                            ): ?>

                                <span>
                                    <?= htmlspecialchars(
                                        (string) $technology,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>


                    <a
                        href="/portfolio-details.php?id=<?= (int) $featuredProject['id'] ?>"
                        class="button button--primary"
                    >
                        <span>
                            Découvrir le projet
                        </span>

                        <span
                            class="button__icon"
                            aria-hidden="true"
                        >
                            ↗
                        </span>
                    </a>

                </div>

            </div>

        </section>

    <?php endif; ?>

    <!-- =========================== ABOUT / STUDIO =========================== -->

    <section class="home-about">

        <div class="container">

            <div class="home-about__header">

                <span class="home-about__eyebrow">
                    À propos
                </span>

                <p class="home-about__location">
                    Céret · Pyrénées-Orientales · France
                </p>

            </div>


            <div class="home-about__content">

                <div class="home-about__statement">

                    <h2>
                        Je conçois des expériences
                        <em>digitales utiles et mémorables.</em>
                    </h2>

                </div>

                <div class="home-about__details">

                    <p class="home-about__lead">
                        OL Creative Studio accompagne les artisans,
                        indépendants et entreprises dans la création
                        de sites web, boutiques en ligne et identités
                        visuelles pensées pour durer.
                    </p>

                    <p>
                        Mon approche mêle design, développement et stratégie
                        pour créer des solutions cohérentes, performantes
                        et adaptées à chaque activité.
                    </p>

                    <a
                        href="/contact.php"
                        class="home-about__link"
                    >
                        Travailler ensemble
                        <span aria-hidden="true">↗</span>
                    </a>

                </div>

            </div>


            <div class="home-about__meta">

                <div class="home-about__meta-item">

                    <span class="home-about__meta-number">
                        01
                    </span>

                    <span class="home-about__meta-label">
                        Sur mesure
                    </span>

                    <p>
                        Pas de modèle générique : chaque projet est pensé
                        selon votre activité et vos objectifs.
                    </p>

                </div>


                <div class="home-about__meta-item">

                    <span class="home-about__meta-number">
                        02
                    </span>

                    <span class="home-about__meta-label">
                        Design & code
                    </span>

                    <p>
                        Une approche globale qui relie identité visuelle,
                        expérience utilisateur et développement.
                    </p>

                </div>


                <div class="home-about__meta-item">

                    <span class="home-about__meta-number">
                        03
                    </span>

                    <span class="home-about__meta-label">
                        Accompagnement
                    </span>

                    <p>
                        Un interlocuteur unique de la première idée
                        jusqu’à la mise en ligne du projet.
                    </p>

                </div>

            </div>

        </div>

    </section>

    <!-- =========================== HOME PORTFOLIO =========================== -->

    <?php

    $query = $pdo->query("
        SELECT *
        FROM portfolio
        ORDER BY id DESC
        LIMIT 3
    ");

    $projects = $query->fetchAll(PDO::FETCH_ASSOC);

    $categories = [
        "figma" => "Maquette Figma",
        "vitrine" => "Site vitrine",
        "logo" => "Identité visuelle",
        "ecommerce" => "E-commerce",
        "app" => "Application web"
    ];

    ?>

    <section class="home-portfolio">

        <div class="container">

            <div class="home-portfolio__header">

                <div>

                    <span class="home-portfolio__eyebrow">
                        Projets récents
                    </span>

                    <h2 class="home-portfolio__title">
                        Quelques créations
                        <em>qui ont pris vie.</em>
                    </h2>

                </div>

                <div class="home-portfolio__header-side">

                    <p>
                        Une sélection de projets web et graphiques
                        conçus sur mesure pour répondre à des besoins,
                        des univers et des objectifs différents.
                    </p>

                    <a
                        href="/portfolio.php"
                        class="home-portfolio__all-link"
                    >
                        Voir tous les projets
                        <span aria-hidden="true">↗</span>
                    </a>

                </div>

            </div>


            <?php if (!empty($projects)): ?>

                <div class="home-portfolio__grid">

                    <?php foreach ($projects as $index => $project): ?>

                        <?php
                        $projectNumber = str_pad(
                            (string) ($index + 1),
                            2,
                            '0',
                            STR_PAD_LEFT
                        );

                        $categoryLabel =
                            $categories[$project["categorie"]]
                            ?? $project["categorie"];
                        ?>

                        <article
                            class="home-project <?= $index === 0 ? 'home-project--featured' : ''; ?>"
                        >

                            <a
                                href="/portfolio-details.php?id=<?= (int) $project["id"]; ?>"
                                class="home-project__link"
                                aria-label="Découvrir le projet <?= htmlspecialchars($project["titre"]); ?>"
                            >

                                <div class="home-project__media">

                                    <img
                                        src="/admin/uploads/creation/<?= htmlspecialchars($project["image"]); ?>"
                                        alt="<?= htmlspecialchars($project["titre"]); ?>"
                                        loading="lazy"
                                    >

                                    <div
                                        class="home-project__arrow"
                                        aria-hidden="true"
                                    >
                                        ↗
                                    </div>

                                </div>


                                <div class="home-project__content">

                                    <div class="home-project__meta">

                                        <span>
                                            <?= $projectNumber; ?>
                                        </span>

                                        <span>
                                            <?= htmlspecialchars($categoryLabel); ?>
                                        </span>

                                    </div>

                                    <h3>
                                        <?= htmlspecialchars($project["titre"]); ?>
                                    </h3>

                                </div>

                            </a>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php endif; ?>


            <div class="home-portfolio__footer">

                <span>
                    Web · E-commerce · Branding · Design
                </span>

                <a
                    href="/portfolio.php"
                    class="button button--secondary"
                >
                    <span>Explorer le portfolio</span>

                    <span
                        class="button__icon"
                        aria-hidden="true"
                    >
                        ↗
                    </span>
                </a>

            </div>

        </div>

    </section>

    <!-- =========================== TESTIMONIALS =========================== -->

    <?php

    $sql = "
        SELECT *
        FROM avis
        WHERE statut = 'validé'
        ORDER BY id DESC
    ";

    $stmt = $pdo->query($sql);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ?>

    <section class="home-testimonials">

        <div class="container">

            <div class="home-testimonials__header">

                <div>

                    <span class="home-testimonials__eyebrow">
                        Ils m'ont fait confiance
                    </span>

                    <h2 class="home-testimonials__title">
                        Des collaborations
                        <em>qui comptent.</em>
                    </h2>

                </div>

                <p class="home-testimonials__intro">
                    Derrière chaque projet, il y a surtout une collaboration,
                    des échanges et un objectif commun : créer quelque chose
                    de juste, utile et durable.
                </p>

            </div>


            <?php if (!empty($avis)): ?>

                <div
                    class="home-testimonials__slider"
                    data-testimonials
                >

                    <div class="home-testimonials__quote-mark" aria-hidden="true">
                        “
                    </div>

                    <div class="home-testimonials__content">

                        <p
                            class="home-testimonials__quote"
                            data-testimonial-text
                        >
                            <?= htmlspecialchars($avis[0]["commentaire"] ?? ""); ?>
                        </p>

                        <div class="home-testimonials__client">

                            <div>

                                <span
                                    class="home-testimonials__name"
                                    data-testimonial-name
                                >
                                    <?= htmlspecialchars($avis[0]["nom"] ?? "Client"); ?>
                                </span>

                                <span
                                    class="home-testimonials__project"
                                    data-testimonial-project
                                >
                                    <?= htmlspecialchars($avis[0]["categorie"] ?? "Projet digital"); ?>
                                </span>

                            </div>

                            <div class="home-testimonials__counter">

                                <span data-testimonial-current>
                                    01
                                </span>

                                <span>
                                    /
                                </span>

                                <span>
                                    <?= str_pad(
                                        (string) count($avis),
                                        2,
                                        '0',
                                        STR_PAD_LEFT
                                    ); ?>
                                </span>

                            </div>

                        </div>

                    </div>


                    <div class="home-testimonials__controls">

                        <button
                            type="button"
                            class="home-testimonials__button"
                            data-testimonial-prev
                            aria-label="Avis précédent"
                        >
                            ←
                        </button>

                        <button
                            type="button"
                            class="home-testimonials__button"
                            data-testimonial-next
                            aria-label="Avis suivant"
                        >
                            →
                        </button>

                    </div>

                </div>

                <script>
                    window.homeTestimonials = <?= json_encode(
                        $avis,
                        JSON_UNESCAPED_UNICODE |
                        JSON_UNESCAPED_SLASHES
                    ); ?>;
                </script>

            <?php endif; ?>

        </div>

    </section>

    <!-- =========================== FINAL CTA =========================== -->

    <section class="home-cta">

        <div class="container">

            <div class="home-cta__inner">

                <span class="home-cta__eyebrow">
                    Un projet en tête ?
                </span>

                <h2 class="home-cta__title">
                    Faisons quelque chose
                    <em>qui marque.</em>
                </h2>

                <p class="home-cta__text">
                    Site web, boutique en ligne ou identité visuelle :
                    parlons de votre projet et construisons quelque chose
                    de cohérent, utile et mémorable.
                </p>

                <div class="home-cta__actions">

                    <a
                        href="/contact.php"
                        class="button button--primary button--lg"
                    >
                        <span>Parler de votre projet</span>

                        <span
                            class="button__icon"
                            aria-hidden="true"
                        >
                            ↗
                        </span>
                    </a>

                    <a
                        href="mailto:contact@olcreativestudio.com"
                        class="home-cta__email"
                    >
                        contact@olcreativestudio.com
                    </a>

                </div>

            </div>

        </div>

    </section>

<script
    type="module"
    src="/assets/js/pages/home.js"
></script>

<?php
$hideFooterCta = true;
include_once 'partials/footer.php';
?>

