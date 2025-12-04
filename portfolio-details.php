<?php
require_once "php/db.php";

if (!isset($_GET['id'])) {
    header("Location: portfolio.php");
    exit;
}

$id = intval($_GET['id']);

// Toutes les catégories possibles dans ton portfolio
$allCategories = [
    "figma"    => "Maquettes Figma",
    "vitrine"  => "Sites vitrines",
    "ecom"     => "Boutiques en ligne",
    "app"      => "Applications",
    "identite" => "Logos & identités visuelles"
];


// Récupération du projet
$stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header("Location: portfolio.php");
    exit;
}

// Détection projet type "logo"
$isLogo = ($project["categorie"] === "identite");

// Décodage de la galerie JSON
$gallery = [];
if (!empty($project["images_gallery"])) {
    $gallery = json_decode($project["images_gallery"], true);
}


?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($project['titre']) ?> — OL Creative Studio</title>

     <!-- Favicon -->
    <link rel="icon" type="image/png" href="/OLCreativeStudio/assets/logo/favicon_olCreativeStudio.png">

</head>

<body>

<?php include "partials/header.php"; ?>

<main class="project-details container">

    <div class="details-header">
        <h1><?= htmlspecialchars($project['titre']) ?></h1>

        <?php
            // clé de catégorie venant de la BDD
            $catKey = $project['categorie']; 

            // conversion clé → label lisible
            $catLabel = $allCategories[$catKey] ?? "Catégorie inconnue";
        ?>

        <p class="category"><?= htmlspecialchars($catLabel) ?></p>
    </div>


    <div class="details-image">
        <img src="admin/uploads/creation/<?= htmlspecialchars($project['image']) ?>" 
             alt="<?= htmlspecialchars($project['titre']) ?>"
             class="<?= $isLogo ? 'logo-protection' : '' ?>"
        >
    </div>

    <!-- GALERIE SUPPLÉMENTAIRE -->
    <?php
    $gallery = !empty($project['images_gallery'])
        ? json_decode($project['images_gallery'], true)
        : [];
    ?>

    <?php if (!empty($gallery)): ?>
    <div class="project-gallery">
        <h2>Galerie du projet</h2>

        <div class="gallery-grid">
            <?php foreach ($gallery as $img): ?>
                <div class="gallery-item">
                    <img src="admin/uploads/creation/<?= htmlspecialchars($img) ?>" 
                        alt="Image du project">
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- TECHNOLOGIES -->
    <?php
    $techs = json_decode($project['technologies'], true);
    ?>

    <?php if (!empty($techs)): ?>
    <section class="details-tech">
        <h2>Technologies utilisées</h2>

        <div class="tech-list">
            <?php foreach ($techs as $t): ?>
                <span class="tech-tag"><?= htmlspecialchars($t) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- DESCRIPTION -->
    <?php if (!empty($project['description'])): ?>
    <div class="details-description">
        <h2>À propos du projet</h2>
        <p><?= nl2br(htmlspecialchars($project['description'])) ?></p>
    </div>
    <?php endif; ?>



    <!-- LIEN DÉMO -->
    <?php if (!empty($project['url_demo'])): ?>
    <div class="details-buttons">
        <a href="<?= htmlspecialchars($project['url_demo']) ?>" class="btn-primary" target="_blank">
            🌐 Voir le site en ligne
        </a>
    </div>
    <?php endif; ?>

    <div class="details-buttons">
        <a href="portfolio.php" class="btn-secondary">⬅ Retour aux projets</a>
    </div>

</main>

<?php include "partials/footer.php"; ?>

</body>
</html>
