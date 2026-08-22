<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| POST uniquement
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /admin/portfolio.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

$csrf =
    (string) ($_POST['csrf'] ?? '');

if (
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrf
    )
) {
    setFlash(
        'error',
        'La requête de suppression est invalide.'
    );

    header(
        'Location: /admin/portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| ID
|--------------------------------------------------------------------------
*/

$id =
    filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

if (!$id || $id < 1) {
    setFlash(
        'error',
        'Le projet demandé est invalide.'
    );

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
    'SELECT
        id,
        image,
        images_gallery
     FROM portfolio
     WHERE id = :id
     LIMIT 1'
);

$stmt->execute([
    ':id' => $id,
]);

$project =
    $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    setFlash(
        'error',
        'Le projet demandé est introuvable.'
    );

    header(
        'Location: /admin/portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Fichiers à supprimer
|--------------------------------------------------------------------------
*/

$filesToDelete = [];

if (!empty($project['image'])) {
    $filesToDelete[] =
        basename(
            (string) $project['image']
        );
}


if (!empty($project['images_gallery'])) {

    $gallery =
        json_decode(
            $project['images_gallery'],
            true
        );

    if (is_array($gallery)) {

        foreach ($gallery as $image) {

            if (!is_string($image)) {
                continue;
            }

            $image = trim($image);

            if ($image === '') {
                continue;
            }

            $filesToDelete[] =
                basename($image);
        }
    }
}


/*
|--------------------------------------------------------------------------
| Suppression BDD
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $deleteStmt =
        $pdo->prepare(
            'DELETE FROM portfolio
             WHERE id = :id'
        );

    $deleteStmt->execute([
        ':id' => $id,
    ]);

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Suppression fichiers
    |--------------------------------------------------------------------------
    |
    | On supprime les fichiers seulement après confirmation
    | de la suppression en BDD.
    |
    */

    $uploadDirectory =
        __DIR__ . '/uploads/creation/';

    foreach (
        array_unique($filesToDelete)
        as $filename
    ) {

        $path =
            $uploadDirectory
            . basename($filename);

        if (is_file($path)) {
            unlink($path);
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


    /*
    |--------------------------------------------------------------------------
    | Redirection
    |--------------------------------------------------------------------------
    */

    setFlash(
        'success',
        'Le projet a bien été supprimé.'
    );

    header(
        'Location: /admin/portfolio.php'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlash(
        'error',
        'Impossible de supprimer le projet.'
    );

    header(
        'Location: /admin/portfolio.php'
    );

    exit;
}
