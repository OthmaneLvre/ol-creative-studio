<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

if (!isset($_GET["id"])) {
    header("Location: avis.php");
    exit;
}

$id = $_GET["id"];

// 1. Récupérer l'avatar
$stmt = $pdo->prepare("SELECT avatar FROM avis WHERE id = :id");
$stmt->execute([":id" => $id]);
$avis = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Supprimer l'avatar si il existe
if ($avis && !empty($avis["avatar"])) {

    $filePath = "uploads/avatars/" . $avis["avatar"];

    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// 3. Supprimer l'avis en DB
$stmt = $pdo->prepare("DELETE FROM avis WHERE id = :id");
$stmt->execute([":id" => $id]);

// 🔥 Notifier Google qu'un nouveau contenu est disponible
file_get_contents("https://www.google.com/ping?sitemap=https://olcreativestudio.fr/sitemap.xml");

header("Location: avis.php?deleted=1");
exit;
