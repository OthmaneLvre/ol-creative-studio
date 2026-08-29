<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/admin-log.php';


/*
|--------------------------------------------------------------------------
| Authentification
|--------------------------------------------------------------------------
*/

if (
    !isset(
        $_SESSION['admin_logged'],
        $_SESSION['admin_id'],
        $_SESSION['admin_name']
    ) ||
    $_SESSION['admin_logged'] !== true ||
    !is_int($_SESSION['admin_id']) ||
    $_SESSION['admin_id'] < 1
) {
    header('Location: /admin/login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| Timeout d'inactivité
|--------------------------------------------------------------------------
*/

$sessionTimeout = 30 * 60;

$lastActivity =
    isset($_SESSION['admin_last_activity'])
        ? (int) $_SESSION['admin_last_activity']
        : null;

if (
    $lastActivity !== null &&
    (time() - $lastActivity) > $sessionTimeout
) {

    writeAdminLog(
        $pdo,
        'auth.session_expired',
        'admin_user',
        (int) $_SESSION['admin_id']
    );

    $_SESSION = [];

    if (
        ini_get('session.use_cookies')
    ) {

        $params =
            session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            [
                'expires' =>
                    time() - 42000,

                'path' =>
                    $params['path'],

                'domain' =>
                    $params['domain'],

                'secure' =>
                    $params['secure'],

                'httponly' =>
                    $params['httponly'],

                'samesite' =>
                    $params['samesite']
                    ?? 'Strict',
            ]
        );
    }

    session_destroy();

    header(
        'Location: /admin/login.php?expired=1'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Mise à jour de l'activité
|--------------------------------------------------------------------------
*/

$_SESSION['admin_last_activity'] =
    time();
