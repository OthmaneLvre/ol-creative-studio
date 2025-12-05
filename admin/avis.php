<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

// Récupérer tous les avis
$sql = "SELECT * FROM avis ORDER BY id DESC";
$stmt = $pdo->query($sql);
$avis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion des avis clients</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
    
    <link rel="stylesheet" href="admin.css">
</head>
<body>

<div class="admin-wrapper">

    <div class="sidebar">
        <h2>OL Creative Studio</h2>

        <a href="dashboard.php">📂 Dashboard</a>
        <a href="avis.php" class="active">💬 Avis clients</a>
        <a href="logout.php">Déconnexion</a>
    </div>

    <div class="main">

        <h1>Avis clients</h1>

        <a href="add-avis.php" class="btn">➕ Ajouter un avis</a>

        <?php if (empty($avis)): ?>
            <p>Aucun avis pour le moment.</p>
        <?php else: ?>

        <div class="grid">

            <?php foreach ($avis as $a): ?>
                <div class="card-avis">

                    <!-- Avatar -->
                    <?php
                    $avatarPath = "uploads/avatars/" . $a["avatar"];

                    if (!empty($a["avatar"]) && file_exists($avatarPath)): ?>
                        <img src="<?= $avatarPath ?>"
                            alt="Avatar client">
                    <?php endif; ?>

                    <!-- Nom -->
                    <h3><?= htmlspecialchars($a['nom']) ?></h3>

                    <!-- Catégorie -->
                    <p><strong><?= htmlspecialchars($a['categorie']) ?></strong></p>

                    <!-- Commentaire -->
                    <p>
                        <?= htmlspecialchars($a['commentaire']) ?>
                    </p>

                    <div class="actions">
                        <a class="edit-btn" href="update_avis.php?id=<?= $a['id'] ?>">Modifier</a>
                        <a href="delete-avis.php?id=<?= $a['id'] ?>" class="delete-btn">Supprimer</a>
                    </div>

                </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>

    </div>

</div>

</body>
</html>
