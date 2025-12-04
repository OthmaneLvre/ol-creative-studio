<?php
session_start();

// Supprime toutes les variables de session
$_SESSION = [];

// Supprime le cookie de session si il existe
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"],
        $params["secure"], 
        $params["httponly"]
    );
}

// Détruit la session
session_destroy();

// Empêche tout retour sur les pages admin avec le bouton "back"
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

header("Location: login.php?logged_out=1");
exit;
