<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

if (!isset($_GET["id"])) {
    header("Location: dashboard.php");
    exit;
}

$id = intval($_GET["id"]);

// Récupération du projet
$stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);
$creation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$creation) {
    header("Location: dashboard.php");
    exit;
}

// Décodage JSON technologies
$techArray = [];
if (!empty($creation["technologies"])) {
    $techArray = json_decode($creation["technologies"], true);
}

// Décodage JSON galerie
$gallery = [];
if (!empty($creation["images_gallery"])) {
    $gallery = json_decode($creation["images_gallery"], true);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Modifier la création</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/olcreativestudio/assets/logo/favicon_olCreativeStudio.png">
    
    <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="admin-wrapper">
    <div class="sidebar">
        <h2>OL Creative Studio</h2>
        <a href="dashboard.php">📂 Dashboard</a>
        <a href="avis.php">💬 Avis clients</a>
        <a href="logout.php">Déconnexion</a>
    </div>

    <div class="main">
        <h1>Modifier la création</h1>

        <form class="add-form" action="edit_process.php" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="id" value="<?= $creation['id'] ?>">

            <!-- Titre -->
            <label for="titre">Titre</label>
            <input type="text" id="titre" name="titre" 
                value="<?= htmlspecialchars($creation['titre']) ?>" required>

            <!-- Catégorie -->
            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie" required>
                <option value="figma"      <?= $creation['categorie'] === "figma" ? "selected" : "" ?>>Maquettes Figma</option>
                <option value="vitrine"    <?= $creation['categorie'] === "vitrine" ? "selected" : "" ?>>Sites vitrines</option>
                <option value="ecommerce"  <?= $creation['categorie'] === "ecommerce" ? "selected" : "" ?>>Boutiques en ligne</option>
                <option value="app"        <?= $creation['categorie'] === "app" ? "selected" : "" ?>>Applications Web & Mobile</option>
                <option value="logo"       <?= $creation['categorie'] === "logo" ? "selected" : "" ?>>Logos & identités visuelles</option>
            </select>

            <!-- DESCRIPTION -->
            <label>Description</label>
            <textarea name="description" rows="5"><?= htmlspecialchars($creation['description']) ?></textarea>


            <!-- TECHNOLOGIES (TAGS) -->
            <label>Technologies utilisées</label>

            <div class="tech-container">

                <!-- Sélecteur -->
                <select id="techSelect">
                    <option value="">Ajouter une technologie…</option>
                    <option value="HTML">HTML</option>
                    <option value="CSS">CSS</option>
                    <option value="JavaScript">JavaScript</option>
                    <option value="PHP">PHP</option>
                    <option value="MySQL">MySQL</option>
                    <option value="WordPress">WordPress</option>
                    <option value="Figma">Figma</option>
                    <option value="Photoshop">Photoshop</option>
                    <option value="Illustrator">Illustrator</option>
                    <option value="Bootstrap">Bootstrap</option>
                    <option value="TailwindCSS">TailwindCSS</option>
                    <option value="React">React</option>
                    <option value="Vue.js">Vue.js</option>
                </select>

                <!-- Tags affichés -->
                <div id="techTags" class="tech-tags"></div>

                <!-- Champ envoyé à PHP -->
                    <input type="hidden" name="technologies" id="technologiesHidden">
            </div>

            <!-- URL DEMO -->
            <label>Lien du projet (optionnel)</label>
            <input type="url" name="url_demo" value="<?= htmlspecialchars($creation['url_demo']) ?>" placeholder="https://">


            <!-- IMAGE PRINCIPALE -->
            <label>Image principale actuelle</label>
            <img src="uploads/creation/<?= htmlspecialchars($creation['image']) ?>">

            <label>Remplacer l'image principale</label>
            <input type="file" name="image">

            <!-- GALERIE EXISTANTE -->
            <?php if (!empty($gallery)): ?>
                <label>Galerie actuelle</label>
                <div class="gallery-edit">
                    <?php foreach ($gallery as $img): ?>
                        <div class="gallery-item">
                            <img src="uploads/creation/<?= $img ?>">
                            <label>
                                <input type="checkbox" name="delete_gallery[]" value="<?= $img ?>">
                                Supprimer
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>


            <!-- AJOUT NOUVELLES IMAGES -->
            <label>Ajouter des images à la galerie</label>
            <input type="file" name="gallery[]" multiple>


            <button type="submit" class="btn">Mettre à jour</button>

        </form>
    </div>
</div>
<script>
    let initialTechs = <?= json_encode($techArray) ?>;
</script>
<script src="../js/admin.js"></script>

</body>
</html>
