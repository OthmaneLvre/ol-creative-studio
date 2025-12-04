<?php include 'partials/header.php'; ?>
<?php require_once 'php/db.php'; ?>

<main>

    <!-- =========================== HERO =========================== -->
    <section class="portfolio-hero">
        <div class="container">
            <h1>Mon Portfolio</h1>
            <p>Découvrez une sélection de mes projets web & graphiques — maquettes Figma, sites vitrines, e-commerces, applications et identités visuelles.</p>
        </div>
    </section>


    <!-- =========================== CATÉGORIES (avec icônes) =========================== -->
    <section class="portfolio-categories">

        <div class="portfolio-section-title">
            <h2>Mes Créations</h3>
            <p>Un aperçu de mes différents domaines de création.</p>
        </div>

        <div class="container">
            <div class="portfolio-cat-wrapper">
                <div class="portfolio-cat-grid">

                    <div class="cat-card">
                        <img src="assets/icons/figma.png" alt="">
                        <h3>Maquettes Figma</h3>
                        <p>UI/UX modernes et responsives.</p>
                    </div>

                    <div class="cat-card">
                        <img src="assets/icons/vitrine.png" alt="">
                        <h3>Site vitrine moderne</h3>
                        <p>Des sites rapides, stylés et adaptés.</p>
                    </div>

                    <div class="cat-card">
                        <img src="assets/icons/ecommerce.png" alt="">
                        <h3>Boutiques en ligne</h3>
                        <p>Création de boutiques performantes.</p>
                    </div>

                    <div class="cat-card">
                        <img src="assets/icons/app.png" alt="">
                        <h3>Applications Web & Mobile</h3>
                        <p>Développement d’apps complètes.</p>
                    </div>

                    <div class="cat-card">
                        <img src="assets/icons/logo.png" alt="">
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
        "identite" => "Logos & identités visuelles"
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
                    <img src="admin/uploads/creation/<?= htmlspecialchars($p['image']) ?>"
                         alt="<?= htmlspecialchars($p['titre']) ?>">
                
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
            <h2>Vous avez un projet ?</h2>
            <h2>Discutons-en.</h2>
            <p>Je suis disponible pour créer votre site, votre identité visuelle ou votre application web.</p>
            <a href="contact.php" class="btn-primary">Me contacter</a>
        </div>
    </section>

</main>

<?php include 'partials/footer.php'; ?>
