<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: /contact.php");
    exit;
}

// Honeypot anti-bot (champ caché dans le formulaire)
if (!empty($_POST["website"])) {
    // Si ce champ est rempli → bot → on coupe tout
    exit;
}

// Petite fonction de nettoyage
function clean($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$prenom  = clean($_POST["prenom"] ?? '');
$nom     = clean($_POST["nom"] ?? '');
$email   = trim($_POST["email"] ?? '');
$objet   = clean($_POST["objet"] ?? '');
$message = clean($_POST["message"] ?? '');

// Vérification email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: /contact.php?error=email");
    exit;
}

// On récupère l'IP pour les logs
$ip = $_SERVER['REMOTE_ADDR'] ?? '';

// ================== LOG EN BDD ==================
require_once __DIR__ . '/db.php';

try {
    $sql = "INSERT INTO contact_messages (prenom, nom, email, objet, message, ip, created_at)
            VALUES (:prenom, :nom, :email, :objet, :message, :ip, NOW())";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':prenom'  => $prenom,
        ':nom'     => $nom,
        ':email'   => $email,
        ':objet'   => $objet,
        ':message' => $message,
        ':ip'      => $ip
    ]);
} catch (PDOException $e) {
    // Tu peux loguer l'erreur si besoin
    // file_put_contents(__DIR__ . '/logs_contact_error.txt', $e->getMessage() . PHP_EOL, FILE_APPEND);
}

// ================== EMAIL À TOI ==================
$toOwner  = "contact@olcreativestudio.fr";
$subjectOwner = "📩 Nouveau message depuis OL Creative Studio – $objet";

$bodyOwner = "Nouveau message reçu depuis le formulaire de contact OL Creative Studio :

Prénom : $prenom
Nom : $nom
Email : $email
Objet : $objet

Message :
$message

IP : $ip
";

$headersOwner  = "From: OL Creative Studio <contact@olcreativestudio.fr>\r\n";
$headersOwner .= "Reply-To: $email\r\n";
$headersOwner .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headersOwner .= "X-Mailer: PHP/" . phpversion();

@mail($toOwner, $subjectOwner, $bodyOwner, $headersOwner);

// ================== EMAIL AU CLIENT (ACCUSÉ DE RÉCEPTION) ==================
$toClient  = $email;
$subjectClient = "Nous avons bien reçu votre message – OL Creative Studio";

$bodyClient = "Bonjour $prenom,

Merci pour votre message, il a bien été reçu par OL Creative Studio.

Récapitulatif :
Objet : $objet

Message :
$message

Je reviens vers vous sous 24 heures maximum.

À très vite,
Othmane
OL Creative Studio
https://olcreativestudio.fr
";

$headersClient  = "From: OL Creative Studio <contact@olcreativestudio.fr>\r\n";
$headersClient .= "Reply-To: contact@olcreativestudio.fr\r\n";
$headersClient .= "Content-Type: text/plain; charset=UTF-8\r\n";
$headersClient .= "X-Mailer: PHP/" . phpversion();

@mail($toClient, $subjectClient, $bodyClient, $headersClient);

// Redirection
header("Location: /contact.php?success=1");
exit;
