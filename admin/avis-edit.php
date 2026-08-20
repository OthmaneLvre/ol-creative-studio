<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Modifier un avis';
$adminActivePage = 'avis';

$categories = [
    'Site vitrine',
    'Identité visuelle',
    'Maquettes Figma',
    'E-commerce',
    'Application Web & Mobile',
    'Autre',
];

$uploadDirectory =
    __DIR__ . '/uploads/avatars/';

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$maxUploadSize =
    4 * 1024 * 1024;

$error = '';


/*
|--------------------------------------------------------------------------
| Helper upload
|--------------------------------------------------------------------------
*/

function uploadAvatar(
    array $file,
    string $directory,
    array $allowedMimeTypes,
    int $maxSize
): string {

    if (
        !isset(
            $file['error'],
            $file['tmp_name'],
            $file['size']
        )
    ) {
        throw new RuntimeException(
            'Fichier invalide.'
        );
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'Erreur pendant l’envoi de l’avatar.'
        );
    }

    if ((int) $file['size'] > $maxSize) {
        throw new RuntimeException(
            'L’avatar dépasse 4 Mo.'
        );
    }

    $finfo =
        new finfo(FILEINFO_MIME_TYPE);

    $mimeType =
        $finfo->file(
            $file['tmp_name']
        );

    if (
        !is_string($mimeType) ||
        !isset($allowedMimeTypes[$mimeType])
    ) {
        throw new RuntimeException(
            'Format d’image non autorisé.'
        );
    }

    $extension =
        $allowedMimeTypes[$mimeType];

    $filename =
        'avatar_'
        . bin2hex(random_bytes(12))
        . '.'
        . $extension;

    $destination =
        $directory . $filename;

    if (
        !move_uploaded_file(
            $file['tmp_name'],
            $destination
        )
    ) {
        throw new RuntimeException(
            'Impossible d’enregistrer l’avatar.'
        );
    }

    return $filename;
}


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id =
    filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT
    );

if (!$id || $id < 1) {
    header(
        'Location: /admin/avis.php'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Avis
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT *
     FROM avis
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $id,
]);

