<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Nouveau projet';
$adminActivePage = 'portfolio';

$categories = [
    'figma'       => 'Maquettes Figma',
    'vitrine'     => 'Sites vitrines',
    'ecommerce'   => 'Boutiques en ligne',
    'application' => 'Applications web',
    'identite'    => 'Logos & identités visuelles',
];

$uploadDirectory =
    __DIR__ . '/uploads/creation/';

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

$maxUploadSize = 8 * 1024 * 1024;

$error = '';
$success = false;


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function createSlug(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    $converted = iconv(
        'UTF-8',
        'ASCII//TRANSLIT//IGNORE',
        $value
    );

    if ($converted !== false) {
        $value = $converted;
    }

    $value = strtolower($value);

    $value = preg_replace(
        '/[^a-z0-9]+/',
        '-',
        $value
    ) ?? '';

    return trim($value, '-');
}


function decodeJsonList(string $value): array
{
    if ($value === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    if (!is_array($decoded)) {
        return [];
    }

    $items = [];

    foreach ($decoded as $item) {

        if (!is_string($item)) {
            continue;
        }

        $item = trim($item);

        if ($item === '') {
            continue;
        }

        $items[] = $item;
    }

    return array_values(
        array_unique($items)
    );
}


function uploadImage(
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
            'Erreur pendant l’envoi du fichier.'
        );
    }

    if (
        (int) $file['size'] > $maxSize
    ) {
        throw new RuntimeException(
            'L’image dépasse la taille maximale autorisée.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file(
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
        bin2hex(random_bytes(16))
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
            'Impossible d’enregistrer l’image.'
        );
    }

    return $filename;
}


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

        $title =
            trim((string) ($_POST['titre'] ?? ''));

        $slug =
            createSlug(
                (string) ($_POST['slug'] ?? '')
            );

        if ($slug === '') {
            $slug = createSlug($title);
        }

        $metaDescription =
            trim(
                (string) (
                    $_POST['meta_description']
                    ?? ''
                )
            );

        $client =
            trim(
                (string) ($_POST['client'] ?? '')
            );

        $year =
            trim(
                (string) ($_POST['annee'] ?? '')
            );

        $role =
            trim(
                (string) (
                    $_POST['role_projet']
                    ?? ''
                )
            );

        $category =
            (string) (
                $_POST['categorie']
                ?? ''
            );

        $description =
            trim(
                (string) (
                    $_POST['description']
                    ?? ''
                )
            );

        $context =
            trim(
                (string) (
                    $_POST['contexte']
                    ?? ''
                )
            );

        $objective =
            trim(
                (string) (
                    $_POST['objectif']
                    ?? ''
                )
            );

        $solution =
            trim(
                (string) (
                    $_POST['solution']
                    ?? ''
                )
            );

        $results =
            trim(
                (string) (
                    $_POST['resultats']
                    ?? ''
                )
            );

        $urlDemo =
            trim(
                (string) (
                    $_POST['url_demo']
                    ?? ''
                )
            );

        $featured =
            isset($_POST['featured'])
                ? 1
                : 0;

        $order =
            max(
                0,
                (int) (
                    $_POST['ordre']
                    ?? 0
                )
            );

        $technologies =
            decodeJsonList(
                (string) (
                    $_POST['technologies']
                    ?? ''
                )
            );

        $services =
            decodeJsonList(
                (string) (
                    $_POST['services']
                    ?? ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | Validation
        |--------------------------------------------------------------------------
        */

        if ($title === '') {

            $error =
                'Le titre du projet est obligatoire.';

        } elseif (
            !array_key_exists(
                $category,
                $categories
            )
        ) {

            $error =
                'La catégorie sélectionnée est invalide.';

        } elseif (
            $year !== '' &&
            (
                !ctype_digit($year) ||
                (int) $year < 2000 ||
                (int) $year > 2100
            )
        ) {

            $error =
                'L’année renseignée est invalide.';

        } elseif (
            $urlDemo !== '' &&
            filter_var(
                $urlDemo,
                FILTER_VALIDATE_URL
            ) === false
        ) {

            $error =
                'L’URL du projet est invalide.';

        } elseif (
            empty($_FILES['image']) ||
            $_FILES['image']['error']
                === UPLOAD_ERR_NO_FILE
        ) {

            $error =
                'Une image de couverture est obligatoire.';

        }


        /*
        |--------------------------------------------------------------------------
        | Vérification slug
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $slugStmt = $pdo->prepare(
                'SELECT id
                 FROM portfolio
                 WHERE slug = :slug
                 LIMIT 1'
            );

            $slugStmt->execute([
                ':slug' => $slug,
            ]);

            if ($slugStmt->fetch()) {
                $error =
                    'Ce slug est déjà utilisé par un autre projet.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Upload + insertion
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $uploadedFiles = [];

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
                        'Impossible de créer le dossier d’upload.'
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Cover
                |--------------------------------------------------------------------------
                */

                $coverFilename = uploadImage(
                    $_FILES['image'],
                    $uploadDirectory,
                    $allowedMimeTypes,
                    $maxUploadSize
                );

                $uploadedFiles[] =
                    $coverFilename;


                /*
                |--------------------------------------------------------------------------
                | Galerie
                |--------------------------------------------------------------------------
                */

                $gallery = [];

                if (
                    isset($_FILES['gallery']) &&
                    is_array(
                        $_FILES['gallery']['name']
                        ?? null
                    )
                ) {

                    $galleryCount =
                        count(
                            $_FILES['gallery']['name']
                        );

                    for (
                        $index = 0;
                        $index < $galleryCount;
                        $index++
                    ) {

                        $galleryError =
                            $_FILES['gallery']['error'][$index]
                            ?? UPLOAD_ERR_NO_FILE;

                        if (
                            $galleryError
                            === UPLOAD_ERR_NO_FILE
                        ) {
                            continue;
                        }

                        $galleryFile = [
                            'name' =>
                                $_FILES['gallery']['name'][$index]
                                ?? '',

                            'type' =>
                                $_FILES['gallery']['type'][$index]
                                ?? '',

                            'tmp_name' =>
                                $_FILES['gallery']['tmp_name'][$index]
                                ?? '',

                            'error' =>
                                $galleryError,

                            'size' =>
                                $_FILES['gallery']['size'][$index]
                                ?? 0,
                        ];

                        $galleryFilename =
                            uploadImage(
                                $galleryFile,
                                $uploadDirectory,
                                $allowedMimeTypes,
                                $maxUploadSize
                            );

                        $gallery[] =
                            $galleryFilename;

                        $uploadedFiles[] =
                            $galleryFilename;
                    }
                }

                $pdo->beginTransaction();

                /*
                |--------------------------------------------------------------------------
                | Projet mis en avant
                |--------------------------------------------------------------------------
                */

                if ($featured === 1) {

                    $pdo->exec(
                        'UPDATE portfolio
                        SET featured = 0'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Insertion BDD
                |--------------------------------------------------------------------------
                */

                $stmt = $pdo->prepare(
                    'INSERT INTO portfolio (
                        titre,
                        slug,
                        meta_description,
                        client,
                        annee,
                        role_projet,
                        description,
                        contexte,
                        objectif,
                        solution,
                        resultats,
                        technologies,
                        services,
                        image,
                        images_gallery,
                        url_demo,
                        featured,
                        ordre,
                        categorie
                    )
                    VALUES (
                        :titre,
                        :slug,
                        :meta_description,
                        :client,
                        :annee,
                        :role_projet,
                        :description,
                        :contexte,
                        :objectif,
                        :solution,
                        :resultats,
                        :technologies,
                        :services,
                        :image,
                        :images_gallery,
                        :url_demo,
                        :featured,
                        :ordre,
                        :categorie
                    )'
                );

                $stmt->execute([
                    ':titre' =>
                        $title,

                    ':slug' =>
                        $slug,

                    ':meta_description' =>
                        $metaDescription !== ''
                            ? $metaDescription
                            : null,

                    ':client' =>
                        $client !== ''
                            ? $client
                            : null,

                    ':annee' =>
                        $year !== ''
                            ? (int) $year
                            : null,

                    ':role_projet' =>
                        $role !== ''
                            ? $role
                            : null,

                    ':description' =>
                        $description !== ''
                            ? $description
                            : null,

                    ':contexte' =>
                        $context !== ''
                            ? $context
                            : null,

                    ':objectif' =>
                        $objective !== ''
                            ? $objective
                            : null,

                    ':solution' =>
                        $solution !== ''
                            ? $solution
                            : null,

                    ':resultats' =>
                        $results !== ''
                            ? $results
                            : null,

                    ':technologies' =>
                        json_encode(
                            $technologies,
                            JSON_UNESCAPED_UNICODE
                        ),

                    ':services' =>
                        json_encode(
                            $services,
                            JSON_UNESCAPED_UNICODE
                        ),

                    ':image' =>
                        $coverFilename,

                    ':images_gallery' =>
                        json_encode(
                            $gallery,
                            JSON_UNESCAPED_UNICODE
                        ),

                    ':url_demo' =>
                        $urlDemo !== ''
                            ? $urlDemo
                            : null,

                    ':featured' =>
                        $featured,

                    ':ordre' =>
                        $order,

                    ':categorie' =>
                        $category,
                ]);

                $pdo->commit();

                /*
                |--------------------------------------------------------------------------
                | Nouveau token + redirection
                |--------------------------------------------------------------------------
                */

                $_SESSION['csrf_token'] =
                    bin2hex(random_bytes(32));

                header(
                    'Location: /admin/portfolio.php?created=1'
                );

                exit;

            } catch (Throwable $exception) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                /*
                |--------------------------------------------------------------------------
                | Nettoyage des fichiers en cas d’échec
                |--------------------------------------------------------------------------
                */

                foreach (
                    $uploadedFiles
                    as $uploadedFile
                ) {

                    $path =
                        $uploadDirectory
                        . $uploadedFile;

                    if (is_file($path)) {
                        unlink($path);
                    }
                }

                $error =
                    'Impossible d’ajouter le projet. '
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
        Nouveau projet — Administration OL Creative Studio
    </title>

    <link
        rel="icon"
        type="image/x-icon"
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
                            href="/admin/portfolio.php"
                            class="admin-text-link"
                        >
                            ← Retour au portfolio
                        </a>

                        <span class="admin-eyebrow">
                            Portfolio
                        </span>

                        <h1>
                            Nouveau projet.
                        </h1>

                        <p>
                            Ajoutez une nouvelle réalisation
                            à votre portfolio.
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
                    data-portfolio-form
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


                    <!-- =================================================
                         IDENTITÉ
                         ================================================= -->

                    <section class="admin-form-section">

                        <div class="admin-form-section__header">

                            <span class="admin-eyebrow">
                                01
                            </span>

                            <div>

                                <h2>
                                    Identité du projet
                                </h2>

                                <p>
                                    Informations principales
                                    affichées dans le portfolio.
                                </p>

                            </div>

                        </div>


                        <div class="admin-form-grid">


                            <div class="admin-field">

                                <label for="titre">
                                    Titre *
                                </label>

                                <input
                                    type="text"
                                    id="titre"
                                    name="titre"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['titre']
                                            ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                    data-project-title
                                >

                            </div>


                            <div class="admin-field">

                                <label for="client">
                                    Client
                                </label>

                                <input
                                    type="text"
                                    id="client"
                                    name="client"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['client']
                                            ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
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
                                        $categories
                                        as $value => $label
                                    ): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $value,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            <?= (
                                                ($_POST['categorie'] ?? '')
                                                === $value
                                            )
                                                ? 'selected'
                                                : ''
                                            ?>
                                        >
                                            <?= htmlspecialchars(
                                                $label,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </div>


                            <div class="admin-field">

                                <label for="annee">
                                    Année
                                </label>

                                <input
                                    type="number"
                                    id="annee"
                                    name="annee"
                                    min="2000"
                                    max="2100"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['annee']
                                            ?? date('Y')
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            </div>


                            <div class="admin-field admin-field--full">

                                <label for="role_projet">
                                    Rôle sur le projet
                                </label>

                                <input
                                    type="text"
                                    id="role_projet"
                                    name="role_projet"
                                    placeholder="UX/UI, développement full-stack et mise en production"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['role_projet']
                                            ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            </div>


                            <div class="admin-field admin-field--full">

                                <label for="description">
                                    Description courte
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    rows="4"
                                    placeholder="Résumé du projet présenté dans le hero..."
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['description']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>

                        </div>

                    </section>


                    <!-- =================================================
                         CASE STUDY
                         ================================================= -->

                    <section class="admin-form-section">

                        <div class="admin-form-section__header">

                            <span class="admin-eyebrow">
                                02
                            </span>

                            <div>

                                <h2>
                                    Case study
                                </h2>

                                <p>
                                    Le contexte, l’objectif,
                                    la solution et les résultats.
                                </p>

                            </div>

                        </div>


                        <div class="admin-form-grid">


                            <div class="admin-field">

                                <label for="contexte">
                                    Contexte
                                </label>

                                <textarea
                                    id="contexte"
                                    name="contexte"
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['contexte']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>


                            <div class="admin-field">

                                <label for="objectif">
                                    Objectif
                                </label>

                                <textarea
                                    id="objectif"
                                    name="objectif"
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['objectif']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>


                            <div class="admin-field">

                                <label for="solution">
                                    Solution
                                </label>

                                <textarea
                                    id="solution"
                                    name="solution"
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['solution']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>


                            <div class="admin-field">

                                <label for="resultats">
                                    Résultats
                                </label>

                                <textarea
                                    id="resultats"
                                    name="resultats"
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['resultats']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>

                        </div>

                    </section>


                    <!-- =================================================
                         SERVICES + TECHNOLOGIES
                         ================================================= -->

                    <section class="admin-form-section">

                        <div class="admin-form-section__header">

                            <span class="admin-eyebrow">
                                03
                            </span>

                            <div>

                                <h2>
                                    Expertise
                                </h2>

                                <p>
                                    Services réalisés et technologies
                                    utilisées.
                                </p>

                            </div>

                        </div>


                        <div class="admin-form-grid">


                            <div
                                class="admin-field"
                                data-tag-field
                                data-target="services"
                            >

                                <label>
                                    Services
                                </label>

                                <div class="admin-tag-input">

                                    <input
                                        type="text"
                                        placeholder="Ex : UX/UI"
                                        data-tag-input
                                    >

                                    <button
                                        type="button"
                                        data-tag-add
                                    >
                                        Ajouter
                                    </button>

                                </div>

                                <div
                                    class="admin-tags"
                                    data-tag-list
                                ></div>

                                <input
                                    type="hidden"
                                    name="services"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['services']
                                            ?? '[]'
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-tag-hidden
                                >

                            </div>


                            <div
                                class="admin-field"
                                data-tag-field
                                data-target="technologies"
                            >

                                <label>
                                    Technologies
                                </label>

                                <div class="admin-tag-input">

                                    <input
                                        type="text"
                                        placeholder="Ex : PHP"
                                        data-tag-input
                                    >

                                    <button
                                        type="button"
                                        data-tag-add
                                    >
                                        Ajouter
                                    </button>

                                </div>

                                <div
                                    class="admin-tags"
                                    data-tag-list
                                ></div>

                                <input
                                    type="hidden"
                                    name="technologies"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['technologies']
                                            ?? '[]'
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-tag-hidden
                                >

                            </div>

                        </div>

                    </section>


                    <!-- =================================================
                         MÉDIAS
                         ================================================= -->

                    <section class="admin-form-section">

                        <div class="admin-form-section__header">

                            <span class="admin-eyebrow">
                                04
                            </span>

                            <div>

                                <h2>
                                    Médias
                                </h2>

                                <p>
                                    Cover principale et galerie
                                    de la case study.
                                </p>

                            </div>

                        </div>


                        <div class="admin-form-grid">


                            <div class="admin-field">

                                <label for="image">
                                    Cover principale *
                                </label>

                                <input
                                    type="file"
                                    id="image"
                                    name="image"
                                    accept="image/jpeg,image/png,image/webp"
                                    required
                                >

                                <span class="admin-field__help">
                                    JPG, PNG ou WebP · 8 Mo maximum.
                                </span>

                            </div>


                            <div class="admin-field">

                                <label for="gallery">
                                    Galerie
                                </label>

                                <input
                                    type="file"
                                    id="gallery"
                                    name="gallery[]"
                                    accept="image/jpeg,image/png,image/webp"
                                    multiple
                                >

                                <span class="admin-field__help">
                                    Plusieurs fichiers peuvent
                                    être sélectionnés.
                                </span>

                            </div>

                        </div>

                    </section>


                    <!-- =================================================
                         PUBLICATION + SEO
                         ================================================= -->

                    <section class="admin-form-section">

                        <div class="admin-form-section__header">

                            <span class="admin-eyebrow">
                                05
                            </span>

                            <div>

                                <h2>
                                    Publication & SEO
                                </h2>

                                <p>
                                    URL, référencement et ordre
                                    d’affichage.
                                </p>

                            </div>

                        </div>


                        <div class="admin-form-grid">


                            <div class="admin-field">

                                <label for="slug">
                                    Slug
                                </label>

                                <input
                                    type="text"
                                    id="slug"
                                    name="slug"
                                    placeholder="below-dreams"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['slug']
                                            ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-project-slug
                                >

                                <span class="admin-field__help">
                                    Généré automatiquement
                                    si laissé vide.
                                </span>

                            </div>


                            <div class="admin-field">

                                <label for="url_demo">
                                    URL du projet
                                </label>

                                <input
                                    type="url"
                                    id="url_demo"
                                    name="url_demo"
                                    placeholder="https://..."
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['url_demo']
                                            ?? ''
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            </div>


                            <div class="admin-field admin-field--full">

                                <label for="meta_description">
                                    Meta description
                                </label>

                                <textarea
                                    id="meta_description"
                                    name="meta_description"
                                    rows="3"
                                    maxlength="255"
                                    placeholder="Description SEO du projet..."
                                ><?= htmlspecialchars(
                                    (string) (
                                        $_POST['meta_description']
                                        ?? ''
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>


                            <div class="admin-field">

                                <label for="ordre">
                                    Ordre d’affichage
                                </label>

                                <input
                                    type="number"
                                    id="ordre"
                                    name="ordre"
                                    min="0"
                                    value="<?= htmlspecialchars(
                                        (string) (
                                            $_POST['ordre']
                                            ?? '0'
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                            </div>


                            <div class="admin-field">

                                <label class="admin-checkbox">

                                    <input
                                        type="checkbox"
                                        name="featured"
                                        value="1"
                                        <?= isset(
                                            $_POST['featured']
                                        )
                                            ? 'checked'
                                            : ''
                                        ?>
                                    >

                                    <span>
                                        Mettre ce projet en avant
                                    </span>

                                </label>

                            </div>

                        </div>

                    </section>


                    <!-- =================================================
                         ACTIONS
                         ================================================= -->

                    <div class="admin-form-actions">

                        <a
                            href="/admin/portfolio.php"
                            class="admin-button admin-button--secondary"
                        >
                            Annuler
                        </a>

                        <button
                            type="submit"
                            class="admin-button admin-button--primary"
                        >
                            Créer le projet
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
