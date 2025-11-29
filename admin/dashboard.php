<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";
$creations = $pdo->query("SELECT * FROM portfolio ORDER BY date_creation DESC")->fetchAll();
?>

<link rel="stylesheet" href="admin.css">

<div class="sidebar">
    <h2>OL Creative Studio</h2>

    <a href="dashboard.php">Dashboard</a>
    <a href="add.php">Ajouter une création</a>
    <a href="logout.php">Déconnexion</a>
</div>

<div class="main">
    <h1>Bienvenue, <?= $_SESSION["admin_name"] ?></h1>

    <a class="btn" href="add.php">➕ Ajouter une création</a>

    <?php if (empty($creations)): ?>
        <p>Aucune création pour le moment.</p>
    <?php else: ?>
        <div class="grid">
            <?php foreach($creations as $c): ?>
                <div class="card">
                    <img src="uploads/<?= $c['image'] ?>" alt="">
                    <h3><?= htmlspecialchars($c['titre']) ?></h3>
                    <div class="actions">
                        <a class="edit-btn" href="edit.php?id=<?= $c['id'] ?>">Modifier</a>
                        <a class="delete-btn" href="delete.php?id=<?= $c['id'] ?>">Supprimer</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
