<?php

declare(strict_types=1);

/**
 * Bootstrap du module Prospection.
 *
 * Ce fichier initialise les dépendances communes nécessaires
 * aux pages du module de prospection.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

/*
|--------------------------------------------------------------------------
| Configuration du module
|--------------------------------------------------------------------------
*/

$prospectionConfigPath = dirname(__DIR__, 3) . '/config/prospection.php';

if (!is_file($prospectionConfigPath)) {
    throw new RuntimeException(
        'Le fichier de configuration du module Prospection est introuvable.'
    );
}

$prospectionConfig = require $prospectionConfigPath;

if (!is_array($prospectionConfig)) {
    throw new RuntimeException(
        'La configuration du module Prospection est invalide.'
    );
}

/*
|--------------------------------------------------------------------------
| Accès simplifié à la configuration
|--------------------------------------------------------------------------
*/

/**
 * Retourne une valeur de configuration du module Prospection.
 *
 * Exemple :
 * prospection_config('sending.max_daily_sends', 5);
 */
if (!function_exists('prospection_config')) {
    function prospection_config(
        string $key,
        mixed $default = null
    ): mixed {
        global $prospectionConfig;

        $value = $prospectionConfig;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }
}
