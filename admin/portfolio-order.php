<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';


/*
|--------------------------------------------------------------------------
| POST uniquement
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: /admin/portfolio.php'
    );

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
        'La requête de réorganisation est invalide.'
    );

    header(
        'Location: /admin/portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Données
|--------------------------------------------------------------------------
*/

$id =
    filter_input(
        INPUT_POST,
        'id',
        FILTER_VALIDATE_INT
    );

$direction =
    (string) ($_POST['direction'] ?? '');

if (
    !$id ||
    $id < 1 ||
    !in_array(
        $direction,
        ['up', 'down'],
        true
    )
) {

    setFlash(
        'error',
        'Impossible de réorganiser ce projet.'
    );

    header(
        'Location: /admin/portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Projets ordonnés
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query(
    'SELECT
        id,
        ordre
     FROM portfolio
     ORDER BY
        ordre ASC,
        date_creation DESC,
        id DESC'
);

$projects =
    $stmt->fetchAll(PDO::FETCH_ASSOC);


$currentIndex = null;

foreach ($projects as $index => $project) {

    if ((int) $project['id'] === $id) {

        $currentIndex = $index;
        break;
    }
}


if ($currentIndex === null) {

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
| Projet voisin
|--------------------------------------------------------------------------
*/

$targetIndex =
    $direction === 'up'
        ? $currentIndex - 1
        : $currentIndex + 1;

if (
    !isset($projects[$targetIndex])
) {

    header(
        'Location: /admin/portfolio.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Réorganisation
|--------------------------------------------------------------------------
*/

$currentProject =
    $projects[$currentIndex];

$targetProject =
    $projects[$targetIndex];


try {

    $pdo->beginTransaction();

    /*
    |--------------------------------------------------------------------------
    | On normalise tout l'ordre
    |--------------------------------------------------------------------------
    */

    $orderedIds =
        array_map(
            static fn (array $project): int =>
                (int) $project['id'],
            $projects
        );

    [
        $orderedIds[$currentIndex],
        $orderedIds[$targetIndex]
    ] = [
        $orderedIds[$targetIndex],
        $orderedIds[$currentIndex]
    ];


    $updateStmt =
        $pdo->prepare(
            'UPDATE portfolio
             SET ordre = :ordre
             WHERE id = :id'
        );


    foreach ($orderedIds as $index => $projectId) {

        $updateStmt->execute([
            ':ordre' => $index + 1,
            ':id'    => $projectId,
        ]);
    }


    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Rotation CSRF
    |--------------------------------------------------------------------------
    */

    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );


    setFlash(
        'success',
        'L’ordre des projets a bien été mis à jour.'
    );

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    setFlash(
        'error',
        'Impossible de modifier l’ordre des projets.'
    );
}


header(
    'Location: /admin/portfolio.php'
);

exit;
