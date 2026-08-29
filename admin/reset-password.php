<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/admin-log.php';

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
$success = false;

$token =
    trim(
        (string) ($_GET['token'] ?? $_POST['token'] ?? '')
    );

$reset = null;


/*
|--------------------------------------------------------------------------
| Validation du token
|--------------------------------------------------------------------------
*/

if ($token !== '') {

    $tokenHash =
        hash(
            'sha256',
            $token
        );

    $stmt =
        $pdo->prepare(
            'SELECT
                apr.id,
                apr.admin_id,
                apr.expires_at,
                au.username
             FROM admin_password_resets apr
             INNER JOIN admin_users au
                ON au.id = apr.admin_id
             WHERE apr.token_hash = :token_hash
             AND apr.expires_at > NOW()
             LIMIT 1'
        );

    $stmt->execute([
        ':token_hash' =>
            $tokenHash,
    ]);

    $reset =
        $stmt->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| Token invalide ou expiré
|--------------------------------------------------------------------------
*/

if (!$reset) {

    $error =
        'Ce lien de réinitialisation est invalide ou a expiré.';
}


/*
|--------------------------------------------------------------------------
| Traitement POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $reset
) {

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

        $newPassword =
            (string) ($_POST['new_password'] ?? '');

        $confirmPassword =
            (string) ($_POST['confirm_password'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validation mot de passe
        |--------------------------------------------------------------------------
        */

        if (strlen($newPassword) < 12) {

            $error =
                'Le nouveau mot de passe doit contenir au moins 12 caractères.';

        } elseif (
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword)
        ) {

            $error =
                'Le nouveau mot de passe doit contenir une majuscule, une minuscule et un chiffre.';

        } elseif (
            $newPassword !== $confirmPassword
        ) {

            $error =
                'La confirmation du mot de passe ne correspond pas.';
        }


        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            try {

                $passwordHash =
                    password_hash(
                        $newPassword,
                        PASSWORD_DEFAULT
                    );

                if ($passwordHash === false) {
                    throw new RuntimeException(
                        'Impossible de sécuriser le nouveau mot de passe.'
                    );
                }


                $pdo->beginTransaction();


                /*
                |--------------------------------------------------------------------------
                | Nouveau mot de passe
                |--------------------------------------------------------------------------
                */

                $updateStmt =
                    $pdo->prepare(
                        'UPDATE admin_users
                         SET password = :password
                         WHERE id = :id'
                    );

                $updateStmt->execute([
                    ':password' =>
                        $passwordHash,

                    ':id' =>
                        (int) $reset['admin_id'],
                ]);




                /*
                |--------------------------------------------------------------------------
                | Suppression de tous les tokens de cet administrateur
                |--------------------------------------------------------------------------
                */

                $deleteStmt =
                    $pdo->prepare(
                        'DELETE FROM admin_password_resets
                         WHERE admin_id = :admin_id'
                    );

                $deleteStmt->execute([
                    ':admin_id' =>
                        (int) $reset['admin_id'],
                ]);


                $pdo->commit();

                writeAdminLog(
                    $pdo,
                    'auth.password_reset',
                    'admin_user',
                    (int) $reset['admin_id']
                );

                /*
                |--------------------------------------------------------------------------
                | Rotation CSRF
                |--------------------------------------------------------------------------
                */

                $_SESSION['csrf_token'] =
                    bin2hex(
                        random_bytes(32)
                    );


                /*
                |--------------------------------------------------------------------------
                | Redirection login
                |--------------------------------------------------------------------------
                */

                header(
                    'Location: /admin/login.php?reset=success'
                );

                exit;

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log(
                    '[ADMIN PASSWORD RESET] '
                    . $exception->getMessage()
                );

                $error =
                    'Impossible de modifier le mot de passe. Veuillez réessayer.';
            }
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
        Nouveau mot de passe — OL Creative Studio
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
            aria-labelledby="reset-title"
        >

            <div class="admin-login__brand">

                <span class="admin-login__eyebrow">
                    Administration
                </span>

                <h1 id="reset-title">
                    Nouveau mot de passe.
                </h1>

                <?php if ($reset): ?>

                    <p>
                        Définissez un nouveau mot de passe
                        pour votre compte administrateur.
                    </p>

                <?php else: ?>

                    <p>
                        Le lien utilisé ne permet plus
                        de modifier votre mot de passe.
                    </p>

                <?php endif; ?>

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


            <?php if ($reset): ?>

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

                    <input
                        type="hidden"
                        name="token"
                        value="<?= htmlspecialchars(
                            $token,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >


                    <div class="admin-field">

                        <label for="new_password">
                            Nouveau mot de passe
                        </label>

                        <input
                            type="password"
                            id="new_password"
                            name="new_password"
                            autocomplete="new-password"
                            required
                        >

                        <span class="admin-field__help">
                            12 caractères minimum,
                            avec majuscule, minuscule et chiffre.
                        </span>

                    </div>


                    <div class="admin-field">

                        <label for="confirm_password">
                            Confirmer le mot de passe
                        </label>

                        <input
                            type="password"
                            id="confirm_password"
                            name="confirm_password"
                            autocomplete="new-password"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="admin-login__submit"
                    >
                        Modifier le mot de passe
                        <span aria-hidden="true">→</span>
                    </button>

                </form>

            <?php else: ?>

                <a
                    href="/admin/forgot-password.php"
                    class="admin-login__submit"
                >
                    Demander un nouveau lien
                    <span aria-hidden="true">→</span>
                </a>

            <?php endif; ?>


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
