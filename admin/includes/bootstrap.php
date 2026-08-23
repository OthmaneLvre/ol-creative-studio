<?php

declare(strict_types=1);

require_once __DIR__ . '/security-headers.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../../php/db.php';

/*
|--------------------------------------------------------------------------
| Token CSRF global admin
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}