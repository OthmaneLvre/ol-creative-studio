<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/../php/mailer.php';


/*
|--------------------------------------------------------------------------
| Administrateur déjà connecté
|--------------------------------------------------------------------------
*/

if (
    isset($_SESSION['admin_logged']) &&
    $_SESSION['admin_logged'] === true
) {
    header('Location: /admin/dashboard.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| État
|--------------------------------------------------------------------------
*/

$error = '';
$success = '';
$email = '';


/*
|--------------------------------------------------------------------------
| Traitement
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf =
        (string) ($_POST['csrf'] ?? '');

    if (
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf
        )
    ) {

        $error =
            'Session expirée ou requête invalide.';

    } else {

        $email =
            trim(
                strtolower(
                    (string) ($_POST['email'] ?? '')
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if (
            $email === '' ||
            filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {

            $error =
                'Veuillez renseigner une adresse email valide.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Recherche administrateur
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare(
                    'SELECT
                        id,
                        username,
                        email
                     FROM admin_users
                     WHERE email = :email
                     LIMIT 1'
                );

            $stmt->execute([
                ':email' => $email,
            ]);

            $admin =
                $stmt->fetch(PDO::FETCH_ASSOC);


            /*
            |--------------------------------------------------------------------------
            | Compte trouvé
            |--------------------------------------------------------------------------
            |
            | On ne change PAS le message affiché à l'utilisateur.
            | Cela évite l'énumération des comptes.
            |
            */

            if ($admin) {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Token
                    |--------------------------------------------------------------------------
                    */

                    $token =
                        bin2hex(
                            random_bytes(32)
                        );

                    $tokenHash =
                        hash(
                            'sha256',
                            $token
                        );

                    $expiresAt =
                        (new DateTimeImmutable('+30 minutes'))
                            ->format('Y-m-d H:i:s');


                    /*
                    |--------------------------------------------------------------------------
                    | Transaction
                    |--------------------------------------------------------------------------
                    */

                    $pdo->beginTransaction();


                    /*
                    |--------------------------------------------------------------------------
                    | Suppression des anciens tokens
                    |--------------------------------------------------------------------------
                    */

                    $deleteStmt =
                        $pdo->prepare(
                            'DELETE FROM admin_password_resets
                             WHERE admin_id = :admin_id'
                        );

                    $deleteStmt->execute([
                        ':admin_id' =>
                            (int) $admin['id'],
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Nouveau token
                    |--------------------------------------------------------------------------
                    */

                    $insertStmt =
                        $pdo->prepare(
                            'INSERT INTO admin_password_resets (
                                admin_id,
                                token_hash,
                                expires_at
                             )
                             VALUES (
                                :admin_id,
                                :token_hash,
                                :expires_at
                             )'
                        );

                    $insertStmt->execute([
                        ':admin_id' =>
                            (int) $admin['id'],

                        ':token_hash' =>
                            $tokenHash,

                        ':expires_at' =>
                            $expiresAt,
                    ]);

                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | URL de réinitialisation
                    |--------------------------------------------------------------------------
                    */

                    $host =
                        (string) (
                            $_SERVER['HTTP_HOST']
                            ?? ''
                        );

                    $isLocal =
                        str_starts_with(
                            $host,
                            'localhost'
                        ) ||
                        str_starts_with(
                            $host,
                            '127.0.0.1'
                        );

                    $baseUrl =
                        $isLocal
                            ? 'http://' . $host
                            : 'https://olcreativestudio.fr';

                    $resetUrl =
                        $baseUrl
                        . '/admin/reset-password.php?token='
                        . urlencode($token);


                    /*
                    |--------------------------------------------------------------------------
                    | Email
                    |--------------------------------------------------------------------------
                    */

                    $subject =
                        'Réinitialisation de votre mot de passe — OL Creative Studio';

                    $body =
                        "Bonjour "
                        . $admin['username']
                        . ",\n\n"
                        . "Une demande de réinitialisation du mot de passe "
                        . "de votre espace administrateur OL Creative Studio "
                        . "a été effectuée.\n\n"
                        . "Utilisez le lien suivant pour définir "
                        . "un nouveau mot de passe :\n\n"
                        . $resetUrl
                        . "\n\n"
                        . "Ce lien est valable pendant 30 minutes.\n\n"
                        . "Si vous n'êtes pas à l'origine de cette demande, "
                        . "vous pouvez ignorer cet email.\n\n"
                        . "OL Creative Studio";


                    $mailSent =
                        sendMail(
                            (string) $admin['email'],
                            (string) $admin['username'],
                            $subject,
                            $body
                        );


                    /*
                    |--------------------------------------------------------------------------
                    | Erreur d'envoi
                    |--------------------------------------------------------------------------
                    |
                    | On log l'erreur côté serveur mais on ne révèle rien
                    | dans l'interface.
                    |
                    */

                    if (!$mailSent) {

                        error_log(
                            '[ADMIN PASSWORD RESET] '
                            . 'Unable to send reset email for admin ID '
                            . (int) $admin['id']
                        );
                    }

                } catch (Throwable $exception) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log(
                        '[ADMIN PASSWORD RESET] '
                        . $exception->getMessage()
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Réponse générique
            |--------------------------------------------------------------------------
            */

            $success =
                'Si cette adresse correspond à un compte administrateur, '
                . 'un lien de réinitialisation vient d’être envoyé.';

            $email = '';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Rotation CSRF
    |--------------------------------------------------------------------------
    */

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}

?>
<!DOCTYPE html>

<html lang="fr">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="robots"
        content="noindex, nofollow"
    >

    <title>
        Mot de passe oublié — OL Creative Studio
    </title>

    <link
        rel="icon"
        type="image/x-icon"
        href="/favicon.ico"
    >

    <link
        rel="stylesheet"
        href="/admin/assets/css/login.css"
    >

</head>

<body class="admin-login">

    <main class="admin-login__main">

        <section
            class="admin-login__card"
            aria-labelledby="forgot-title"
        >

            <div class="admin-login__brand">

                <span class="admin-login__eyebrow">
                    Administration
                </span>

                <h1 id="forgot-title">
                    Mot de passe oublié.
                </h1>

                <p>
                    Renseignez l’adresse email associée
                    à votre compte administrateur.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div
                    class="admin-alert admin-alert--error"
                    role="alert"
                >
                    <?= htmlspecialchars(
                        $error,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>


            <?php if ($success !== ''): ?>

                <div
                    class="admin-alert admin-alert--success"
                    role="status"
                >
                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>

            <?php endif; ?>


            <form
                method="POST"
                class="admin-login__form"
                novalidate
            >

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >


                <div class="admin-field">

                    <label for="email">
                        Adresse email
                    </label>

                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="<?= htmlspecialchars(
                            $email,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        autocomplete="email"
                        required
                        autofocus
                    >

                </div>


                <button
                    type="submit"
                    class="admin-login__submit"
                >
                    Envoyer le lien
                    <span aria-hidden="true">→</span>
                </button>

            </form>


            <a
                href="/admin/login.php"
                class="admin-login__back"
            >
                ← Retour à la connexion
            </a>

        </section>

    </main>

</body>

</html>
