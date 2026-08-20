<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

$adminPageTitle = 'Modifier un projet';
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

$maxUploadSize =
    8 * 1024 * 1024;

$error = '';


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

    $decoded = json_decode(
        $value,
        true
    );

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

    $finfo =
        new finfo(FILEINFO_MIME_TYPE);

    $mimeType =
        $finfo->file(
            $file['tmp_name']
        );

    if (
        !is_string($mimeType) ||
        !isset(
            $allowedMimeTypes[$mimeType]
        )
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
        $directory
        . $filename;

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


function deleteUploadedFile(
    string $directory,
    ?string $filename
): void {

    if (!$filename) {
        return;
    }

    $basename =
        basename($filename);

    $path =
        $directory
        . $basename;

    if (is_file($path)) {
        unlink($path);
    }
}


/*
|--------------------------------------------------------------------------
| ID projet
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
        'Location: /admin/portfolio.php'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Projet
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT *
     FROM portfolio
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $id,
]);

$project =
    $stmt->fetch(
        PDO::FETCH_ASSOC
    );

if (!$project) {
    header(
        'Location: /admin/portfolio.php'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Galerie actuelle
|--------------------------------------------------------------------------
*/

$currentGallery = [];

if (
    !empty(
        $project['images_gallery']
    )
) {

    $decodedGallery =
        json_decode(
            $project['images_gallery'],
            true
        );

    if (is_array($decodedGallery)) {
        $currentGallery =
            array_values(
                array_filter(
                    $decodedGallery,
                    'is_string'
                )
            );
    }
}


/*
|--------------------------------------------------------------------------
| Traitement POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD']
    === 'POST'
) {

    $csrf =
        (string) (
            $_POST['csrf']
            ?? ''
        );

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
            trim(
                (string) (
                    $_POST['titre']
                    ?? ''
                )
            );

        $slug =
            createSlug(
                (string) (
                    $_POST['slug']
                    ?? ''
                )
            );

        if ($slug === '') {
            $slug =
                createSlug($title);
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
                (string) (
                    $_POST['client']
                    ?? ''
                )
            );

        $year =
            trim(
                (string) (
                    $_POST['annee']
                    ?? ''
                )
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
            isset(
                $_POST['featured']
            )
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
        }


        /*
        |--------------------------------------------------------------------------
        | Slug unique
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $slugStmt =
                $pdo->prepare(
                    'SELECT id
                     FROM portfolio
                     WHERE slug = :slug
                     AND id != :id
                     LIMIT 1'
                );

            $slugStmt->execute([
                ':slug' => $slug,
                ':id'   => $id,
            ]);

            if ($slugStmt->fetch()) {

                $error =
                    'Ce slug est déjà utilisé par un autre projet.';
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Mise à jour
        |--------------------------------------------------------------------------
        */

        if ($error === '') {

            $uploadedFiles = [];

            try {

                $coverFilename =
                    (string) (
                        $project['image']
                        ?? ''
                    );

                $gallery =
                    $currentGallery;


                /*
                |--------------------------------------------------------------------------
                | Nouvelle cover
                |--------------------------------------------------------------------------
                */

                if (
                    isset($_FILES['image']) &&
                    (
                        $_FILES['image']['error']
                        ?? UPLOAD_ERR_NO_FILE
                    ) !== UPLOAD_ERR_NO_FILE
                ) {

                    $newCover =
                        uploadImage(
                            $_FILES['image'],
                            $uploadDirectory,
                            $allowedMimeTypes,
                            $maxUploadSize
                        );

                    $uploadedFiles[] =
                        $newCover;

                    $coverFilename =
                        $newCover;
                }


                /*
                |--------------------------------------------------------------------------
                | Suppression galerie
                |--------------------------------------------------------------------------
                */

                $removedGallery =
                    $_POST['remove_gallery']
                    ?? [];

                if (
                    is_array(
                        $removedGallery
                    )
                ) {

                    $removedGallery =
                        array_map(
                            'basename',
                            array_filter(
                                $removedGallery,
                                'is_string'
                            )
                        );

                    $gallery =
                        array_values(
                            array_filter(
                                $gallery,
                                static function (
                                    string $filename
                                ) use (
                                    $removedGallery
                                ): bool {

                                    return !in_array(
                                        basename(
                                            $filename
                                        ),
                                        $removedGallery,
                                        true
                                    );
                                }
                            )
                        );
                }


                /*
                |--------------------------------------------------------------------------
                | Nouvelles images galerie
                |--------------------------------------------------------------------------
                */

                if (
                    isset(
                        $_FILES['gallery']
                    ) &&
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

                        $filename =
                            uploadImage(
                                $galleryFile,
                                $uploadDirectory,
                                $allowedMimeTypes,
                                $maxUploadSize
                            );

                        $gallery[] =
                            $filename;

                        $uploadedFiles[] =
                            $filename;
                    }
                }


                /*
                |--------------------------------------------------------------------------
                | UPDATE
                |--------------------------------------------------------------------------
                */

                $updateStmt =
                    $pdo->prepare(
                        'UPDATE portfolio
                         SET
                            titre = :titre,
                            slug = :slug,
                            meta_description = :meta_description,
                            client = :client,
                            annee = :annee,
                            role_projet = :role_projet,
                            description = :description,
                            contexte = :contexte,
                            objectif = :objectif,
                            solution = :solution,
                            resultats = :resultats,
                            technologies = :technologies,
                            services = :services,
                            image = :image,
                            images_gallery = :images_gallery,
                            url_demo = :url_demo,
                            featured = :featured,
                            ordre = :ordre,
                            categorie = :categorie
                         WHERE id = :id'
                    );

                $updateStmt->execute([
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

                    ':id' =>
                        $id,
                ]);


                /*
                |--------------------------------------------------------------------------
                | Nettoyage ancienne cover
                |--------------------------------------------------------------------------
                */

                if (
                    isset($newCover) &&
                    !empty($project['image']) &&
                    $project['image']
                        !== $newCover
                ) {

                    deleteUploadedFile(
                        $uploadDirectory,
                        $project['image']
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Nettoyage galerie supprimée
                |--------------------------------------------------------------------------
                */

                if (
                    isset($removedGallery) &&
                    is_array(
                        $removedGallery
                    )
                ) {

                    foreach (
                        $removedGallery
                        as $filename
                    ) {

                        deleteUploadedFile(
                            $uploadDirectory,
                            $filename
                        );
                    }
                }


                $_SESSION['csrf_token'] =
                    bin2hex(
                        random_bytes(32)
                    );

                header(
                    'Location: /admin/portfolio.php?updated=1'
                );

                exit;

            } catch (Throwable $exception) {

                foreach (
                    $uploadedFiles
                    as $uploadedFile
                ) {

                    deleteUploadedFile(
                        $uploadDirectory,
                        $uploadedFile
                    );
                }

                $error =
                    'Impossible de modifier le projet. '
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
    'titre' =>
        $_POST['titre']
        ?? $project['titre']
        ?? '',

    'slug' =>
        $_POST['slug']
        ?? $project['slug']
        ?? '',

    'meta_description' =>
        $_POST['meta_description']
        ?? $project['meta_description']
        ?? '',

    'client' =>
        $_POST['client']
        ?? $project['client']
        ?? '',

    'annee' =>
        $_POST['annee']
        ?? $project['annee']
        ?? '',

    'role_projet' =>
        $_POST['role_projet']
        ?? $project['role_projet']
        ?? '',

    'categorie' =>
        $_POST['categorie']
        ?? $project['categorie']
        ?? '',

    'description' =>
        $_POST['description']
        ?? $project['description']
        ?? '',

    'contexte' =>
        $_POST['contexte']
        ?? $project['contexte']
        ?? '',

    'objectif' =>
        $_POST['objectif']
        ?? $project['objectif']
        ?? '',

    'solution' =>
        $_POST['solution']
        ?? $project['solution']
        ?? '',

    'resultats' =>
        $_POST['resultats']
        ?? $project['resultats']
        ?? '',

    'url_demo' =>
        $_POST['url_demo']
        ?? $project['url_demo']
        ?? '',

    'ordre' =>
        $_POST['ordre']
        ?? $project['ordre']
        ?? 0,

    'services' =>
        $_POST['services']
        ?? $project['services']
        ?? '[]',

    'technologies' =>
        $_POST['technologies']
        ?? $project['technologies']
        ?? '[]',
];

$featuredChecked =
    $_SERVER['REQUEST_METHOD']
    === 'POST'
        ? isset(
            $_POST['featured']
        )
        : (
            (int) (
                $project['featured']
                ?? 0
            ) === 1
        );

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
        Modifier <?= htmlspecialchars(
            $project['titre'],
            ENT_QUOTES,
            'UTF-8'
        ) ?>
        — Administration
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
                        Modifier le projet.
                    </h1>

                    <p>
                        <?= htmlspecialchars(
                            $project['titre'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>

                </div>

                <a
                    href="/portfolio-details.php?id=<?= $id ?>"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="admin-button admin-button--secondary"
                >
                    Voir le projet ↗
                </a>

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


                <!-- 01 IDENTITÉ -->

                <section class="admin-form-section">

                    <div class="admin-form-section__header">

                        <span class="admin-eyebrow">
                            01
                        </span>

                        <div>
                            <h2>Identité du projet</h2>

                            <p>
                                Informations principales
                                de la réalisation.
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
                                    (string) $form['titre'],
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
                                    (string) $form['client'],
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
                                            $form['categorie']
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
                                    (string) $form['annee'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                        </div>


                        <div class="admin-field admin-field--full">

                            <label for="role_projet">
                                Rôle
                            </label>

                            <input
                                type="text"
                                id="role_projet"
                                name="role_projet"
                                value="<?= htmlspecialchars(
                                    (string) $form['role_projet'],
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
                            ><?= htmlspecialchars(
                                (string) $form['description'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>

                        </div>

                    </div>

                </section>


                <!-- 02 CASE STUDY -->

                <section class="admin-form-section">

                    <div class="admin-form-section__header">

                        <span class="admin-eyebrow">
                            02
                        </span>

                        <div>
                            <h2>Case study</h2>

                            <p>
                                Contexte, objectif,
                                solution et résultats.
                            </p>
                        </div>

                    </div>

                    <div class="admin-form-grid">

                        <?php

                        $caseFields = [
                            'contexte' =>
                                'Contexte',

                            'objectif' =>
                                'Objectif',

                            'solution' =>
                                'Solution',

                            'resultats' =>
                                'Résultats',
                        ];

                        ?>

                        <?php foreach (
                            $caseFields
                            as $fieldName => $label
                        ): ?>

                            <div class="admin-field">

                                <label
                                    for="<?= $fieldName ?>"
                                >
                                    <?= $label ?>
                                </label>

                                <textarea
                                    id="<?= $fieldName ?>"
                                    name="<?= $fieldName ?>"
                                ><?= htmlspecialchars(
                                    (string) $form[$fieldName],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?></textarea>

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>


                <!-- 03 EXPERTISE -->

                <section class="admin-form-section">

                    <div class="admin-form-section__header">

                        <span class="admin-eyebrow">
                            03
                        </span>

                        <div>
                            <h2>Expertise</h2>

                            <p>
                                Services et technologies.
                            </p>
                        </div>

                    </div>


                    <div class="admin-form-grid">

                        <?php

                        $tagFields = [
                            'services' =>
                                'Services',

                            'technologies' =>
                                'Technologies',
                        ];

                        ?>

                        <?php foreach (
                            $tagFields
                            as $fieldName => $label
                        ): ?>

                            <div
                                class="admin-field"
                                data-tag-field
                            >

                                <label>
                                    <?= $label ?>
                                </label>

                                <div class="admin-tag-input">

                                    <input
                                        type="text"
                                        placeholder="Ajouter..."
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
                                    name="<?= $fieldName ?>"
                                    value="<?= htmlspecialchars(
                                        (string) $form[$fieldName],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    data-tag-hidden
                                >

                            </div>

                        <?php endforeach; ?>

                    </div>

                </section>


                <!-- 04 MEDIAS -->

                <section class="admin-form-section">

                    <div class="admin-form-section__header">

                        <span class="admin-eyebrow">
                            04
                        </span>

                        <div>
                            <h2>Médias</h2>

                            <p>
                                Cover et galerie
                                du projet.
                            </p>
                        </div>

                    </div>


                    <div class="admin-form-grid">

                        <div class="admin-field">

                            <label>
                                Cover actuelle
                            </label>

                            <?php if (
                                !empty(
                                    $project['image']
                                )
                            ): ?>

                                <div class="admin-media-preview">

                                    <img
                                        src="/admin/uploads/creation/<?= htmlspecialchars(
                                            $project['image'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt=""
                                    >

                                </div>

                            <?php endif; ?>

                            <label for="image">
                                Remplacer la cover
                            </label>

                            <input
                                type="file"
                                id="image"
                                name="image"
                                accept="image/jpeg,image/png,image/webp"
                            >

                        </div>


                        <div class="admin-field">

                            <label>
                                Galerie actuelle
                            </label>

                            <?php if (
                                empty(
                                    $currentGallery
                                )
                            ): ?>

                                <span class="admin-field__help">
                                    Aucune image
                                    dans la galerie.
                                </span>

                            <?php else: ?>

                                <div class="admin-gallery-manager">

                                    <?php foreach (
                                        $currentGallery
                                        as $galleryImage
                                    ): ?>

                                        <label class="admin-gallery-media">

                                            <img
                                                src="/admin/uploads/creation/<?= htmlspecialchars(
                                                    $galleryImage,
                                                    ENT_QUOTES,
                                                    'UTF-8'
                                                ) ?>"
                                                alt=""
                                            >

                                            <span>
                                                <input
                                                    type="checkbox"
                                                    name="remove_gallery[]"
                                                    value="<?= htmlspecialchars(
                                                        basename(
                                                            $galleryImage
                                                        ),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>"
                                                >

                                                Supprimer
                                            </span>

                                        </label>

                                    <?php endforeach; ?>

                                </div>

                            <?php endif; ?>


                            <label for="gallery">
                                Ajouter des images
                            </label>

                            <input
                                type="file"
                                id="gallery"
                                name="gallery[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                            >

                        </div>

                    </div>

                </section>


                <!-- 05 SEO -->

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
                                Référencement et ordre.
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
                                value="<?= htmlspecialchars(
                                    (string) $form['slug'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                data-project-slug
                            >

                        </div>


                        <div class="admin-field">

                            <label for="url_demo">
                                URL du projet
                            </label>

                            <input
                                type="url"
                                id="url_demo"
                                name="url_demo"
                                value="<?= htmlspecialchars(
                                    (string) $form['url_demo'],
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
                                maxlength="255"
                            ><?= htmlspecialchars(
                                (string) $form['meta_description'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>

                        </div>


                        <div class="admin-field">

                            <label for="ordre">
                                Ordre
                            </label>

                            <input
                                type="number"
                                id="ordre"
                                name="ordre"
                                min="0"
                                value="<?= htmlspecialchars(
                                    (string) $form['ordre'],
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
                                    <?= $featuredChecked
                                        ? 'checked'
                                        : ''
                                    ?>
                                >

                                <span>
                                    Mettre en avant
                                </span>

                            </label>

                        </div>

                    </div>

                </section>


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
                        Enregistrer les modifications
                        <span aria-hidden="true">
                            →
                        </span>
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
