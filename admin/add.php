<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
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
    <h1>Ajouter une création</h1>

    <form class="add-form" action="add_process.php" method="POST" enctype="multipart/form-data">

        <label for="titre">Titre de la création</label>
        <input type="text" id="titre" name="titre" placeholder="Ex: Site Vitrine Moderne" required>

        <label for="categorie">Catégorie</label>
        <select id="categorie" name="categorie" required>
            <option value="figma">Maquettes Figma</option>
            <option value="vitrine">Sites vitrines</option>
            <option value="boutique">Boutiques en ligne</option>
            <option value="applications">Applications Web & Mobile</option>
            <option value="identite">Logos & identités visuelles</option>
        </select>

        <label for="image">Image</label>
        <input type="file" id="image" name="image" required>

        <button type="submit" class="btn">Ajouter la création</button>

    </form>
</div>
