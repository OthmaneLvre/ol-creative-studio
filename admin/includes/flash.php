<?php

declare(strict_types=1);


/*
|--------------------------------------------------------------------------
| Définir un message flash
|--------------------------------------------------------------------------
*/

function setFlash(
    string $type,
    string $message
): void {

    $allowedTypes = [
        'success',
        'error',
        'warning',
        'info',
    ];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $_SESSION['admin_flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}


/*
|--------------------------------------------------------------------------
| Récupérer un message flash
|--------------------------------------------------------------------------
*/

function getFlash(): ?array
{
    if (
        !isset($_SESSION['admin_flash']) ||
        !is_array($_SESSION['admin_flash'])
    ) {
        return null;
    }

    $flash = $_SESSION['admin_flash'];

    unset($_SESSION['admin_flash']);

    return $flash;
}
