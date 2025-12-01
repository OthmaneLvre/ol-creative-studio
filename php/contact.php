<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $prenom = htmlspecialchars($_POST["prenom"]);
    $nom    = htmlspecialchars($_POST["nom"]);
    $email  = htmlspecialchars($_POST["email"]);
    $objet  = htmlspecialchars($_POST["objet"]);
    $message = htmlspecialchars($_POST["message"]);

    $to = "contact@olcreativestudio.com";
    $subject = "Nouveau message depuis OL Creative Studio";
    $body = "
        Nom : $nom\n
        Prénom : $prenom\n
        Email : $email\n
        Objet : $objet\n
        Message : \n$message
    ";

    $headers = "From: $email";

    mail($to, $subject, $body, $headers);

    header("Location: contact.php?success=1");
    exit;
}
