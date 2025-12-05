<?php
$pageTitle = "Services en création web & identité visuelle";
$pageDescription = "Découvrez mes services de création de sites internet, identité visuelle, graphisme et maintenance web pour entreprises et indépendants.";
include 'partials/header.php';
?>

<main class="page-content">

    <!-- =========================== HERO SECTION =========================== -->

    <section class="services-hero">
        <img src="/assets/images/illustration-hero.webp"
            alt="Illustration portfolio"
            width="1200"
            height="1200"
            class="hero-bg"
            loading="eager">
        <div class="container">
            <h1>Prestations web & design</h1>
            <p>Des solutions digitales adaptées à vos besoins — design, performance et créativité.</p>
        </div>
    </section>

    <!-- =========================== SERVICES 1 =========================== -->
    <section class="service-block">
        <div class="container block-container reverse">

            <div class="block-text">
                <div class="service-icon">
                    <img src="/assets/icons/computer.svg" loading="lazy" alt="Site vitrine moderne">
                </div>
                
                <h2>Création de site vitrine sur-mesure</h2>
                <p>Création de sites internet modernes, rapides, sécurisés, responsives et adaptés à votre activité.</p>

                <ul>
                    <li>Site responsive mobile</li>
                    <li>Design sur-mesure</li>
                    <li>Optimisation SEO</li>
                    <li>Analyse des performances</li>
                </ul>

                <a href="contact.php" class="btn-primary btn-services">Me contacter</a>
            </div>

            <div class="block-image">
                <img src="/assets/images/illustration_services1.webp"
                    loading="lazy"
                    alt="Illustration création site vitrine"
                    width="600"
                    height="600"
                >
            </div>

        </div>
    </section>

    <!-- =========================== SERVICES 2 =========================== -->

    <section class="service-block alt">
        <div class="container">
            <div class="block-container">

                <div class="block-image">
                    <img src="/assets/images/illustration_services2.webp"
                        loading="lazy"
                        alt="Illustration identité visuelle"
                        width="600"
                        height="600"
                    >
                </div>

                <div class="block-text">
                    <div class="service-icon">
                        <img src="/assets/icons/palette.svg" loading="lazy" alt="Identité visuelle">
                    </div>
                    <h2>Identité visuelle & design graphique</h2>
                    <p>
                        Création d’identités visuelles uniques, logos, chartes graphiques,
                        supports web et print adaptés à votre marque.
                    </p>

                    <ul>
                        <li>Logo sur-mesure</li>
                        <li>Palette, typographies & charte graphique</li>
                        <li>Supports print & web cohérents</li>
                        <li>Création d’illustrations personnalisées</li>
                    </ul>

                    <a href="contact.php" class="btn-primary btn-services">Me contacter</a>
                </div>
            </div>
        </div>
    </section>

    <!-- =========================== SERVICES 3 =========================== -->

    <section class="service-block">
        <div class="container block-container reverse">

            <div class="block-text">
                <div class="service-icon">
                    <img src="/assets/icons/maintenance.svg" alt="Maintenance & Optimisation">
                </div>
                <h2>Maintenance & optimisation de site web</h2>
                <p>
                    Suivi technique, mises à jour régulières, optimisation des performances,
                    sécurité et accompagnement personnalisé.
                </p>

                <ul>
                    <li>Monitoring & mises à jour</li>
                    <li>Optimisation des performances</li>
                    <li>Sécurisation du site</li>
                    <li>Support & assistance technique</li>
                </ul>

                <a href="contact.php" class="btn-primary btn-services">Me contacter</a>
            </div>

            <div class="block-image">
                <img src="/assets/images/illustration_services3.webp"
                    alt="Illustration maintenance site web"
                    loading="lazy"
                    width="600"
                    height="600"
                >
            </div>

        </div>
    </section>

    <section class="seo-text">
    <div class="container">
        <h2>Pourquoi faire appel à un freelance pour votre projet web ?</h2>
        <p>
            En tant que développeur web et graphiste freelance, j’accompagne les entrepreneurs,
            artisans, associations et indépendants dans la création de solutions digitales modernes
            et performantes. <strong>Mon objectif est simple :</strong> concevoir pour vous un site internet qui
            reflète votre identité, renforce votre crédibilité et attire davantage de clients.
        </p>

        <br>
        
        <p>
            Contrairement aux agences traditionnelles, je propose un <strong>accompagnement personnalisé</strong>,
            transparent et humain. Chaque projet est construit sur-mesure : analyse de vos besoins,
            création graphique adaptée, optimisation SEO, développement propre et évolutif,
            puis mise en ligne accompagnée. Vous avez un interlocuteur unique, disponible et
            réactif, capable de gérer à la fois le design, le développement et l’optimisation.
        </p>

        <br>
        
        <p>
            Que vous ayez besoin d’un <strong>site vitrine</strong>, d’une
            <strong>identité visuelle complète</strong> ou d’une
            <strong>refonte professionnelle</strong>, je vous propose des solutions durables,
            sécurisées et pensées pour la performance. Votre site est conçu pour être rapide,
            responsive, facile à administrer et optimisé pour un bon référencement naturel.
        </p>

        <br>

        <p>
            Basé dans les <strong>Pyrénées-Orientales</strong>, j’accompagne des clients partout en France :
            commerçants, auto-entrepreneurs, professions libérales, associations, startups
            et petites entreprises souhaitant améliorer leur présence en ligne.
            Si vous souhaitez lancer un projet ou simplement échanger sur vos besoins,
            je serai ravi de vous conseiller.
        </p>
    </div>
</section>

</main>

<?php include 'partials/footer.php'; ?>
