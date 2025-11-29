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

$id = $_GET["id"];

// Récupération de la création
$stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);
$creation = $stmt->fetch();

if (!$creation) {
    header("Location: dashboard.php");
    exit;
}
?>

<link rel="stylesheet" href="admin.css">

<div class="sidebar">
    <h2>OL Creative Studio</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="add.php">Ajouter une création</a>
    <a href="logout.php">Déconnexion</a>
</div>

<div class="main">
    <h1>Modifier la création</h1>

    <form class="add-form" action="edit_process.php" method="POST" enctype="multipart/form-data">

        <input type="hidden" name="id" value="<?= $creation['id'] ?>">

        <label for="titre">Titre</label>
        <input type="text" id="titre" name="titre" value="<?= htmlspecialchars($creation['titre']) ?>" required>

        <label for="description">Description</label>
        <textarea id="description" name="description"><?= htmlspecialchars($creation['description']) ?></textarea>

        <label>Image actuelle</label>
        <img src="uploads/<?= $creation['image'] ?>" width="250" style="border-radius:8px; margin-bottom:10px;">

        <label for="image">Nouvelle image (optionnel)</label>
        <input type="file" id="image" name="image">

        <button type="submit" class="btn">Mettre à jour</button>

    </form>
</div>
