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

    // Charger l'image selon son type
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

    // Nouveau canevas
    $dst = imagecreatetruecolor($newWidth, $newHeight);

    // PNG transparence
    if ($type == IMAGETYPE_PNG) {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
    }

    // Redimensionnement
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 
        $newWidth, $newHeight, $w, $h
    );

    // Sauvegarde en WebP compressé
    imagewebp($dst, $destinationPath, $quality);

    imagedestroy($src);
    imagedestroy($dst);

    return true;
}

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
    $filename = uniqid("creation_") . ".webp";
    $targetPath = $folderMain . $filename;

    // Redimension + conversion 
    resizeTo1200Webp($_FILES["image"]["tmp_name"], $targetPath);

} else {
    die("Aucune image envoyée.");
}

// ==== UPLOAD GALERIE MULTIPLE ====

$galleryFiles = [];

if (!empty($_FILES["gallery"]["name"][0])) {

    foreach ($_FILES["gallery"]["name"] as $i => $fileName) {

        if (!empty($fileName)) {

            $tmp = $_FILES["gallery"]["tmp_name"][$i];

            // Vérification extension
            $ext2 = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if (!in_array($ext2, $allowed)) continue;

            // Nouveau nom en WEBP
            $newName = uniqid("g_") . ".webp";
            $destGallery = $folderGallery . $newName;

            // Redimension + conversion WEBP
            resizeTo1200Webp($tmp, $destGallery);

            $galleryFiles[] = $newName;
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

// =========================
// 🔥 REGENERATION SITEMAP
// =========================
require_once "../php/generate_sitemap.php";
regenerateSitemap();

// 🔥 Notification Google du nouveau sitemap
file_get_contents("http://www.google.com/ping?sitemap=https://olcreativestudio.fr/sitemap.xml");

header("Location: dashboard.php?added=1");
exit;
