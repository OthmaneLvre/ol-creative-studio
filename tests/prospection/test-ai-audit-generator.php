<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/AiAuditGenerator.php';

require_once __DIR__
    . '/../../php/prospection/ProspectRepository.php';

$prospectId = 3;

try {
    $apiKey =
        $_ENV['OPENAI_API_KEY']
        ?? getenv('OPENAI_API_KEY')
        ?: '';

    $model =
        $_ENV['OPENAI_MODEL']
        ?? getenv('OPENAI_MODEL')
        ?: 'gpt-5.6-luna';

    if ($apiKey === '') {
        throw new RuntimeException(
            'OPENAI_API_KEY absente.'
        );
    }

    $prospectStatement = $pdo->prepare(
        <<<SQL
            SELECT
                id,
                company_name,
                activity,
                city,
                postal_code,
                website_url,
                email_type
            FROM prospects
            WHERE id = :id
            LIMIT 1
        SQL
    );

    $prospectStatement->execute([
        'id' => $prospectId,
    ]);

    $prospect = $prospectStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if ($prospect === false) {
        throw new RuntimeException(
            'Prospect introuvable.'
        );
    }

    $analysisStatement = $pdo->prepare(
        <<<SQL
            SELECT
                raw_analysis,
                scoring_details
            FROM prospect_analyses
            WHERE prospect_id = :prospect_id
            ORDER BY id DESC
            LIMIT 1
        SQL
    );

    $analysisStatement->execute([
        'prospect_id' => $prospectId,
    ]);

    $analysisRow = $analysisStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if ($analysisRow === false) {
        throw new RuntimeException(
            'Analyse introuvable.'
        );
    }

    $analysis = json_decode(
        (string) $analysisRow['raw_analysis'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $scoring = json_decode(
        (string) $analysisRow['scoring_details'],
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $generator = new AiAuditGenerator(
        $apiKey,
        $model
    );

    $audit = $generator->generate(
        $prospect,
        $analysis,
        $scoring
    );

    $repository = new ProspectRepository($pdo);

    $repository->saveAiAudit(
        $prospectId,
        $audit
    );

    echo '=== AUDIT IA ==='
        . PHP_EOL
        . PHP_EOL;

    echo 'RÃ©sumÃ© : '
        . $audit['summary']
        . PHP_EOL
        . PHP_EOL;

    echo 'Forces :'
        . PHP_EOL;

    foreach ($audit['strengths'] as $item) {
        echo ' - ' . $item . PHP_EOL;
    }

    echo PHP_EOL
        . 'Faiblesses :'
        . PHP_EOL;

    foreach ($audit['weaknesses'] as $item) {
        echo ' - ' . $item . PHP_EOL;
    }

    echo PHP_EOL
        . 'OpportunitÃ©s :'
        . PHP_EOL;

    foreach ($audit['opportunities'] as $item) {
        echo ' - ' . $item . PHP_EOL;
    }

    echo PHP_EOL
        . 'Angle recommandÃ© : '
        . $audit['recommended_angle']
        . PHP_EOL
        . PHP_EOL;

    echo 'Audit :'
        . PHP_EOL
        . $audit['audit_text']
        . PHP_EOL;

    echo PHP_EOL
    . '[OK] Audit IA enregistrÃ© pour le prospect #'
    . $prospectId
    . '.'
    . PHP_EOL;

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
