<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/ProspectMailTransport.php';

$mailConfig = require __DIR__
    . '/../../config/mail.php';

$transport = new ProspectMailTransport(
    $mailConfig
);

echo '=== TEST 1 : instanciation ==='
    . PHP_EOL;

if (
    !$transport instanceof ProspectMailTransport
) {
    throw new RuntimeException(
        'Transport non instancié.'
    );
}

echo 'Instanciation : OK'
    . PHP_EOL;

echo PHP_EOL;

echo '=== TEST 2 : aucune connexion SMTP ==='
    . PHP_EOL;

/*
 * IMPORTANT :
 * On ne doit PAS appeler ->send() ici.
 *
 * Ce test vérifie uniquement que la classe peut
 * être chargée avec la configuration existante.
 */

echo 'Aucun appel send() : OK'
    . PHP_EOL;

echo PHP_EOL;

echo 'Transport SMTP préparé sans envoi réel : OK'
    . PHP_EOL;
    