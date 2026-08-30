<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$scheduler = new ProspectScheduler($config);

$timezone = new DateTimeZone('Europe/Paris');

/*
|--------------------------------------------------------------------------
| Test 1 — Validation un dimanche
|--------------------------------------------------------------------------
*/

$approvedAt = new DateTimeImmutable(
    '2026-08-30 13:06:09',
    $timezone
);

$slot = $scheduler->findNextAvailableSlot(
    $approvedAt,
    static fn (string $date): int => 0
);

echo 'Test 1 : '
    . $slot->format('Y-m-d H:i:s')
    . PHP_EOL;

if (
    $slot->format('Y-m-d H:i:s')
    !== '2026-08-31 09:30:00'
) {
    throw new RuntimeException(
        'Échec du test 1.'
    );
}

/*
|--------------------------------------------------------------------------
| Test 2 — Lundi complet
|--------------------------------------------------------------------------
*/

$slot = $scheduler->findNextAvailableSlot(
    $approvedAt,
    static function (string $date): int {
        if ($date === '2026-08-31') {
            return 5;
        }

        return 0;
    }
);

echo 'Test 2 : '
    . $slot->format('Y-m-d H:i:s')
    . PHP_EOL;

if (
    $slot->format('Y-m-d H:i:s')
    !== '2026-09-01 09:30:00'
) {
    throw new RuntimeException(
        'Échec du test 2.'
    );
}

/*
|--------------------------------------------------------------------------
| Test 3 — Validation un vendredi
|--------------------------------------------------------------------------
*/

$fridayApproval = new DateTimeImmutable(
    '2026-09-04 15:00:00',
    $timezone
);

$slot = $scheduler->findNextAvailableSlot(
    $fridayApproval,
    static fn (string $date): int => 0
);

echo 'Test 3 : '
    . $slot->format('Y-m-d H:i:s')
    . PHP_EOL;

if (
    $slot->format('Y-m-d H:i:s')
    !== '2026-09-07 09:30:00'
) {
    throw new RuntimeException(
        'Échec du test 3.'
    );
}

echo PHP_EOL;
echo 'Tous les tests du scheduler sont OK.'
    . PHP_EOL;
    