<?php

declare(strict_types=1);

require_once __DIR__
    . '/../../admin/prospection/includes/bootstrap.php';

require_once __DIR__
    . '/../../php/prospection/AiMessageGenerator.php';

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

    /*
     * Chargement du prospect.
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
                email_type,
                status,
                score
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

    if (
        ($prospect['status'] ?? null)
        !== 'qualified'
    ) {
        throw new RuntimeException(
            'Le prospect doit Ãªtre qualifiÃ© avant la gÃ©nÃ©ration du message.'
        );
    }

    /*
     * Chargement du dernier audit IA.
     */
    $analysisStatement = $pdo->prepare(
        <<<SQL
            SELECT
                ai_audit_details
            FROM prospect_analyses
            WHERE prospect_id = :prospect_id
            ORDER BY id DESC
            LIMIT 1
        SQL
    );

    $analysisStatement->execute([
        'prospect_id' => $prospectId,
    ]);

    $auditJson =
        $analysisStatement->fetchColumn();

    if (
        $auditJson === false
        || $auditJson === null
        || trim((string) $auditJson) === ''
    ) {
        throw new RuntimeException(
            'Aucun audit IA disponible.'
        );
    }

    $audit = json_decode(
        (string) $auditJson,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

    if (!is_array($audit)) {
        throw new RuntimeException(
            'Audit IA invalide.'
        );
    }

    /*
     * GÃ©nÃ©ration du premier message.
     */
    $generator = new AiMessageGenerator(
        $apiKey,
        $model
    );

    $message = $generator->generate(
        $prospect,
        $audit
    );

    $repository = new ProspectRepository($pdo);

    $emailId = $repository->saveInitialEmailDraft(
        $prospectId,
        $message
    );

    echo '=== PREMIER EMAIL ==='
        . PHP_EOL
        . PHP_EOL;

    echo 'Prospect : '
        . $prospect['company_name']
        . PHP_EOL;

    echo 'Destinataire : '
        . ($prospect['email'] ?? 'Aucun')
        . PHP_EOL
        . PHP_EOL;

    echo 'Objet :'
        . PHP_EOL
        . $message['subject']
        . PHP_EOL
        . PHP_EOL;

    echo 'PrÃ©header :'
        . PHP_EOL
        . $message['preheader']
        . PHP_EOL
        . PHP_EOL;

    echo 'Introduction :'
        . PHP_EOL
        . $message['opening']
        . PHP_EOL
        . PHP_EOL;

    echo 'Message :'
        . PHP_EOL
        . $message['body']
        . PHP_EOL
        . PHP_EOL;

    echo 'CTA :'
        . PHP_EOL
        . $message['cta']
        . PHP_EOL;

    echo PHP_EOL
        . '[OK] Brouillon e-mail #'
        . $emailId
        . ' enregistrÃ©.'
        . PHP_EOL;


} catch (Throwable $exception) {
    echo '[ERREUR] '
        . $exception->getMessage()
        . PHP_EOL;

    exit(1);
}
