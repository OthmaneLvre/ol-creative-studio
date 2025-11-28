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
$creation = $stmt->fetch();

if ($creation) {
    unlink("uploads/" . $creation["image"]);
}

// supprimer en DB
$pdo->prepare("DELETE FROM portfolio WHERE id = :id")->execute([":id" => $id]);

header("Location: dashboard.php");
exit;
