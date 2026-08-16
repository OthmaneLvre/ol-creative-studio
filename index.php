<?php
$pageTitle = "Développeur Web Freelance à Céret";
$pageDescription = "Développeur web freelance à Céret dans les Pyrénées-Orientales. Création de sites vitrines, boutiques en ligne, référencement naturel SEO et identité visuelle pour artisans, indépendants et associations.";

include 'partials/header.php';
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
                    J'imagine et dévelope des sites web sur mesure,
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

    <section class="services">
        <div class="container">

            <h2 class="section-title">Création de sites internet modernes pour les entreprises et indépendants</h2>

            <div class="services-grid">

                <!-- CARD 1 -->
                <div class="service-card">
                    <img src="/assets/icons/computer.svg" loading="lazy" alt="Site vitrine moderne">
                    <h3>Site vitrine moderne</h3>
                    <p>Création de sites modernes, optimisés, responsives et élégants  adaptés à votre activité.</p>
                </div>

                <!-- CARD 2 -->
                <div class="service-card">
                    <img src="/assets/icons/palette.svg" loading="lazy" alt="Identité visuelle">
                    <h3>Identité visuelle & Graphisme</h3>
                    <p>Création d’identités visuelles uniques : logos, chartes graphiques, supports web et print adaptés à votre marque.</p>
                </div>

                
                <!-- CARD 1 -->
                <div class="service-card">
                    <img src="/assets/icons/maintenance.svg" loading="lazy" alt="Maintenance & Optimisation">
                    <h3>Maintenance & optimisation</h3>
                    <p>Suivi technique, mises à jour, optimisation des performances et accompagnement pour un site sécurisé et rapide.</p>
                </div>
            </div>
        
        </div>

        <!-- PAGINATION -->
        <div class="services-pagination">
            <span class="dot active"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </div>

        <div class="services-btn-container">
            <a href="services.php" class="btn-primary">Voir tous mes services</a>
        </div>
    </section>

    <div class="section-divider"></div>


        <!-- =========================== ABOUT SECTION =========================== -->

    <section class="about">
        <div class="container about-container">

            <!-- Illustration -->
            <div class="about-image">
                <img src="/assets/images/about-illustration.webp"
                    loading="lazy"
                    alt="Développeur freelance illustration"
                    width="480"
                    height="320"
                >
            </div>

            <!-- Contenu texte -->
            <div class="about-content">
                <h2>Qui suis-je ?</h2>
                <h3>Développeur Web Freelance basé à Céret</h3>

                <p>
                    Basé à Céret dans les Pyrénées-Orientales, j'accompagne les artisans, commerçants, associations et
                    entreprises dans la création de sites internet modernes, rapides et optimisés pour le référencement naturel.
                    <br><br>
                    <strong>Mon objectif :</strong> transformer votre présence en ligne en un véritable atout professionnel.
                </p>
            
                <a href="contact.php" class="btn-secondary about-btn">Me contacter</a>
            </div>

        </div>
    </section>

    <div class="section-divider"></div>


        <!-- =========================== PORTFOLIO/REALISATIONS =========================== -->

    <section class="portfolio">
        <div class="container">

            <h2 class="section-title">Créations Web – Mes réalisations</h2>
            <p class="section-subtitle">Une sélection de projets conçus avec soin pour mes clients.</p>

            <div class="portfolio-grid">

                <?php
                require_once "php/db.php";

                // Récupérer les 3 dernières créations
                $query = $pdo->query("SELECT * FROM portfolio ORDER BY id DESC LIMIT 3");
                $projects = $query->fetchAll(PDO::FETCH_ASSOC);

                // 🟦 Table de correspondance CATEGORIE -> LIBELLÉ propre
                $categories = [
                    "figma" => "Maquettes Figma",
                    "vitrine" => "Site vitrine",
                    "logo" => "Identité visuelle",
                    "ecommerce" => "Boutique en ligne",
                    "app" => "Application Web & Mobile"
                ];

                foreach ($projects as $project):
                ?>

                <div class="portfolio-card">

                    <h3><?=  htmlspecialchars($project["titre"]); ?></h3>
                    <span class="project-type">
                        <?= htmlspecialchars($categories[$project["categorie"]] ?? $project["categorie"]); ?>
                    </span>

                    <div class="project-frame">
                        <img src="admin/uploads/creation/<?= htmlspecialchars($project["image"]); ?>" width="320px" height="320px" loading="lazy" alt="<?= htmlspecialchars($project["titre"]); ?>">
                    </div>

                </div>

                <?php endforeach; ?>

            </div>

            <div class="portfolio-btn-container">
                <a href="portfolio.php" class="btn-primary">Voir mes créations</a>
            </div>

        </div>
    </section>

    <div class="section-divider"></div>

        <!-- =========================== TESTIMONIALS =========================== -->


    <?php
    require_once "php/db.php";

    $sql = "SELECT * FROM avis WHERE statut = 'validé' ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>

    <section class="testimonials">
        <div class="container">

            <h2 class="section-title">Ils m'ont fait confiance !</h2>
            <p class="section-subtitle">
                Découvrez ce que pensent mes clients de leur expérience avec OL Creative Studio.
            </p>

            <div class="testimonial-card">

                <button class="arrow arrow-left" id="prevTestimonial">
                    <img src="assets/icons/arrow-left.svg" alt="Précédent" aria-label="Avis précédent">
                </button>

                <div class="testimonial-content">
                    <img src="assets/images/default-avatar.svg" id="clientAvatar" loading="lazy" alt="Avis client">

                    <h3 class="testimonial-name" id="clientName">Client</h3>
                    <p class="testimonial-type" id="clientType">Création d’un site vitrine</p>

                    <p class="testimonial-text" id="clientText">
                        “Othmane a fait un travail exceptionnel pour mon site vitrine. Rapidité,
                        qualité, écoute... Je recommande à 200 % !”
                    </p>
                </div>

                <button class="arrow arrow-right" id="nextTestimonial">
                    <img src="assets/icons/arrow-right.svg" alt="Suivant" aria-label="Avis suivant">
                </button>

            </div>

        </div>

        <script>
            const testimonials = <?= json_encode($avis); ?>;
        </script>

    </section>

        <!-- =========================== CTA SECTION =========================== -->

    <section class="cta">
        <div class="container cta-container">

            <h2>Prêt à booster votre présence en ligne ?</h2>
            <p>Des sites modernes, performants et élégants — adaptés à votre activité.</p>

            <a href="contact.php" class="btn-primary">Me contacter</a>

        </div>
    </section>

<script
    type="module"
    src="/assets/js/pages/home.js"
></script>

<?php include 'partials/footer.php'; ?>
