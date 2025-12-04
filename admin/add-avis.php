<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

if (isset($_POST["ajouter"])) {

    $nom = trim($_POST["nom"]);
    $categorie = trim($_POST["categorie"]);
    $commentaire = trim($_POST["commentaire"]);

    $avatarName = null;

    // ----- UPLOAD AVATAR -----
    if (!empty($_FILES["avatar"]["name"])) {

        $allowed = ["jpg", "jpeg", "png", "webp"];
        $fileName = $_FILES["avatar"]["name"];
        $fileTmp  = $_FILES["avatar"]["tmp_name"];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (in_array($ext, $allowed)) {

            $avatarName = uniqid("avatar_") . "." . $ext;
            $uploadPath = "uploads/avatars/" . $avatarName;

            move_uploaded_file($fileTmp, $uploadPath);
        }
    }

    // ----- SQL INSERT -----
    $sql = "INSERT INTO avis (nom, categorie, commentaire, avatar, statut)
            VALUES (:nom, :categorie, :commentaire, :avatar, 'validé')";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ":nom"        => $nom,
        ":categorie"  => $categorie,
        ":commentaire"=> $commentaire,
        ":avatar"     => $avatarName
    ]);

    header("Location: avis.php?success=1");
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Ajouter un avis client</title>

    <!-- FAVICON -->
    <link rel="icon" type="image/x-icon" href="/olcreativestudio/assets/logo/favicon_olCreativeStudio.png">

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

        <h1>Ajouter un avis client</h1>

        <form class="add-form" action="" method="POST" enctype="multipart/form-data">

            <label>Nom du client</label>
            <input type="text" name="nom" required>

            <label>Catégorie</label>
            <select name="categorie" required>
                <option value="Site vitrine">Site vitrine</option>
                <option value="Identité visuelle">Identité visuelle</option>
                <option value="Maquettes Figma">Maquettes Figma</option>
                <option value="E-commerce">Boutique en ligne</option>
                <option value="Application Web & Mobile">Application Web & Mobile</option>
                <option value="Autre">Autre</option>
            </select>

            <label>Commentaire</label>
            <textarea name="commentaire" required></textarea>

            <label>Avatar du client (optionnel)</label>
            <input type="file" name="avatar">

            <button type="submit" name="ajouter" class="btn">Créer l'avis</button>
        </form>

    </div>

</div>

</body>
</html>
