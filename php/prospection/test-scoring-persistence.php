<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/ProspectRepository.php';

require_once __DIR__
    . '/ProspectScorer.php';

$prospectId = 3;

try {
    /*
     * Prospect.
     */
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

    /*
     * Dernière analyse technique.
     */
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

    /*
     * Calcul.
     */
    $scorer = new ProspectScorer();

    $scoring = $scorer->score(
        $prospect,
        $analysis
    );

    echo '=== SCORING ==='
        . PHP_EOL
        . PHP_EOL;

    echo 'Prospect : '
        . $prospect['company_name']
        . PHP_EOL;

    echo 'Score calculé : '
        . $scoring['score']
        . '/100'
        . PHP_EOL;

    echo 'Qualifié : '
        . (
            $scoring['qualified']
                ? 'Oui'
                : 'Non'
        )
        . PHP_EOL
        . PHP_EOL;

    /*
     * Persistance.
     */
    $repository =
        new ProspectRepository($pdo);

    $repository->saveScoringResult(
        $prospectId,
        $scoring
    );

    echo '[OK] Score enregistré pour le prospect #'
        . $prospectId
        . '.'
        . PHP_EOL;

} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
