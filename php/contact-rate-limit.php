<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Contact rate limit
|--------------------------------------------------------------------------
*/

function isContactRateLimited(
    PDO $pdo,
    string $ip,
    int $maxAttempts = 5,
    int $windowMinutes = 15
): bool {

    if ($ip === '') {
        return false;
    }

    $maxAttempts =
        max(1, $maxAttempts);

    $windowMinutes =
        max(1, min($windowMinutes, 1440));

    $sql = "
        SELECT COUNT(*)
        FROM contact_messages
        WHERE ip = :ip
        AND created_at >= DATE_SUB(
            NOW(),
            INTERVAL {$windowMinutes} MINUTE
        )
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':ip' => $ip,
    ]);

    return
        (int) $stmt->fetchColumn()
        >= $maxAttempts;
}