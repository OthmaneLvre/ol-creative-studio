<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

$id = $_GET["id"];

// récupérer l'image
$stmt = $pdo->prepare("SELECT image FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);
$creation = $stmt->fetch(PDO::FETCH_ASSOC);

if ($creation && !empty($creation["image"])) {

    $filePath = "uploads/creation/" . $creation["image"];

    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// supprimer en DB
$stmt = $pdo->prepare("DELETE FROM portfolio WHERE id = :id");
$stmt->execute([":id" => $id]);

// =========================
// 🔥 REGENERATION SITEMAP
// =========================
require_once "../php/generate_sitemap.php";
regenerateSitemap();

// 🔥 Notification Google du nouveau sitemap
file_get_contents("http://www.google.com/ping?sitemap=https://olcreativestudio.fr/sitemap.xml");

header("Location: dashboard.php?deleted=1");
exit;
