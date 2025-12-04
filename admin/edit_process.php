<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

$id = intval($_POST["id"]);

// ===== RÉCUPÉRER L'ANCIEN PROJET COMPLET =====
$stmt = $pdo->prepare("SELECT * FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);
$old = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$old) {
    header("Location: dashboard.php");
    exit;
}

// ===== CHAMPS MIS À JOUR =====
$titre = trim($_POST["titre"]);
$categorie = trim($_POST["categorie"]);
$desc = $_POST["description"] ?? "";
$techsJSON = $_POST["technologies"] ?? "[]";
$url_demo = $_POST["url_demo"] ?? "";

// ===== DOSSIERS =====
$folderMain = "uploads/creation/";
$folderGallery = "uploads/creation/";

if (!file_exists($folderMain)) mkdir($folderMain, 0755, true);

// ===== EXTENSIONS PERMISES =====
$allowed = ["jpg", "jpeg", "png", "webp"];

// ===============================================
// ✦ IMAGE PRINCIPALE
// ===============================================
$new_image = $old["image"];  // par défaut, on garde l’ancienne

if (!empty($_FILES["image"]["name"])) {

    $ext = strtolower(pathinfo($_FILES["image"]["name"], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) die("Format d'image non autorisé.");

    $filename = uniqid("creation_") . "." . $ext;

    move_uploaded_file(
        $_FILES["image"]["tmp_name"],
        $folderMain . $filename
    );

    // supprimer l’ancienne image
    if (!empty($old["image"]) && file_exists($folderMain . $old["image"])) {
        unlink($folderMain . $old["image"]);
    }

    $new_image = $filename;
}


// ===============================================
// ✦ SUPPRESSION IMAGES DE LA GALERIE
// ===============================================
$gallery = json_decode($old["images_gallery"], true) ?: [];

if (!empty($_POST["delete_gallery"])) {
    foreach ($_POST["delete_gallery"] as $imgToDelete) {

        $path = $folderGallery . $imgToDelete;

        // supprimer physiquement
        if (file_exists($path)) unlink($path);

        // retirer de la liste
        $gallery = array_filter($gallery, fn($i) => $i !== $imgToDelete);
    }
}


// ===============================================
// ✦ AJOUT NOUVELLES IMAGES
// ===============================================
if (!empty($_FILES["gallery"]["name"][0])) {

    foreach ($_FILES["gallery"]["name"] as $i => $fileName) {

        if (empty($fileName)) continue;

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;

        $unique = uniqid("g_") . "." . $ext;

        move_uploaded_file(
            $_FILES["gallery"]["tmp_name"][$i],
            $folderGallery . $unique
        );

        $gallery[] = $unique;
    }
}

$galleryJSON = json_encode($gallery);


// ===============================================
// ✦ MISE À JOUR SQL
// ===============================================
$sql = "UPDATE portfolio SET
        titre = :t,
        description = :d,
        categorie = :c,
        image = :i,
        technologies = :tech,
        images_gallery = :gallery,
        url_demo = :url
        WHERE id = :id";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":t" => htmlspecialchars($titre),
    ":d" => $desc,
    ":c" => $categorie,
    ":i" => $new_image,
    ":tech" => $techsJSON,
    ":gallery" => $galleryJSON,
    ":url" => $url_demo,
    ":id" => $id
]);

header("Location: dashboard.php?updated=1");
exit;
