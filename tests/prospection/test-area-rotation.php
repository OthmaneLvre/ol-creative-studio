<?php

declare(strict_types=1);

require_once __DIR__ . '/../../admin/prospection/includes/bootstrap.php';
require_once __DIR__
    . '/../../php/prospection/ProspectingAreaManager.php';

$areaManager = new ProspectingAreaManager($pdo);

$area = $areaManager->getNextArea();

if ($area === null) {
    echo 'Aucune zone active disponible.' . PHP_EOL;
    exit;
}

echo 'Zone sélectionnée :' . PHP_EOL;
echo 'ID : ' . $area['id'] . PHP_EOL;
echo 'Commune : ' . $area['city'] . PHP_EOL;
echo 'Dernier scan : ' . ($area['last_scanned_at'] ?? 'jamais') . PHP_EOL;

$areaManager->markAsScanned((int) $area['id']);

echo PHP_EOL;
echo 'Zone marquée comme analysée.' . PHP_EOL;

$nextArea = $areaManager->getNextArea();

if ($nextArea === null) {
    echo 'Aucune zone suivante disponible.' . PHP_EOL;
    exit;
}

echo PHP_EOL;
echo 'Prochaine zone :' . PHP_EOL;
echo 'ID : ' . $nextArea['id'] . PHP_EOL;
echo 'Commune : ' . $nextArea['city'] . PHP_EOL;
echo 'Dernier scan : ' . ($nextArea['last_scanned_at'] ?? 'jamais') . PHP_EOL;
