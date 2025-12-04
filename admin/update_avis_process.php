<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

$id = $_POST["id"];
$nom = $_POST["nom"];
$categorie = $_POST["categorie"];
$commentaire = $_POST["commentaire"];

// Récupérer l'ancien avatar
$stmt = $pdo->prepare("SELECT avatar FROM avis WHERE id = :id");
$stmt->execute([":id" => $id]);
$old = $stmt->fetch();
$oldAvatar = $old["avatar"];

$newAvatar = $oldAvatar;

// Si un nouvel avatar est uploadé
if (!empty($_FILES["avatar"]["name"])) {

    $allowed = ["jpg", "jpeg", "png", "webp"];
    $fileName = $_FILES["avatar"]["name"];
    $fileTmp  = $_FILES["avatar"]["tmp_name"];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (in_array($ext, $allowed)) {

        // Nouveau nom sécurisé
        $newAvatar = uniqid() . "." . $ext;
        $uploadPath = "uploads/avatars/" . $newAvatar;

        move_uploaded_file($fileTmp, $uploadPath);

        // Suppression de l'ancien avatar
        if (!empty($oldAvatar) && file_exists("uploads/avatars/" . $oldAvatar)) {
            unlink("uploads/avatars/" . $oldAvatar);
        }
    }
}

// Mise à jour SQL
$sql = "UPDATE avis 
        SET nom = :nom, categorie = :categorie, commentaire = :commentaire, avatar = :avatar
        WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":nom" => $nom,
    ":categorie" => $categorie,
    ":commentaire" => $commentaire,
    ":avatar" => $newAvatar,
    ":id" => $id
]);

header("Location: avis.php?updated=1");
exit;
