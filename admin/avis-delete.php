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
        'Location: /admin/avis.php'
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
    header(
        'Location: /admin/avis.php?error=csrf'
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
    header(
        'Location: /admin/avis.php?error=invalid'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Avis
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare(
    'SELECT
        id,
        avatar
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
        'Location: /admin/avis.php?error=notfound'
    );
    exit;
}


/*
|--------------------------------------------------------------------------
| Suppression
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    $deleteStmt =
        $pdo->prepare(
            'DELETE FROM avis
             WHERE id = :id'
        );

    $deleteStmt->execute([
        ':id' => $id,
    ]);

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | Avatar
    |--------------------------------------------------------------------------
    */

    if (!empty($review['avatar'])) {

        $avatarPath =
            __DIR__
            . '/uploads/avatars/'
            . basename(
                (string) $review['avatar']
            );

        if (is_file($avatarPath)) {
            unlink($avatarPath);
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


    header(
        'Location: /admin/avis.php?deleted=1'
    );

    exit;

} catch (Throwable $exception) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header(
        'Location: /admin/avis.php?error=delete'
    );

    exit;
}
