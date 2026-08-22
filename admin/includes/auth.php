<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/flash.php';

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