<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

$lockName = 'ol_prospection_scheduler';

$stmt = $pdo->prepare(
    'SELECT GET_LOCK(:lock_name, :timeout)'
);

$stmt->execute([
    'lock_name' => $lockName,
    'timeout' => 5,
]);

$acquired = (int) $stmt->fetchColumn();

if ($acquired !== 1) {
    throw new RuntimeException(
        'Impossible d’obtenir le verrou.'
    );
}

echo 'Verrou obtenu.' . PHP_EOL;

try {
    $stmt = $pdo->prepare(
        'SELECT IS_USED_LOCK(:lock_name)'
    );

    $stmt->execute([
        'lock_name' => $lockName,
    ]);

    $connectionId = $stmt->fetchColumn();

    if ($connectionId === false || $connectionId === null) {
        throw new RuntimeException(
            'Le verrou n’est pas actif.'
        );
    }

    echo 'Verrou actif sur la connexion MySQL : '
        . $connectionId
        . PHP_EOL;
} finally {
    $stmt = $pdo->prepare(
        'SELECT RELEASE_LOCK(:lock_name)'
    );

    $stmt->execute([
        'lock_name' => $lockName,
    ]);

    $released = (int) $stmt->fetchColumn();

    if ($released !== 1) {
        throw new RuntimeException(
            'Le verrou n’a pas pu être libéré.'
        );
    }
}

echo 'Verrou libéré.' . PHP_EOL;
echo 'Test du verrou scheduler OK.' . PHP_EOL;
