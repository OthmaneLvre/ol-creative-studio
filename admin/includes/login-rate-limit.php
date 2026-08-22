<?php

declare(strict_types=1);


function getLoginIp(): string
{
    return isset($_SERVER['REMOTE_ADDR'])
        ? (string) $_SERVER['REMOTE_ADDR']
        : 'unknown';
}


function isLoginRateLimited(
    PDO $pdo,
    string $ipAddress,
    int $maxAttempts = 5,
    int $windowMinutes = 15
): bool {

    $stmt =
        $pdo->prepare(
            'SELECT COUNT(*)
             FROM admin_login_attempts
             WHERE ip_address = :ip_address
             AND success = 0
             AND attempted_at >= DATE_SUB(
                NOW(),
                INTERVAL :window_minutes MINUTE
             )'
        );

    $stmt->bindValue(
        ':ip_address',
        $ipAddress,
        PDO::PARAM_STR
    );

    $stmt->bindValue(
        ':window_minutes',
        $windowMinutes,
        PDO::PARAM_INT
    );

    $stmt->execute();

    return (int) $stmt->fetchColumn()
        >= $maxAttempts;
}


function recordLoginAttempt(
    PDO $pdo,
    string $ipAddress,
    ?string $username,
    bool $success
): void {

    try {

        $stmt =
            $pdo->prepare(
                'INSERT INTO admin_login_attempts (
                    ip_address,
                    username,
                    success
                 )
                 VALUES (
                    :ip_address,
                    :username,
                    :success
                 )'
            );

        $stmt->execute([
            ':ip_address' =>
                $ipAddress,

            ':username' =>
                $username !== ''
                    ? $username
                    : null,

            ':success' =>
                $success ? 1 : 0,
        ]);

    } catch (Throwable $exception) {

        error_log(
            '[LOGIN RATE LIMIT] '
            . $exception->getMessage()
        );
    }
}


function clearOldLoginAttempts(
    PDO $pdo
): void {

    try {

        $pdo->exec(
            'DELETE FROM admin_login_attempts
             WHERE attempted_at < DATE_SUB(
                NOW(),
                INTERVAL 24 HOUR
             )'
        );

    } catch (Throwable $exception) {

        error_log(
            '[LOGIN RATE LIMIT CLEANUP] '
            . $exception->getMessage()
        );
    }
}
