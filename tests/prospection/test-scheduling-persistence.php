<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

require_once __DIR__
    . '/../../php/prospection/ProspectScheduler.php';

$config = require __DIR__
    . '/../../config/prospection.php';

$prospectId = 3;

$timezone = new DateTimeZone('Europe/Paris');

$repository = new ProspectRepository($pdo);
$scheduler = new ProspectScheduler($config);

$stmt = $pdo->prepare(
    '
    SELECT approved_at
    FROM prospect_emails
    WHERE prospect_id = :prospect_id
      AND email_type = :email_type
      AND status = :status
    ORDER BY id DESC
    LIMIT 1
    '
);

$stmt->execute([
    'prospect_id' => $prospectId,
    'email_type' => 'initial',
    'status' => 'approved',
]);

$approvedAtRaw = $stmt->fetchColumn();

if (!$approvedAtRaw) {
    throw new RuntimeException(
        'Aucun e-mail initial approuvé trouvé pour le prospect.'
    );
}

$approvedAt = new DateTimeImmutable(
    (string) $approvedAtRaw,
    $timezone
);

$scheduledAt = $scheduler->findNextAvailableSlot(
    $approvedAt,
    function (string $date) use ($repository, $timezone): int {
        return $repository->countScheduledInitialEmailsForDate(
            new DateTimeImmutable($date, $timezone)
        );
    }
);

echo 'Approbation : '
    . $approvedAt->format('Y-m-d H:i:s')
    . PHP_EOL;

echo 'Programmation : '
    . $scheduledAt->format('Y-m-d H:i:s')
    . PHP_EOL;

$scheduledAt = $repository->scheduleApprovedProspectWithDailyLimit(
    $prospectId,
    $scheduler,
    $timezone
);

$stmt = $pdo->prepare(
    '
    SELECT status, scheduled_at
    FROM prospects
    WHERE id = :id
    '
);

$stmt->execute([
    'id' => $prospectId,
]);

$prospect = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare(
    '
    SELECT id, status, scheduled_at, sent_at
    FROM prospect_emails
    WHERE prospect_id = :prospect_id
      AND email_type = :email_type
    ORDER BY id DESC
    LIMIT 1
    '
);

$stmt->execute([
    'prospect_id' => $prospectId,
    'email_type' => 'initial',
]);

$email = $stmt->fetch(PDO::FETCH_ASSOC);

echo PHP_EOL;
echo 'Prospect :' . PHP_EOL;
print_r($prospect);

echo PHP_EOL;
echo 'Email :' . PHP_EOL;
print_r($email);

if (
    ($prospect['status'] ?? null) !== 'scheduled'
    || ($email['status'] ?? null) !== 'scheduled'
) {
    throw new RuntimeException(
        'La programmation n’a pas été correctement persistée.'
    );
}

if (($email['sent_at'] ?? null) !== null) {
    throw new RuntimeException(
        'L’e-mail ne doit pas être envoyé pendant ce test.'
    );
}

echo PHP_EOL;
echo 'Programmation persistée avec succès.' . PHP_EOL;
echo 'Aucun e-mail envoyé.' . PHP_EOL;