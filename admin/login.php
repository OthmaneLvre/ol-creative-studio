<?php
session_start();
require_once "../php/db.php";

// Si déjà connecté → redirection
if (isset($_SESSION["admin_logged"]) && $_SESSION["admin_logged"] === true) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

// Générer un token CSRF si pas existant
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Vérification CSRF
    if (!isset($_POST["csrf"]) || $_POST["csrf"] !== $_SESSION["csrf_token"]) {
        $error = "Requête invalide.";
    } else {
        $username = trim($_POST["username"]);
        $password = $_POST["password"];

        // Sélection utilisateur
        $sql = "SELECT * FROM admin_users WHERE username = :user LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([":user" => $username]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vérification crédentials
        if ($admin && password_verify($password, $admin["password"])) {

            // Empêche session fixation
            session_regenerate_id(true);

            $_SESSION["admin_logged"] = true;
            $_SESSION["admin_name"] = $admin["username"];

            header("Location: dashboard.php");
            exit;

        } else {
            $error = "Identifiants incorrects.";
        }
    }

    // Regenerate token à chaque POST
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Connexion administrateur - OL Creative Studio</title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/olcreativestudio/assets/logo/favicon_olCreativeStudio.png">
    

    <link rel="stylesheet" href="login.css">
</head>
<body>

<div class="login-container">

    <div class="login-card">
        <h1>OL Creative Studio</h1>
        <p class="subtitle">Connexion administrateur</p>

        <?php if (!empty($error)): ?>
            <p class="error-msg"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <form method="POST">

            <!-- Anti-CSRF -->
            <input type="hidden" name="csrf" value="<?= $_SESSION["csrf_token"] ?>">

            <label>Nom d'utilisateur</label>
            <input type="text" name="username" placeholder="Votre identifiant" required>

            <label>Mot de passe</label>
            <input type="password" name="password" placeholder="Votre mot de passe" required>

            <button type="submit" class="btn">Se connecter</button>

        </form>
    </div>

</div>

</body>
</html>