$review =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$review) {
    header(
        'Location: /admin/avis.php'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Traitement POST
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

        $name =
            trim(
                (string) ($_POST['nom'] ?? '')
            );

        $category =
            trim(
                (string) ($_POST['categorie'] ?? '')
            );

        $comment =
            trim(
                (string) ($_POST['commentaire'] ?? '')
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($name === '') {

            $error =
                'Le nom du client est obligatoire.';

        } elseif (
            !in_array(
                $category,
                $categories,
                true
            )
        ) {

            $error =
                'La catégorie sélectionnée est invalide.';

        } elseif ($comment === '') {

            $error =
                'Le témoignage est obligatoire.';

        } elseif (
            mb_strlen($comment) > 2000
        ) {

            $error =
                'Le témoignage est trop long.';
        }


        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $newAvatar = null;

            try {

                $avatarFilename =
                    $review['avatar']
                    ?: null;


                /*
                |--------------------------------------------------------------------------
                | Remplacement avatar
                |--------------------------------------------------------------------------
                */

                if (
                    isset($_FILES['avatar']) &&
                    (
                        $_FILES['avatar']['error']
                        ?? UPLOAD_ERR_NO_FILE
                    ) !== UPLOAD_ERR_NO_FILE
                ) {

                    $newAvatar =
                        uploadAvatar(
                            $_FILES['avatar'],
                            $uploadDirectory,
                            $allowedMimeTypes,
                            $maxUploadSize
                        );

                    $avatarFilename =
                        $newAvatar;
                }


                /*
                |--------------------------------------------------------------------------
                | Suppression avatar demandée
                |--------------------------------------------------------------------------
                */

                $removeAvatar =
                    isset($_POST['remove_avatar']);

                if ($removeAvatar) {
                    $avatarFilename = null;
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                $updateStmt =
                    $pdo->prepare(
                        'UPDATE avis
                         SET
                            nom = :nom,
                            categorie = :categorie,
                            commentaire = :commentaire,
                            avatar = :avatar
                         WHERE id = :id'
                    );

                $updateStmt->execute([
                    ':nom' =>
                        $name,

                    ':categorie' =>
                        $category,

                    ':commentaire' =>
                        $comment,

                    ':avatar' =>
                        $avatarFilename,

                    ':id' =>
                        $id,
                ]);


                /*
                |--------------------------------------------------------------------------
                | Nettoyage ancien avatar
                |--------------------------------------------------------------------------
                */

                if (
                    !empty($review['avatar']) &&
                    (
                        $removeAvatar ||
                        $newAvatar !== null
                    )
                ) {

                    $oldPath =
                        $uploadDirectory
                        . basename(
                            (string) $review['avatar']
                        );

                    if (is_file($oldPath)) {
                        unlink($oldPath);
                    }
                }


                $_SESSION['csrf_token'] =
                    bin2hex(
                        random_bytes(32)
                    );

                header(
                    'Location: /admin/avis.php?updated=1'
                );

                exit;

            } catch (Throwable $exception) {

                /*
                |--------------------------------------------------------------------------
                | Nettoyage nouvel avatar si échec
                |--------------------------------------------------------------------------
                */

                if ($newAvatar !== null) {

                    $path =
                        $uploadDirectory
                        . basename($newAvatar);

                    if (is_file($path)) {
                        unlink($path);
                    }
                }

                $error =
                    'Impossible de modifier l’avis. '
                    . $exception->getMessage();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Valeurs formulaire
|--------------------------------------------------------------------------
*/

$form = [
    'nom' =>
        $_POST['nom']
        ?? $review['nom']
        ?? '',

    'categorie' =>
        $_POST['categorie']
        ?? $review['categorie']
        ?? '',

    'commentaire' =>
        $_POST['commentaire']
        ?? $review['commentaire']
        ?? '',
];

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
        Modifier un avis — Administration OL Creative Studio
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
    include_once __DIR__ . '/partials/sidebar.php';
    ?>


    <main class="admin-main">

        <?php
        include_once __DIR__ . '/partials/header.php';
        ?>


        <div class="admin-content">

            <section class="admin-page-heading">

                <div class="admin-page-heading__content">

                    <a
                        href="/admin/avis.php"
                        class="admin-text-link"
                    >
                        ← Retour aux avis
                    </a>

                    <span class="admin-eyebrow">
                        Témoignages
                    </span>

                    <h1>
                        Modifier l’avis.
                    </h1>

                    <p>
                        <?= htmlspecialchars(
                            (string) $review['nom'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                </div>

            </section>


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
                enctype="multipart/form-data"
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
                                Informations client
                            </h2>

                            <p>
                                Modifiez le témoignage
                                et les informations associées.
                            </p>

                        </div>

                    </div>


                    <div class="admin-form-grid">


                        <div class="admin-field">

                            <label for="nom">
                                Nom du client *
                            </label>

                            <input
                                type="text"
                                id="nom"
                                name="nom"
                                value="<?= htmlspecialchars(
                                    (string) $form['nom'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >

                        </div>


                        <div class="admin-field">

                            <label for="categorie">
                                Catégorie *
                            </label>

                            <select
                                id="categorie"
                                name="categorie"
                                required
                            >

                                <?php foreach ($categories as $item): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $item,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        <?= (
                                            $form['categorie']
                                            === $item
                                        )
                                            ? 'selected'
                                            : ''
                                        ?>
                                    >
                                        <?= htmlspecialchars(
                                            $item,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="admin-field admin-field--full">

                            <label for="commentaire">
                                Témoignage *
                            </label>

                            <textarea
                                id="commentaire"
                                name="commentaire"
                                maxlength="2000"
                                required
                            ><?= htmlspecialchars(
                                (string) $form['commentaire'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>

                        </div>

                    </div>

                </section>


                <section class="admin-form-section">

                    <div class="admin-form-section__header">

                        <span class="admin-eyebrow">
                            02
                        </span>

                        <div>

                            <h2>
                                Avatar
                            </h2>

                            <p>
                                Gérez la photo associée au client.
                            </p>

                        </div>

                    </div>


                    <div class="admin-form-grid">

                        <div class="admin-field admin-field--full">


                            <?php if (!empty($review['avatar'])): ?>

                                <div class="admin-avatar-editor">

                                    <div class="admin-avatar-editor__preview">

                                        <img
                                            src="/admin/uploads/avatars/<?= htmlspecialchars(
                                                (string) $review['avatar'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            alt=""
                                        >

                                    </div>


                                    <label class="admin-checkbox">

                                        <input
                                            type="checkbox"
                                            name="remove_avatar"
                                            value="1"
                                        >

                                        <span>
                                            Supprimer l’avatar actuel
                                        </span>

                                    </label>

                                </div>

                            <?php else: ?>

                                <span class="admin-field__help">
                                    Aucun avatar actuellement.
                                </span>

                            <?php endif; ?>


                            <label for="avatar">
                                Remplacer l’avatar
                            </label>

                            <input
                                type="file"
                                id="avatar"
                                name="avatar"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <span class="admin-field__help">
                                JPG, PNG ou WebP · 4 Mo maximum.
                            </span>

                        </div>

                    </div>

                </section>


                <div class="admin-form-actions">

                    <a
                        href="/admin/avis.php"
                        class="admin-button admin-button--secondary"
                    >
                        Annuler
                    </a>

                    <button
                        type="submit"
                        class="admin-button admin-button--primary"
                    >
                        Enregistrer les modifications
                        <span aria-hidden="true">→</span>
                    </button>

                </div>

            </form>

        </div>

    </main>

</div>


<?php
include_once __DIR__ . '/partials/footer.php';
?>

</body>
</html>
