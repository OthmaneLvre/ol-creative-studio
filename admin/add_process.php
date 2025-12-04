<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

// Sécurisation des champs
$titre = trim($_POST["titre"]);
$categorie = trim($_POST["categorie"]);
$desc = $_POST["description"] ?? "";
$techsJSON = $_POST["technologies"] ?? "[]";
$url_demo = $_POST["url_demo"] ?? "";

// ===== Vérification dossier upload =====
$folderMain = "uploads/creation";
$folderGallery = "uploads/creation/";

if (!file_exists($folderMain)) {
    mkdir($folderMain, 0755, true);
}

if (!file_exists($folderGallery)) {
    mkdir($folderGallery, 0755, true);
}

// ===== Sécurité UPLOAD =====
$allowed = ["jpg", "jpeg", "png", "webp"];
$filename = null;

if (!empty($_FILES["image"]["name"])) {

    $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed)) {
        die("Format d'image non autorisé.");
    }

    // Nom unique
    $filename = uniqid("creation_") . "." . $ext;
    $targetPath = $folderMain . $filename;

    move_uploaded_file($_FILES["image"]["tmp_name"], $targetPath);

} else {
    die("Aucune image envoyée.");
}

// ==== UPLOAD GALERIE MULTIPLE ====

$galleryFiles = [];

if (!empty($_FILES["gallery"]["name"][0])) {

    foreach ($_FILES["gallery"]["name"] as $i => $fileName) {
        $tmp = $_FILES["gallery"]["tmp_name"][$i];

        if (!empty($fileName)) {

            $ext2 = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext2, $allowed)) continue;

            $unique = uniqid("g_") . "." . $ext2;
            move_uploaded_file($tmp, $folderGallery . $unique);

            $galleryFiles[] = $unique;
        }
    }
}

$galleryJSON = json_encode($galleryFiles);

// ===== INSERT SQL =====
$sql = "INSERT INTO portfolio 
(titre, description, image, categorie, technologies, images_gallery, url_demo)
VALUES
(:t, :d, :i, :c, :tech, :gallery, :url)";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":t" => htmlspecialchars($titre),
    ":d" => $desc,
    ":i" => $filename,
    ":c" => $categorie,
    ":tech" => $techsJSON,
    ":gallery" => $galleryJSON,
    ":url" => $url_demo
]);

header("Location: dashboard.php?added=1");
exit;
