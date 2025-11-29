<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

$id = $_POST["id"];
$titre = $_POST["titre"];
$desc = $_POST["description"];

// Récupérer l'ancienne image
$stmt = $pdo->prepare("SELECT image FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);
$old = $stmt->fetch();
$old_image = $old["image"];

$new_image = $old_image;

// Vérifier si une nouvelle image a été uploadée
if (!empty($_FILES["image"]["name"])) {

    $folder = "uploads/";
    if (!file_exists($folder)) {
        mkdir($folder, 0777, true);
    }

    // Nouveau nom de fichier
    $filename = time() . "_" . basename($_FILES["image"]["name"]);
    $targetPath = $folder . $filename;

    // Upload
    move_uploaded_file($_FILES["image"]["tmp_name"], $targetPath);

    // Supprimer l'ancienne image
    if (file_exists("uploads/" . $old_image)) {
        unlink("uploads/" . $old_image);
    }

    $new_image = $filename;
}

// Mettre à jour la création
$sql = "UPDATE portfolio SET titre = :t, description = :d, image = :i WHERE id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":t" => $titre,
    ":d" => $desc,
    ":i" => $new_image,
    ":id" => $id
]);

header("Location: dashboard.php");
exit;
