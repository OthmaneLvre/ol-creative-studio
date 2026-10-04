<?php

declare(strict_types=1);

require __DIR__ . '/../../php/db.php';

require __DIR__
    . '/../../php/prospection/ProspectingLogger.php';

$passed = 0;
$failed = 0;

$runId =
    'test-logger-'
    . bin2hex(random_bytes(8));

function assertLogger(
    bool $condition,
    string $message
): void {
    global $passed, $failed;

    if ($condition) {
        $passed++;

        echo '[OK]   '
            . $message
            . PHP_EOL;

        return;
    }

    $failed++;

    echo '[FAIL] '
        . $message
        . PHP_EOL;
}

try {
    echo '=== TEST PROSPECTING LOGGER ==='
        . PHP_EOL
        . PHP_EOL;

    $logger = new ProspectingLogger(
        $pdo,
        'test',
        $runId
    );

    assertLogger(
        $logger->getRunId() === $runId,
        'Run ID conserve'
    );

    $result = $logger->info(
        'test_started',
        'Demarrage du test logger.',
        [
            'source' => 'automated_test',
            'count' => 3,
        ]
    );

    assertLogger(
        $result === true,
        'Log info insere'
    );

    $result = $logger->warning(
        'test_warning',
        'Avertissement de test.'
    );

    assertLogger(
        $result === true,
        'Log warning insere'
    );

    $result = $logger->error(
        'test_error',
        'Erreur de test.',
        [
            'retry' => false,
        ]
    );

    assertLogger(
        $result === true,
        'Log error insere'
    );

    /*
    |--------------------------------------------------------------------------
    | Lecture des logs
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        SELECT
            run_id,
            channel,
            level,
            event_type,
            message,
            prospect_id,
            email_id,
            context
        FROM prospecting_logs
        WHERE run_id = :run_id
        ORDER BY id ASC
        '
    );

    $statement->execute([
        'run_id' => $runId,
    ]);

    $logs = $statement->fetchAll(
        PDO::FETCH_ASSOC
    );

    assertLogger(
        count($logs) === 3,
        'Trois logs retrouves'
    );

    assertLogger(
        ($logs[0]['channel'] ?? null)
            === 'test',
        'Canal correctement persiste'
    );

    assertLogger(
        ($logs[0]['level'] ?? null)
            === 'info',
        'Niveau info correctement persiste'
    );

    assertLogger(
        ($logs[1]['level'] ?? null)
            === 'warning',
        'Niveau warning correctement persiste'
    );

    assertLogger(
        ($logs[2]['level'] ?? null)
            === 'error',
        'Niveau error correctement persiste'
    );

    $context = json_decode(
        (string) $logs[0]['context'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    assertLogger(
        ($context['source'] ?? null)
            === 'automated_test'
        && ($context['count'] ?? null) === 3,
        'Contexte JSON correctement persiste'
    );

    assertLogger(
        $logs[0]['prospect_id'] === null
        && $logs[0]['email_id'] === null,
        'Relations prospect/email optionnelles'
    );

    /*
    |--------------------------------------------------------------------------
    | Generation automatique du run ID
    |--------------------------------------------------------------------------
    */

    $automaticLogger =
        new ProspectingLogger(
            $pdo,
            'discovery'
        );

    assertLogger(
        str_starts_with(
            $automaticLogger->getRunId(),
            'discovery-'
        ),
        'Run ID automatique genere'
    );

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    $invalidLevelRejected = false;

    try {
        $logger->log(
            'invalid',
            'test',
            'Test'
        );
    } catch (InvalidArgumentException) {
        $invalidLevelRejected = true;
    }

    assertLogger(
        $invalidLevelRejected,
        'Niveau invalide refuse'
    );

    $emptyEventRejected = false;

    try {
        $logger->info(
            '',
            'Test'
        );
    } catch (InvalidArgumentException) {
        $emptyEventRejected = true;
    }

    assertLogger(
        $emptyEventRejected,
        'Event type vide refuse'
    );

    $emptyMessageRejected = false;

    try {
        $logger->info(
            'test',
            ''
        );
    } catch (InvalidArgumentException) {
        $emptyMessageRejected = true;
    }

    assertLogger(
        $emptyMessageRejected,
        'Message vide refuse'
    );
} finally {
    /*
    |--------------------------------------------------------------------------
    | Nettoyage
    |--------------------------------------------------------------------------
    */

    $statement = $pdo->prepare(
        '
        DELETE FROM prospecting_logs
        WHERE run_id = :run_id
        '
    );

    $statement->execute([
        'run_id' => $runId,
    ]);

    echo PHP_EOL
        . 'Logs temporaires supprimes.'
        . PHP_EOL;
}

echo PHP_EOL
    . '=== RESULTAT ==='
    . PHP_EOL;

echo 'OK   : '
    . $passed
    . PHP_EOL;

echo 'FAIL : '
    . $failed
    . PHP_EOL;

if ($failed > 0) {
    exit(1);
}

echo PHP_EOL
    . 'Prospecting Logger : OK'
    . PHP_EOL;
    