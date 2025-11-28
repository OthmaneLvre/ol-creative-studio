<?php
session_start();
require_once "../php/db.php";

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = $_POST["username"];
    $password = $_POST["password"];

    $sql = "SELECT * FROM admin_users WHERE username = :user LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([":user" => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin["password"])) {
        $_SESSION["admin_logged"] = true;
        $_SESSION["admin_name"] = $admin["username"];
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Identifiants incorrects.";
    }
}
?>

<link rel="stylesheet" href="login.css">

<div class="login-container">

    <div class="login-card">
        <h1>OL Creative Studio</h1>
        <p class="subtitle">Connexion administrateur</p>

        <?php if($error): ?>
            <p class="error-msg"><?= $error ?></p>
        <?php endif; ?>

        <form method="POST">

            <label>Nom d'utilisateur</label>
            <input type="text" name="username" placeholder="Votre identifiant" required>

            <label>Mot de passe</label>
            <input type="password" name="password" placeholder="Votre mot de passe" required>

            <button type="submit" class="btn">Se connecter</button>

        </form>
    </div>

</div>

