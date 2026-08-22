<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Nouvel avis';
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
| Helper upload avatar
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

    if (
        $file['error'] !== UPLOAD_ERR_OK
    ) {
        throw new RuntimeException(
            'Erreur pendant l’envoi de l’avatar.'
        );
    }

    if (
        (int) $file['size'] > $maxSize
    ) {
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
        | Upload + insertion
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $avatarFilename = null;

            try {

                if (
                    !is_dir($uploadDirectory) &&
                    !mkdir(
                        $uploadDirectory,
                        0755,
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Impossible de créer le dossier des avatars.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Avatar optionnel
                |--------------------------------------------------------------------------
                */

                if (
                    isset($_FILES['avatar']) &&
                    (
                        $_FILES['avatar']['error']
                        ?? UPLOAD_ERR_NO_FILE
                    ) !== UPLOAD_ERR_NO_FILE
                ) {

                    $avatarFilename =
                        uploadAvatar(
                            $_FILES['avatar'],
                            $uploadDirectory,
                            $allowedMimeTypes,
                            $maxUploadSize
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | BDD
                |--------------------------------------------------------------------------
                */

                $stmt =
                    $pdo->prepare(
                        'INSERT INTO avis (
                            nom,
                            categorie,
                            commentaire,
                            avatar,
                            statut
                        )
                        VALUES (
                            :nom,
                            :categorie,
                            :commentaire,
                            :avatar,
                            :statut
                        )'
                    );

                $stmt->execute([
                    ':nom' =>
                        $name,

                    ':categorie' =>
                        $category,

                    ':commentaire' =>
                        $comment,

                    ':avatar' =>
                        $avatarFilename,

                    ':statut' =>
                        'validé',
                ]);


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
                | Redirection
                |--------------------------------------------------------------------------
                */
                setFlash(
                    'success',
                    "L'avis client a bien été ajouté."
                );

                header(
                    'Location: /admin/avis.php'
                );

                exit;

            } catch (Throwable $exception) {

                if (
                    $avatarFilename !== null
                ) {

                    $path =
                        $uploadDirectory
                        . basename(
                            $avatarFilename
                        );

                    if (is_file($path)) {
                        unlink($path);
                    }
                }

                $error =
                    'Impossible d’ajouter l’avis. '
                    . $exception->getMessage();
            }
        }
    }
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
        Nouvel avis — Administration OL Creative Studio
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


                <!-- =====================================================
                     HEADER
                     ===================================================== -->

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
                            Nouvel avis.
                        </h1>

                        <p>
                            Ajoutez un nouveau témoignage client.
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


                <!-- =====================================================
                     FORM
                     ===================================================== -->

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
                                    Identité du client et contexte
                                    du témoignage.
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
                                        (string) (
                                            $_POST['nom']
                                            ?? ''
                                        ),
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

                                    <?php foreach (
                                        $categories as $item
                                    ): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $item,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            <?= (
                                                ($_POST['categorie'] ?? '')
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
                                    placeholder="Le témoignage du client..."
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['commentaire']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>


                            <div class="admin-field admin-field--full">

                                <label for="avatar">
                                    Avatar du client
                                </label>

                                <input
                                    type="file"
                                    id="avatar"
                                    name="avatar"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <span class="admin-field__help">
                                    Optionnel · JPG, PNG ou WebP · 4 Mo maximum.
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
                            Ajouter l’avis
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
