<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Mon compte';
$adminActivePage = 'account';

$error = '';


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

        $currentPassword =
            (string) ($_POST['current_password'] ?? '');

        $newPassword =
            (string) ($_POST['new_password'] ?? '');

        $confirmPassword =
            (string) ($_POST['confirm_password'] ?? '');


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($currentPassword === '') {

            $error =
                'Votre mot de passe actuel est obligatoire.';

        } elseif (strlen($newPassword) < 12) {

            $error =
                'Le nouveau mot de passe doit contenir au moins 12 caractères.';

        } elseif (
            !preg_match('/[A-Z]/', $newPassword) ||
            !preg_match('/[a-z]/', $newPassword) ||
            !preg_match('/[0-9]/', $newPassword)
        ) {

            $error =
                'Le nouveau mot de passe doit contenir une majuscule, une minuscule et un chiffre.';

        } elseif ($newPassword !== $confirmPassword) {

            $error =
                'La confirmation du nouveau mot de passe ne correspond pas.';
        }


        /*
        |--------------------------------------------------------------------------
        | Administrateur
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $stmt =
                $pdo->prepare(
                    'SELECT
                        id,
                        username,
                        password
                     FROM admin_users
                     WHERE id = :id
                     LIMIT 1'
                );

            $stmt->execute([
                ':id' =>
                    (int) $_SESSION['admin_id'],
            ]);

            $admin =
                $stmt->fetch(PDO::FETCH_ASSOC);


            if (
                !$admin ||
                !password_verify(
                    $currentPassword,
                    $admin['password']
                )
            ) {

                $error =
                    'Le mot de passe actuel est incorrect.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $passwordHash =
                password_hash(
                    $newPassword,
                    PASSWORD_DEFAULT
                );

            if ($passwordHash === false) {

                $error =
                    'Impossible de sécuriser le nouveau mot de passe.';

            } else {

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
                        (int) $admin['id'],
                ]);


                /*
                |--------------------------------------------------------------------------
                | Sécurité session
                |--------------------------------------------------------------------------
                */

                session_regenerate_id(true);

                $_SESSION['csrf_token'] =
                    bin2hex(
                        random_bytes(32)
                    );


                setFlash(
                    'success',
                    'Votre mot de passe a bien été modifié.'
                );

                header(
                    'Location: /admin/account.php'
                );

                exit;
            }
        }
    }
}


$flash = getFlash();

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
        Mon compte — Administration OL Creative Studio
    </title>

    <link
        rel="icon"
        href="/favicon.ico"
    >

    <link
        rel="stylesheet"
        href="/admin/assets/css/admin.css"
    >

</head>

<body class="admin-body">

<div class="admin-layout">

    <?php
    include __DIR__
        . '/partials/sidebar.php';
    ?>


    <main class="admin-main">

        <?php
        include __DIR__
            . '/partials/header.php';
        ?>


        <div class="admin-content">


            <section class="admin-page-heading">

                <div class="admin-page-heading__content">

                    <span class="admin-eyebrow">
                        Compte
                    </span>

                    <h1>
                        Sécurité.
                    </h1>

                    <p>
                        Gérez les informations sensibles
                        de votre compte administrateur.
                    </p>

                </div>

            </section>


            <?php if ($flash !== null): ?>

                <div
                    class="admin-alert admin-alert--<?= htmlspecialchars(
                        $flash['type'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    role="status"
                >
                    <?= htmlspecialchars(
                        $flash['message'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
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
                class="admin-form"
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


                <section class="admin-form-section">

                    <div class="admin-form-section__header">

                        <span class="admin-eyebrow">
                            01
                        </span>

                        <div>

                            <h2>
                                Mot de passe
                            </h2>

                            <p>
                                Modifiez votre mot de passe
                                administrateur.
                            </p>

                        </div>

                    </div>


                    <div class="admin-form-grid">


                        <div class="admin-field admin-field--full">

                            <label for="current_password">
                                Mot de passe actuel
                            </label>

                            <input
                                type="password"
                                id="current_password"
                                name="current_password"
                                autocomplete="current-password"
                                required
                            >

                        </div>


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
                                Confirmation
                            </label>

                            <input
                                type="password"
                                id="confirm_password"
                                name="confirm_password"
                                autocomplete="new-password"
                                required
                            >

                        </div>

                    </div>

                </section>


                <div class="admin-form-actions">

                    <button
                        type="submit"
                        class="admin-button admin-button--primary"
                    >
                        Modifier le mot de passe
                        <span aria-hidden="true">→</span>
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>


<?php
include_once __DIR__
    . '/partials/footer.php';
?>

</body>
</html>
