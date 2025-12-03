<?php include 'partials/header.php'; ?>


<main>
    <!-- =========================== HERO SECTION =========================== -->

    <section class="hero">
        <div class="container hero-container">

            <div class="hero-text">
                <h1>Développeur Web & Graphiste Freelance</h1>
                <p>Sites vitrines modernes, optimisés et élégants — adaptés à votre activité.</p>
            
                <div class="hero-buttons">
                    <a href="contact.php" class="btn-primary">Me contacter</a>
                    <a href="services.php" class="btn-secondary">Voir mes services</a>
                </div>
            </div>

            <div class="hero-image">
                <img src="assets/images/hero.jpg" alt="Développeur freelance">
            </div>

        </div>
    </section>

    <div class="section-divider"></div>

        <!-- =========================== SERVICES SECTION =========================== -->

    <section class="services">
        <div class="container">

            <h2 class="section-title">Des services pensés pour booster votre présence en ligne</h2>

            <div class="services-grid">

                <!-- CARD 1 -->
                <div class="service-card">
                    <img src="assets/icons/computer.svg" alt="Site vitrine moderne">
                    <h3>Site vitrine moderne</h3>
                    <p>Création de sites modernes, optimisés, responsives et élégants  adaptés à votre activité.</p>
                </div>

                <!-- CARD 2 -->
                <div class="service-card">
                    <img src="assets/icons/palette.svg" alt="Identité visuelle">
                    <h3>Identité visuelle & Graphisme</h3>
                    <p>Création d’identités visuelles uniques : logos, chartes graphiques, supports web et print adaptés à votre marque.</p>
                </div>

                
                <!-- CARD 1 -->
                <div class="service-card">
                    <img src="assets/icons/maintenance.svg" alt="Maintenance & Optimisation">
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
                <img src="assets/images/about-illustration.png" alt="Développeur freelance illustration">
            </div>

            <!-- Contenu texte -->
            <div class="about-content">
                <h2>Qui suis-je ?</h2>
                <h3>Développeur Web Freelance basé à Céret</h3>

                <p>
                    Passionné par le web et le design, j’accompagne les entreprises et indépendants
                    dans la création de sites modernes, élégants et performants.
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

            <h2 class="section-title">Mes réalisations</h2>
            <p class="section-subtitle">Une sélection de projets conçus avec soin pour mes clients.</p>

            <div class="portfolio-grid">

                <?php
                require_once "php/db.php";

                // Récupérer les 3 dernières créations
                $query = $pdo->query("SELECT * FROM portfolio ORDER BY id DESC LIMIT 3");
                $projects = $query->fetchAll(PDO::FETCH_ASSOC);

                foreach ($projects as $project):
                ?>

                <div class="portfolio-card">

                    <h3><?=  htmlspecialchars($project["titre"]); ?></h3>
                    <span class="project-type">Site vitrine</span>

                    <div class="project-frame">
                        <img src="admin/uploads/<?= htmlspecialchars($project["image"]); ?>" alt="<?= htmlspecialchars($project["titre"]); ?>">
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

    <section class="testimonials">
        <div class="container">

            <h2 class="section-title">Ils m'ont fait confiance !</h2>
            <p class="section-subtitle">
                Découvrez ce que pensent mes clients de leur expérience avec OL Creative Studio.
            </p>

            <div class="testimonial-card">

                <button class="arrow arrow-left" id="prevTestimonial">
                    <img src="assets/icons/arrow-left.svg" alt="Précédent">
                </button>

                <div class="testimonial-content">
                    <div class="testimonial-avatar"></div>

                    <h3 class="testimonial-name" id="clientName">Client</h3>
                    <p class="testimonial-type" id="clientType">Création d’un site vitrine</p>

                    <p class="testimonial-text" id="clientText">
                        “Othmane a fait un travail exceptionnel pour mon site vitrine. Rapidité,
                        qualité, écoute... Je recommande à 200 % !”
                    </p>
                </div>

                <button class="arrow arrow-right" id="nextTestimonial">
                    <img src="assets/icons/arrow-right.svg" alt="Suivant">
                </button>

            </div>

        </div>
    </section>

        <!-- =========================== CTA SECTION =========================== -->

    <section class="cta">
        <div class="container cta-container">

            <h2>Prêt à booster votre présence en ligne ?</h2>
            <p>Des sites modernes, performants et élégants — adaptés à votre activité.</p>

            <a href="contact.php" class="btn-primary">Me contacter</a>

        </div>
    </section>

</main>

<?php include 'partials/footer.php'; ?>
