<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/ProspectScorer.php';

$prospectId = 3;

try {
    $prospectStatement = $pdo->prepare(
        <<<SQL
            SELECT
                id,
                company_name,
                activity,
                city,
                postal_code,
                website_url,
                email,
                phone
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
            SELECT raw_analysis
            FROM prospect_analyses
            WHERE prospect_id = :prospect_id
            ORDER BY id DESC
            LIMIT 1
        SQL
    );

    $analysisStatement->execute([
        'prospect_id' => $prospectId,
    ]);

    $rawAnalysis =
        $analysisStatement->fetchColumn();

    if (
        $rawAnalysis === false
        || $rawAnalysis === null
    ) {
        throw new RuntimeException(
            'Aucune analyse disponible.'
        );
    }

    $analysis = json_decode(
        (string) $rawAnalysis,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    $scorer = new ProspectScorer();

    $result = $scorer->score(
        $prospect,
        $analysis
    );

    echo '=== SCORE PROSPECT ==='
        . PHP_EOL
        . PHP_EOL;

    echo 'Prospect : '
        . $prospect['company_name']
        . PHP_EOL;

    echo 'Score : '
        . $result['score']
        . '/100'
        . PHP_EOL;

    echo 'QualifiÃ© : '
        . (
            $result['qualified']
                ? 'Oui'
                : 'Non'
        )
        . PHP_EOL;

    echo 'Seuil : '
        . $result['threshold']
        . '/100'
        . PHP_EOL
        . PHP_EOL;

    foreach (
        $result['breakdown']
        as $name => $category
    ) {
        echo strtoupper($name)
            . ' : '
            . $category['score']
            . '/'
            . $category['max']
            . PHP_EOL;

        foreach (
            $category['reasons']
            as $reason
        ) {
            echo ' - '
                . $reason
                . PHP_EOL;
        }

        echo PHP_EOL;
    }

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
