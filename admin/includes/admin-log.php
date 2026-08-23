<?php

declare(strict_types=1);

function writeAdminLog(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null
): void {

    try {

        $adminId =
            isset($_SESSION['admin_id'])
                ? (int) $_SESSION['admin_id']
                : null;

        $ipAddress =
            isset($_SERVER['REMOTE_ADDR'])
                ? (string) $_SERVER['REMOTE_ADDR']
                : null;

        $stmt =
            $pdo->prepare(
                'INSERT INTO admin_logs (
                    admin_id,
                    action,
                    entity_type,
                    entity_id,
                    details,
                    ip_address
                 )
                 VALUES (
                    :admin_id,
                    :action,
                    :entity_type,
                    :entity_id,
                    :details,
                    :ip_address
                 )'
            );

        $stmt->execute([
            ':admin_id' =>
                $adminId,

            ':action' =>
                $action,

            ':entity_type' =>
                $entityType,

            ':entity_id' =>
                $entityId,

            ':details' =>
                $details,

            ':ip_address' =>
                $ipAddress,
        ]);

    } catch (Throwable $exception) {

        error_log(
            '[ADMIN LOG] '
            . $exception->getMessage()
        );
    }
}