<?php
$pageTitle = "Portfolio – Créateur de sites web & identités visuelles";
$pageDescription = "Découvrez mes créations : sites vitrines, boutiques en ligne, maquettes Figma, applications web & mobiles et identités visuelles. Travaux professionnels réalisés pour entreprises et indépendants.";
include 'partials/header.php';
require_once 'php/db.php'; ?>

<main class="page-content">

    <!-- =========================== HERO =========================== -->
    <section class="portfolio-hero">
            <img src="/assets/images/illustration-hero.webp"
            alt="Illustration portfolio"
            width="1200"
            height="1200"
            class="hero-bg"
            loading="eager">
        <div class="container">
            <h1>Portfolio – Créations Web & Identités Visuelles</h1>
            <p>Découvrez une sélection de projets en design, développement web, maquettes Figma et créations graphiques.</p>
        </div>
    </section>

    <!-- =========================== HERO =========================== -->
    <section class="seo-text">
    <div class="container">
        <h2>Un portfolio dédié à la création web et au design graphique</h2>
        <p>
            À travers ce portfolio, je présente une sélection de projets réalisés en
            <strong>développement web</strong>, <strong>création de sites vitrines</strong>,
            <strong>identités visuelles</strong>, <strong>maquettes Figma</strong> et
            <strong>applications web & mobiles</strong>. Mon travail repose sur une approche
            centrée utilisateur : design clair, expérience intuitive et performances optimisées.
        </p>

        <br>

        <p>
            Chaque projet présenté a été conçu <strong>sur-mesure</strong> pour répondre aux besoins de mes clients :
            <strong>artisans</strong>, <strong>entrepreneurs</strong>, <strong>entreprises locales</strong>, <strong>associations</strong> ou <strong>projets personnels</strong>.
            Que ce soit pour un site vitrine moderne, une boutique en ligne performante, un logo professionnel
            ou une interface mobile, j’accorde une attention particulière à la cohérence visuelle, à la
            qualité du code et aux bonnes pratiques SEO.
        </p>

        <br>

        <p>
            Mon objectif est de proposer des créations esthétiques, efficaces et durables, capables
            d’améliorer la visibilité en ligne de chaque client. Si vous souhaitez discuter de votre
            projet ou obtenir un devis, je reste entièrement disponible pour échanger et vous accompagner.
        </p>
    </div>
    </section>

    <!-- =========================== CATÉGORIES (avec icônes) =========================== -->
    <section class="portfolio-categories">

        <div class="portfolio-section-title">
            <h2>Mes Créations</h2>
            <p>Un aperçu de mes différents domaines d'expertise.</p>
        </div>

        <div class="container">
            <div class="portfolio-cat-wrapper">
                <div class="portfolio-cat-grid">

                    <div class="cat-card">
                        <img src="/assets/icons/figma.svg" alt="Icône Maquettes Figma">
                        <h3>Maquettes Figma</h3>
                        <p>UI/UX modernes et responsives.</p>
                    </div>

                    <div class="cat-card">
                        <img src="/assets/icons/vitrine.svg" alt="Icône Site vitrine moderne">
                        <h3>Site vitrine moderne</h3>
                        <p>Des sites rapides, stylés et adaptés.</p>
                    </div>

                    <div class="cat-card">
                        <img src="/assets/icons/ecommerce.svg" alt="Icône Boutique en ligne">
                        <h3>Boutiques en ligne</h3>
                        <p>Création de boutiques performantes.</p>
                    </div>

                    <div class="cat-card">
                        <img src="/assets/icons/app.svg" alt="Icône Application Web et Mobile">
                        <h3>Applications Web & Mobile</h3>
                        <p>Développement d’apps complètes.</p>
                    </div>

                    <div class="cat-card">
                        <img src="/assets/icons/logo.svg" alt="Icône Logos et identités visuelles">
                        <h3>Logos & identités visuelles</h3>
                        <p>Identités uniques & personnalisées.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="portfolio-cat-pagination">
            <span class="dot active"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
            <span class="dot"></span>
        </div>

    </section>

    <!-- =========================== CHARGEMENT DES CATEGORIES AVANT LES FILTRES =========================== -->
    <?php
    // On récupère les projets
    $sql = "SELECT * FROM portfolio ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Toutes les catégories possibles dans ton portfolio
    $allCategories = [
        "figma"    => "Maquettes Figma",
        "vitrine"  => "Sites vitrines",
        "ecom"     => "Boutiques en ligne",
        "app"      => "Applications",
        "logo" => "Logos & identités visuelles"
    ];

    // Récupérer toutes les catégories existantes dans la BDD
    $sqlCat = "SELECT DISTINCT categorie FROM portfolio";
    $stmtCat = $pdo->query($sqlCat);
    $existingCategories = $stmtCat->fetchAll(PDO::FETCH_COLUMN);
    ?>


    <!-- =========================== FILTRES =========================== -->
    <section class="portfolio-filters">
        <div>

            <!-- Toujours visible -->
            <button class="filter-btn active" data-category="all">Tous</button>
            
            <!-- Boutons dynamiques -->
            <?php foreach ($allCategories as $key => $label): ?>
                <?php if (in_array($key, $existingCategories)): ?>
                    <button class="filter-btn" data-category="<?= $key ?>">
                        <?= $label ?>
                    </button>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </section>


    <!-- =========================== GRILLE PROJETS (BDD) =========================== -->
    <section class="portfolio-list">
        <div class="container portfolio-grid">

            <?php foreach ($projects as $p): ?>

            <a href="portfolio-details.php?id=<?= $p['id'] ?>"
                class="portfolio-card"
                data-category="<?= htmlspecialchars($p['categorie'] ?? 'all') ?>">

                <div class="card-image">
                    <img src="/admin/uploads/creation/<?= htmlspecialchars($p['image']) ?>"
                         alt="<?= htmlspecialchars($p['titre']) ?>"
                        loading="lazy"
                    >
                
                    <div class="overlay">
                        Voir les détails du projet
                    </div>
                </div>

                <h3><?= htmlspecialchars($p['titre']) ?></h3>

            </a>

            <?php endforeach; ?>

        </div>
    </section>


    <!-- =========================== CTA =========================== -->
    <section class="portfolio-cta">
        <div class="container">
            <h2>Vous avez un projet ? Discutons-en.</h2>
            <p>Je suis disponible pour créer votre site, votre identité visuelle ou votre application web.</p>
            <a href="contact.php" class="btn-primary">Me contacter</a>
        </div>
    </section>

</main>

<?php include 'partials/footer.php'; ?>
