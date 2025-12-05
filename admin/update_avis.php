<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

// Vérifier l'ID
if (!isset($_GET["id"])) {
    header("Location: avis.php");
    exit;
}

$id = intval($_GET["id"]);

// Récupération de l'avis
$stmt = $pdo->prepare("SELECT * FROM avis WHERE id = :id");
$stmt->execute([":id" => $id]);
$avis = $stmt->fetch();

if (!$avis) {
    header("Location: avis.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un avis client</title>

    <!-- FAVICON -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">

    <!-- CSS -->
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
        <h1>Modifier un avis</h1>

        <form class="add-form" action="update_avis_process.php" method="POST" enctype="multipart/form-data">

            <input type="hidden" name="id" value="<?= $avis['id'] ?>">

            <label>Nom du client :</label>
            <input type="text" name="nom" value="<?= htmlspecialchars($avis['nom']) ?>" required>

            <label>Catégorie :</label>
            <input type="text" name="categorie" value="<?= htmlspecialchars($avis['categorie']) ?>">

            <label>Commentaire :</label>
            <textarea name="commentaire" required><?= htmlspecialchars($avis['commentaire']) ?></textarea>

            <label>Avatar (laisser vide pour garder l'actuel) :</label>
            <?php if (!empty($avis["avatar"])): ?>
                <img src="uploads/avatars/<?= $avis['avatar'] ?>" width="80" style="border-radius:50%; margin-bottom:10px;">
            <?php endif; ?>

            <input type="file" name="avatar">

            <button type="submit" class="btn">Mettre à jour</button>
        </form>
    </div>

</div>

</body>
</html>

