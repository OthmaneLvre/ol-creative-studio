<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";
$creations = $pdo->query("SELECT * FROM portfolio ORDER BY date_creation DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Admin - OL Creative Studio</title>
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
    
    <!-- CSS -->
        <link rel="stylesheet" href="admin.css">

</head>
<body>

<div class="admin-wrapper">
    <div class="sidebar">
        <h2>OL Creative Studio</h2>

        <a href="dashboard.php" class="active">📂 Dashboard</a>
        <a href="avis.php">💬 Avis clients</a>
        <a href="logout.php">Déconnexion</a>
    </div>

    <div class="main">
        <h1>Bienvenue, <?= htmlspecialchars($_SESSION["admin_name"]) ?></h1>

        <div class="top-bar">
            <a class="btn" href="add.php">➕ Ajouter une création</a>
        </div>

        <?php if (empty($creations)): ?>
            <p>Aucune création enregistrée pour le moment.</p>

        <?php else: ?>
            <div class="grid">
                <?php foreach($creations as $c): ?>

                    <div class="card">
                        
                        <?php
                        $imgPath = "uploads/creation/" . $c["image"];
                        ?>

                        <img src="<?= $imgPath ?>" alt="<?= htmlspecialchars($c["titre"]) ?>">

                        <div class="card-body">
                            <h3><?= htmlspecialchars($c['titre']) ?></h3>
                        </div>

                        <div class="actions">
                            <a class="edit-btn" href="edit.php?id=<?= $c['id'] ?>">Modifier</a>
                            <a class="delete-btn" href="delete.php?id=<?= $c['id'] ?>">Supprimer</a>
                        </div>
                        
                    </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
