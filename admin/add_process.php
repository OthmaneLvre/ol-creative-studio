<?php
session_start();
if (!isset($_SESSION["admin_logged"])) {
    header("Location: login.php");
    exit;
}

require_once "../php/db.php";

$titre = $_POST["titre"];
$categorie = $_POST["categorie"];

// dossier upload
$folder = "uploads/";
if (!file_exists($folder)) {
    mkdir($folder, 0777, true);
}

// upload image
$filename = time() . "_" . basename($_FILES["image"]["name"]);
$targetPath = $folder . $filename;

move_uploaded_file($_FILES["image"]["tmp_name"], $targetPath);

// insérer en DB
$sql = "INSERT INTO portfolio (titre, image, categorie)
        VALUES (:t, :i, :c)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ":t" => $titre,
    ":d" => $desc,
    ":i" => $filename,
    ":c" => $categorie
]);

header("Location: dashboard.php");
exit;
