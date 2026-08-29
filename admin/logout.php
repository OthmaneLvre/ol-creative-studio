<?php

declare(strict_types=1);

require_once __DIR__
    . '/includes/bootstrap.php';


/*
|--------------------------------------------------------------------------
| Nettoyage de la session
|--------------------------------------------------------------------------
*/

$_SESSION = [];


/*
|--------------------------------------------------------------------------
| Suppression du cookie de session
|--------------------------------------------------------------------------
*/

if (ini_get('session.use_cookies')) {

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


/*
|--------------------------------------------------------------------------
| Destruction de la session
|--------------------------------------------------------------------------
*/

session_destroy();


/*
|--------------------------------------------------------------------------
| Redirection
|--------------------------------------------------------------------------
*/

header(
    'Location: /admin/login.php?logged_out=1'
);

exit;