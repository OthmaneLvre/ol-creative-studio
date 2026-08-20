<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (
    !isset($_SESSION['admin_logged']) ||
    $_SESSION['admin_logged'] !== true
) {
    header('Location: /admin/login.php');
    exit;
}