<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$timezone = new DateTimeZone('Europe/Paris');

$scheduler = new ProspectScheduler($config);

$approvedAt = new DateTimeImmutable(
    '2026-08-30 13:06:09',
    $timezone
);

/*
|--------------------------------------------------------------------------
| Cas 1 : 4 e-mails déjà programmés
|--------------------------------------------------------------------------
|
| Le scheduler doit encore accepter le 31/08.
|
*/

$slotWithFour = $scheduler->findNextAvailableSlot(
    $approvedAt,
    static function (string $date): int {
        if ($date === '2026-08-31') {
            return 4;
        }

        return 0;
    }
);

if (
    $slotWithFour->format('Y-m-d H:i:s')
    !== '2026-08-31 09:30:00'
) {
    throw new RuntimeException(
        'Échec du test 4/5.'
    );
}

echo 'Test 4/5 : '
    . $slotWithFour->format('Y-m-d H:i:s')
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| Cas 2 : 5 e-mails déjà programmés
|--------------------------------------------------------------------------
|
| Le 31/08 est plein.
| Le scheduler doit reporter au 01/09.
|
*/

$slotWithFive = $scheduler->findNextAvailableSlot(
    $approvedAt,
    static function (string $date): int {
        if ($date === '2026-08-31') {
            return 5;
        }

        return 0;
    }
);

if (
    $slotWithFive->format('Y-m-d H:i:s')
    !== '2026-09-01 09:30:00'
) {
    throw new RuntimeException(
        'Échec du test 5/5.'
    );
}

echo 'Test 5/5 : '
    . $slotWithFive->format('Y-m-d H:i:s')
    . PHP_EOL;

echo PHP_EOL;
echo 'Limite quotidienne scheduler OK.' . PHP_EOL;
