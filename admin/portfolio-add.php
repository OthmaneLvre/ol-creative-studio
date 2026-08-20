<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter une création - Admin</title>

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
        <a href="avis.php">💬 Avis clients</a>
        <a href="logout.php">Déconnexion</a>
    </div>

    <div class="main">

        <h1>Ajouter une création</h1>

        <form class="add-form" action="add_process.php" method="POST" enctype="multipart/form-data">

            <label for="titre">Titre de la création</label>
            <input type="text" id="titre" name="titre" placeholder="Ex : Site Vitrine Moderne" required>

            <label for="categorie">Catégorie</label>
            <select id="categorie" name="categorie" required>
                <option value="figma">Maquettes Figma</option>
                <option value="vitrine">Sites vitrines</option>
                <option value="ecommerce">Boutiques en ligne</option>
                <option value="app">Applications Web & Mobile</option>
                <option value="logo">Logos & identités visuelles</option>
            </select>

            <label for="image">Image</label>
            <input type="file" id="image" name="image" required>

            <label for="description">Description</label>
            <textarea name="description" id="description" rows="10" placeholder="Décris ton projet, objectifs, contexte..."></textarea>

            <label>Technologies utilisées</label>
            <div class="tech-container">

                <!-- Selecteur -->
                <select id="techSelect">
                    <option value="">Sélectionner une technologie…</option>
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

                <!-- Les tags apparaissent ici -->
                <div id="techTags" class="tech-tags"></div>

                <!-- Champ caché envoyé à PHP -->
                <input type="hidden" name="technologies" id="technologiesHidden">
            
            </div>

            <label>Lien du site (optionnel) :</label>
            <input type="url" name="url_demo" placeholder="https://...">

            <label>Photos supplémentaires :</label>
            <input type="file" name="gallery[]" multiple>

            <button type="submit" class="btn">Ajouter la création</button>

        </form>

    </div>

</div>

<script src="admin.js"></script>

</body>
</html>
