<?php

declare(strict_types=1);

require_once __DIR__ . '/security-headers.php';


/*
|--------------------------------------------------------------------------
| Session sécurisée
|--------------------------------------------------------------------------
*/

if (session_status() !== PHP_SESSION_ACTIVE) {

    $isHttps =
        (
            isset($_SERVER['HTTPS']) &&
            $_SERVER['HTTPS'] !== '' &&
            $_SERVER['HTTPS'] !== 'off'
        )
        ||
        (
            isset($_SERVER['HTTP_X_FORWARDED_PROTO']) &&
            $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'
        );

    ini_set(
        'session.use_strict_mode',
        '1'
    );

    ini_set(
        'session.use_only_cookies',
        '1'
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);

    session_name(
        'OLADMINSESSID'
    );

    session_start();
}


require_once __DIR__ . '/../../php/db.php';


/*
|--------------------------------------------------------------------------
| Token CSRF global admin
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] =
        bin2hex(
            random_bytes(32)
        );
}