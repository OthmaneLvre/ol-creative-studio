<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv::createImmutable(
    dirname(__DIR__)
);

$dotenv->safeLoad();

return [
    'host' => $_ENV['MAIL_HOST'] ?? 'ssl0.ovh.net',
    'port' => (int) ($_ENV['MAIL_PORT'] ?? 465),
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? 'ssl',

    'username' => $_ENV['MAIL_USERNAME'] ?? '',
    'password' => $_ENV['MAIL_PASSWORD'] ?? '',

    'from_email' => $_ENV['MAIL_FROM_EMAIL']
        ?? 'contact@olcreativestudio.fr',

    'from_name' => $_ENV['MAIL_FROM_NAME']
        ?? 'OL Creative Studio',
];