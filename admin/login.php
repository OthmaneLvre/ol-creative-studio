<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-log.php';
require_once __DIR__ . '/includes/login-rate-limit.php';

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

$error = '';
$username = '';

$ipAddress =
    getLoginIp();

clearOldLoginAttempts($pdo);

$resetSuccess =
    isset($_GET['reset']) &&
    $_GET['reset'] === 'success';

$sessionExpired =
    isset($_GET['expired']) &&
    $_GET['expired'] === '1';

/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
    |--------------------------------------------------------------------------
    | CSRF
    |--------------------------------------------------------------------------
    */

    $csrf =
        $_POST['csrf']
        ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals(
            $_SESSION['csrf_token'],
            $csrf
        )
    ) {

        $error =
            'Requête invalide. Veuillez réessayer.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Identifiants
        |--------------------------------------------------------------------------
        */

        $username =
            trim(
                (string) (
                    $_POST['username']
                    ?? ''
                )
            );

        $password =
            (string) (
                $_POST['password']
                ?? ''
            );


        /*
        |--------------------------------------------------------------------------
        | Rate limiting
        |--------------------------------------------------------------------------
        */

        if (
            isLoginRateLimited(
                $pdo,
                $ipAddress
            )
        ) {

            $error =
                'Trop de tentatives de connexion. '
                . 'Veuillez réessayer dans quelques minutes.';

        } elseif (
            $username === '' ||
            $password === ''
        ) {

            $error =
                'Veuillez renseigner vos identifiants.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Administrateur
            |--------------------------------------------------------------------------
            */

            $stmt =
                $pdo->prepare(
                    'SELECT
                        id,
                        username,
                        password
                     FROM admin_users
                     WHERE username = :username
                     LIMIT 1'
                );

            $stmt->execute([
                ':username' =>
                    $username,
            ]);

            $admin =
                $stmt->fetch(
                    PDO::FETCH_ASSOC
                );


            /*
            |--------------------------------------------------------------------------
            | Connexion réussie
            |--------------------------------------------------------------------------
            */

            if (
                $admin &&
                password_verify(
                    $password,
                    $admin['password']
                )
            ) {

                /*
                |--------------------------------------------------------------------------
                | Protection fixation session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['admin_logged'] =
                    true;

                $_SESSION['admin_id'] =
                    (int) $admin['id'];

                $_SESSION['admin_name'] =
                    (string) $admin['username'];

                $_SESSION['admin_last_activity'] =
                    time();


                /*
                |--------------------------------------------------------------------------
                | Tentative réussie
                |--------------------------------------------------------------------------
                */

                recordLoginAttempt(
                    $pdo,
                    $ipAddress,
                    (string) $admin['username'],
                    true
                );


                /*
                |--------------------------------------------------------------------------
                | Reset des échecs de cette IP
                |--------------------------------------------------------------------------
                */

                $clearStmt =
                    $pdo->prepare(
                        'DELETE FROM admin_login_attempts
                         WHERE ip_address = :ip_address
                         AND success = 0'
                    );

                $clearStmt->execute([
                    ':ip_address' =>
                        $ipAddress,
                ]);


                /*
                |--------------------------------------------------------------------------
                | Log admin
                |--------------------------------------------------------------------------
                */

                writeAdminLog(
                    $pdo,
                    'auth.login_success',
                    'admin_user',
                    (int) $admin['id'],
                    (string) $admin['username']
                );


                /*
                |--------------------------------------------------------------------------
                | Nouveau CSRF
                |--------------------------------------------------------------------------
                */

                $_SESSION['csrf_token'] =
                    bin2hex(
                        random_bytes(32)
                    );

                header(
                    'Location: /admin/dashboard.php'
                );

                exit;
            }


            /*
            |--------------------------------------------------------------------------
            | Connexion échouée
            |--------------------------------------------------------------------------
            */

            recordLoginAttempt(
                $pdo,
                $ipAddress,
                $username,
                false
            );

            writeAdminLog(
                $pdo,
                'auth.login_failed',
                'admin_user',
                null,
                $username !== ''
                    ? $username
                    : null
            );

            $error =
                'Identifiants incorrects.';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Rotation CSRF après POST échoué
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
        Connexion administrateur — OL Creative Studio
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
            aria-labelledby="login-title"
        >

            <div class="admin-login__brand">

                <span class="admin-login__eyebrow">
                    Administration
                </span>

                <h1 id="login-title">
                    OL Creative Studio
                </h1>

                <p>
                    Connectez-vous pour accéder
                    à votre espace d’administration.
                </p>

            </div>

            <?php if ($resetSuccess): ?>

                <div
                    class="admin-alert admin-alert--success"
                    role="status"
                >
                    Votre mot de passe a bien été réinitialisé.
                    Vous pouvez maintenant vous connecter.
                </div>

            <?php endif; ?>

            <?php if ($sessionExpired): ?>

                <div
                    class="admin-alert admin-alert--error"
                    role="alert"
                >
                    Votre session a expiré après une période d’inactivité.
                    Veuillez vous reconnecter.
                </div>

            <?php endif; ?>

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

                    <label for="username">
                        Nom d’utilisateur
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        value="<?= htmlspecialchars(
                            $username,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        autocomplete="username"
                        required
                        autofocus
                    >

                </div>


                <div class="admin-field">

                    <label for="password">
                        Mot de passe
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <div class="admin-login__password-tools">
                    <a href="/admin/forgot-password.php">
                        Mot de passe oublié ?
                    </a>
                </div>

                <button
                    type="submit"
                    class="admin-login__submit"
                >
                    Se connecter
                    <span aria-hidden="true">→</span>
                </button>

            </form>

            <a
                href="/"
                class="admin-login__back"
            >
                ← Retour au site
            </a>

        </section>

    </main>

</body>

</html>
