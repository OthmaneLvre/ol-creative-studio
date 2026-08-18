<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /contact.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Honeypot anti-bot
|--------------------------------------------------------------------------
*/

if (!empty($_POST['website'] ?? '')) {
    exit;
}


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function cleanInput(?string $value): string
{
    return trim((string) $value);
}


/*
|--------------------------------------------------------------------------
| Données
|--------------------------------------------------------------------------
*/

$prenom = cleanInput($_POST['prenom'] ?? '');
$nom = cleanInput($_POST['nom'] ?? '');
$email = cleanInput($_POST['email'] ?? '');
$telephone = cleanInput($_POST['telephone'] ?? '');
$objet = cleanInput($_POST['objet'] ?? '');
$budget = cleanInput($_POST['budget'] ?? '');
$message = cleanInput($_POST['message'] ?? '');

$consent = isset($_POST['consent'])
    && $_POST['consent'] === '1';


/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (
    mb_strlen($prenom) < 2 ||
    mb_strlen($nom) < 2 ||
    mb_strlen($objet) < 2 ||
    mb_strlen($message) < 10
) {
    header('Location: /contact.php?error=fields');
    exit;
}


if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: /contact.php?error=email');
    exit;
}


if (!$consent) {
    header('Location: /contact.php?error=consent');
    exit;
}


/*
|--------------------------------------------------------------------------
| IP
|--------------------------------------------------------------------------
*/

$ip = $_SERVER['REMOTE_ADDR'] ?? '';


/*
|--------------------------------------------------------------------------
| Enregistrement BDD
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/mailer.php';

try {

    $sql = "
        INSERT INTO contact_messages (
            prenom,
            nom,
            email,
            telephone,
            objet,
            budget,
            message,
            consent,
            ip,
            created_at
        )
        VALUES (
            :prenom,
            :nom,
            :email,
            :telephone,
            :objet,
            :budget,
            :message,
            :consent,
            :ip,
            NOW()
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':prenom' => $prenom,
        ':nom' => $nom,
        ':email' => $email,
        ':telephone' => $telephone,
        ':objet' => $objet,
        ':budget' => $budget,
        ':message' => $message,
        ':consent' => 1,
        ':ip' => $ip,
    ]);

} catch (PDOException $e) {

    error_log(
        '[CONTACT] Database error: ' .
        $e->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| Email propriétaire
|--------------------------------------------------------------------------
*/

$toOwner = 'contact@olcreativestudio.fr';

$subjectOwner =
    "Nouveau message OL Creative Studio – {$objet}";

$telephoneDisplay =
    $telephone !== ''
        ? $telephone
        : 'Non renseigné';

$budgetDisplay =
    $budget !== ''
        ? $budget
        : 'Non renseigné';

$bodyOwner = <<<TEXT
Nouveau message reçu depuis le formulaire OL Creative Studio.

Prénom : {$prenom}
Nom : {$nom}
Email : {$email}
Téléphone : {$telephoneDisplay}

Projet : {$objet}
Budget : {$budgetDisplay}

Message :
{$message}

Consentement RGPD : Oui
IP : {$ip}
TEXT;

$ownerMailSent = sendMail(
    $toOwner,
    'OL Creative Studio',
    $subjectOwner,
    $bodyOwner,
    $email,
    "{$prenom} {$nom}"
);

if (!$ownerMailSent) {

    error_log(
        '[CONTACT] Owner email could not be sent.'
    );

} else {

    error_log(
        '[CONTACT] Owner email sent successfully.'
    );
}


/*
|--------------------------------------------------------------------------
| Accusé de réception client
|--------------------------------------------------------------------------
*/

$subjectClient =
    'Votre demande a bien été reçue – OL Creative Studio';

$bodyClient = <<<TEXT
Bonjour {$prenom},

Merci pour votre message. Votre demande a bien été reçue par OL Creative Studio.

Récapitulatif :

Projet : {$objet}
Budget : {$budgetDisplay}

Message :
{$message}

Je reviens vers vous généralement sous 24 heures ouvrées.

À bientôt,

Othmane
OL Creative Studio
https://olcreativestudio.fr
TEXT;

$clientMailSent = sendMail(
    $email,
    "{$prenom} {$nom}",
    $subjectClient,
    $bodyClient
);

if (!$clientMailSent) {

    error_log(
        '[CONTACT] Client confirmation could not be sent.'
    );

} else {

    error_log(
        '[CONTACT] Client confirmation sent successfully to: ' .
        $email
    );
}


/*
|--------------------------------------------------------------------------
| Redirection
|--------------------------------------------------------------------------
*/

header('Location: /contact.php?success=1');
exit;
