<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

// ============================
// 🖼️ Fonction redimensionnement + WEBP
// ============================
function resizeTo1200Webp($sourceTmpPath, $destinationPath, $quality = 85) {

    list($w, $h, $type) = getimagesize($sourceTmpPath);

    $newWidth = 1200;
    $newHeight = intval($h * ($newWidth / $w));

    // Chargement selon le type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($sourceTmpPath);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($sourceTmpPath);
            break;
        case IMAGETYPE_WEBP:
            $src = imagecreatefromwebp($sourceTmpPath);
            break;
        default:
            return false;
    }

    $dst = imagecreatetruecolor($newWidth, $newHeight);

    if ($type == IMAGETYPE_PNG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0,
        $newWidth, $newHeight, $w, $h
    );

    imagewebp($dst, $destinationPath, $quality);

    imagedestroy($src);
    imagedestroy($dst);

    return true;
}


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

    // Nouveau nom en WebP
    $filename = uniqid("creation_") . ".webp";
    $target = $folderMain . $filename;

    // Redimensionne + convertir en WebP
    resizeTo1200Webp($_FILES["image"]["tmp_name"], $target);

    // Supprimer l'ancienne image
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

        $tmp = $_FILES["gallery"]["tmp_name"][$i];

        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed)) continue;

        $newName = uniqid("g_") . ".webp";
        $dest = $folderGallery . $newName;

        // Redimension + conversion WebP
        resizeTo1200Webp($tmp, $dest);
        
        $gallery[] = $newName;
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

// =========================
// 🔥 REGENERATION SITEMAP
// =========================
require_once "../php/generate_sitemap.php";
regenerateSitemap();

// 🔥 Notification Google du nouveau sitemap
file_get_contents("http://www.google.com/ping?sitemap=https://olcreativestudio.fr/sitemap.xml");


header("Location: dashboard.php?updated=1");
exit;
