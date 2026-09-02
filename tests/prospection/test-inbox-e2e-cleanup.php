<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/db.php';

echo PHP_EOL;
echo '========================================' . PHP_EOL;
echo 'TEST E2E INBOX - CLEANUP' . PHP_EOL;
echo '========================================' . PHP_EOL;
echo PHP_EOL;

$source = 'inbox_e2e_test';

/*
|--------------------------------------------------------------------------
| Recherche des fixtures E2E
|--------------------------------------------------------------------------
*/

$statement = $pdo->prepare(
    '
    SELECT id
    FROM prospects
    WHERE source = :source
    ORDER BY id ASC
    '
);

$statement->execute([
    'source' => $source,
]);

$prospectIds = array_map(
    static fn (array $row): int =>
        (int) $row['id'],
    $statement->fetchAll(PDO::FETCH_ASSOC)
);

if ($prospectIds === []) {
    echo 'Aucune fixture E2E à supprimer.'
        . PHP_EOL;

    exit(0);
}

echo 'Fixtures trouvées : '
    . count($prospectIds)
    . PHP_EOL;

echo 'Prospect IDs : '
    . implode(', ', $prospectIds)
    . PHP_EOL;

echo PHP_EOL;

$pdo->beginTransaction();

try {
    foreach ($prospectIds as $prospectId) {
        /*
        |--------------------------------------------------------------------------
        | Réponses
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            '
            DELETE FROM prospect_replies
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Événements
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            '
            DELETE FROM prospect_events
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | E-mails
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            '
            DELETE FROM prospect_emails
            WHERE prospect_id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);

        /*
        |--------------------------------------------------------------------------
        | Prospect
        |--------------------------------------------------------------------------
        */

        $statement = $pdo->prepare(
            '
            DELETE FROM prospects
            WHERE id = :prospect_id
            '
        );

        $statement->execute([
            'prospect_id' =>
                $prospectId,
        ]);
    }

    $pdo->commit();

    echo 'Nettoyage E2E : OK'
        . PHP_EOL;

    echo PHP_EOL;

    echo '========================================'
        . PHP_EOL;

    echo 'FIXTURES SUPPRIMEES'
        . PHP_EOL;

    echo '========================================'
        . PHP_EOL;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
