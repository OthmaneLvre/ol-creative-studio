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

header("Location: dashboard.php?deleted=1");
exit;
